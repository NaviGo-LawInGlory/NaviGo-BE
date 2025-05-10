<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Traits\DocumentUtilityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class DocumentContentController extends Controller
{
    use DocumentUtilityTrait;
    
    public function download(Request $request, $id): \Symfony\Component\HttpFoundation\Response
    {
        $document = Document::findOrFail($id);
        
        if ($document->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        if (!$document->file_path || !Storage::exists($document->file_path)) {
            $content = $document->content;
            $extension = 'html';
            
            $fileName = Str::slug($document->judul) . '-' . time() . '.' . $extension;
            $filePath = 'documents/' . $request->user()->id . '/' . $fileName;
            
            $directory = dirname(Storage::path($filePath));
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            
            if (!str_contains($content, '<!DOCTYPE html>') && !str_contains($content, '<html')) {
                $content = $this->wrapInHtmlDocument($content, $document->judul);
            }
            
            Storage::put($filePath, $content);
            
            $document->update(['file_path' => $filePath]);
        }
        
        return $this->serveDocumentFile($document);
    }
    
    private function serveDocumentFile(Document $document): \Symfony\Component\HttpFoundation\Response
    {
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        
        $contentType = match ($extension) {
            'html' => 'text/html',
            'pdf' => 'application/pdf',
            'md' => 'text/markdown',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };
        
        $downloadFilename = Str::slug($document->judul) . '.' . $extension;
        
        return response(Storage::get($document->file_path), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $downloadFilename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function getContent(Request $request, $id): JsonResponse
    {
        try {
            $document = Document::findOrFail($id);
            
            if ($document->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            
            $content = $this->getDocumentContent($document);
            
            return response()->json([
                'content' => $content,
                'title' => $document->judul,
                'contentType' => 'text/html',
                'documentId' => $document->id,
                'fileName' => pathinfo($document->file_path, PATHINFO_BASENAME) ?? $document->judul . '.html',
                'download_url' => url('/api/v1/documents/' . $document->id . '/download'),
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to retrieve document content: ' . $e->getMessage()], 500);
        }
    }
    
    private function getDocumentContent(Document $document): string
    {
        if ($document->file_path && Storage::exists($document->file_path)) {
            return Storage::get($document->file_path);
        }
        
        $content = $document->content;
        
        if (!str_contains($content, '<!DOCTYPE html>') && !str_contains($content, '<html')) {
            $content = $this->wrapInHtmlDocument($content, $document->judul);
        }
        
        return $content;
    }
}

