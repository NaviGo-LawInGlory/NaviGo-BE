<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Google\GenerativeAI\GenerativeModel;
use Google\GenerativeAI\Part;

class DocumentController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'perjanjian' => 'required|string|max:255',
            'pihak1' => 'required|string|max:255',
            'pihak2' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'tanggal' => 'required|date',
        ]);

        try {
            $apiKey = config('services.gemini.api_key');
            $model = new GenerativeModel('gemini-pro', $apiKey);
            
            $prompt = "Buat dokumen perjanjian dengan detail berikut: 
            Judul: {$validated['judul']}
            Jenis Perjanjian: {$validated['perjanjian']}
            Pihak Pertama: {$validated['pihak1']}
            Pihak Kedua: {$validated['pihak2']}
            Deskripsi: {$validated['deskripsi']}
            Tanggal: {$validated['tanggal']}
            Berikan format dokumen resmi dalam bahasa Indonesia yang lengkap.";
            
            $result = $model->generateContent($prompt);
            $content = $result->text();
            
            $document = Document::create([
                'user_id' => $request->user()->id,
                'type' => 'generated',
                'judul' => $validated['judul'],
                'perjanjian' => $validated['perjanjian'],
                'pihak1' => $validated['pihak1'],
                'pihak2' => $validated['pihak2'],
                'deskripsi' => $validated['deskripsi'],
                'tanggal' => $validated['tanggal'],
                'content' => $content,
                'file_path' => null,
            ]);
            
            $fileName = Str::slug($validated['judul']) . '-' . time() . '.pdf';
            $filePath = 'documents/' . $request->user()->id . '/' . $fileName;
            
            $document->update(['file_path' => $filePath]);
            
            Activity::create([
                'user_id' => $request->user()->id,
                'type' => 'generator',
                'title' => $validated['judul'],
                'document_id' => $document->id,
            ]);
            
            return response()->json([
                'id' => $document->id,
                'content' => $content,
                'download_url' => url('/api/v1/documents/' . $document->id . '/download'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate document: ' . $e->getMessage()], 500);
        }
    }
    
    public function download(Request $request, $id): \Symfony\Component\HttpFoundation\Response
    {
        $document = Document::findOrFail($id);
        
        if ($document->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        if (!$document->file_path || !Storage::exists($document->file_path)) {
            $content = $document->content;
            $headers = [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . basename($document->file_path) . '"',
            ];
            
            return response($content, 200, $headers);
        }
        
        return Storage::download($document->file_path);
    }
    
    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);
        
        try {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads', $fileName);
            
            $fileContent = "Simulated extracted content from " . $file->getClientOriginalName();
            
            $apiKey = config('services.gemini.api_key');
            $model = new GenerativeModel('gemini-pro', $apiKey);
            
            $prompt = "Analyze the following legal document and extract these details: judul (title), tanggal (date), pihak (first party), pihak2 (second party), perjanjian (agreement type), and deskripsi (description). Return as JSON format. Document content: {$fileContent}";
            
            $result = $model->generateContent($prompt);
            $analysisText = $result->text();
            
            $analysis = json_decode($analysisText, true);
            if (!$analysis) {
                $analysis = [
                    'judul' => 'Unknown Document',
                    'tanggal' => date('Y-m-d'),
                    'pihak' => 'Unknown Party 1',
                    'pihak2' => 'Unknown Party 2',
                    'perjanjian' => 'Unknown Agreement Type',
                    'deskripsi' => 'Unable to extract description',
                ];
            }
            
            $document = Document::create([
                'user_id' => $request->user()->id,
                'type' => 'analyzed',
                'judul' => $analysis['judul'] ?? 'Unknown Document',
                'perjanjian' => $analysis['perjanjian'] ?? 'Unknown Agreement Type',
                'pihak1' => $analysis['pihak'] ?? 'Unknown Party 1',
                'pihak2' => $analysis['pihak2'] ?? 'Unknown Party 2',
                'deskripsi' => $analysis['deskripsi'] ?? 'Unable to extract description',
                'tanggal' => $analysis['tanggal'] ?? date('Y-m-d'),
                'content' => $fileContent,
                'file_path' => $filePath,
            ]);
            
            Activity::create([
                'user_id' => $request->user()->id,
                'type' => 'analyzer',
                'title' => $analysis['judul'] ?? 'Document Analysis',
                'document_id' => $document->id,
            ]);
            
            return response()->json($analysis);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to analyze document: ' . $e->getMessage()], 500);
        }
    }
}

