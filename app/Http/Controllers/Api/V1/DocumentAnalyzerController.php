<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Activity;
use App\Traits\DocumentUtilityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;

class DocumentAnalyzerController extends Controller
{
    use DocumentUtilityTrait;
    
    public function analyze(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx|max:10240',
        ]);
        
        try {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads', $fileName);
            
            $apiKey = config('services.gemini.api_key');
            
            $response = $this->getAnalysisResponse($file, $apiKey);
            
            if ($response->failed()) {
                throw new \Exception('Gemini API error: ' . $response->body());
            }
            
            $responseData = $response->json();
            $analysisText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            if (empty($analysisText)) {
                throw new \Exception('Failed to analyze document content');
            }
            
            $analysis = $this->parseAnalysisResponse($analysisText);
            
            $formattedDate = $this->formatDateString($analysis['tanggal'] ?? date('Y-m-d'));
            $document = $this->saveDocument($request, $analysis, $formattedDate, $analysisText, $filePath);
            
            return response()->json([
                'judul' => $analysis['judul'] ?? 'Unknown Document',
                'tanggal' => $formattedDate,
                'pihak' => $analysis['pihak'] ?? 'Unknown Party 1',
                'pihak2' => $analysis['pihak2'] ?? 'Unknown Party 2',
                'perjanjian' => $analysis['perjanjian'] ?? 'Unknown Agreement Type',
                'deskripsi' => $analysis['deskripsi'] ?? 'Unable to extract description',
                'htmlSummary' => $analysis['htmlSummary'] ?? '<p>No summary available</p>'
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to analyze document: ' . $e->getMessage()], 500);
        }
    }
    
    private function getAnalysisResponse($file, string $apiKey)
    {
        if (strtolower($file->getClientOriginalExtension()) === 'pdf') {
            $fileData = base64_encode(file_get_contents($file->getPathname()));
            
            return Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => "Analyze this legal document and extract the following details: title (judul), date (tanggal), first party (pihak), second party (pihak2), agreement type (perjanjian), and description (deskripsi). Also generate an HTML summary with key points. Return the results as JSON with these exact field names: judul, tanggal, pihak, pihak2, perjanjian, deskripsi, htmlSummary."
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => 'application/pdf',
                                    'data' => $fileData
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topP' => 0.95,
                    'topK' => 40,
                    'maxOutputTokens' => 4096,
                ],
            ]);
        } else {
            $content = '';
            
            if (strtolower($file->getClientOriginalExtension()) === 'docx') {
                $content = $this->extractTextFromWord($file);
            } else {
                $content = file_get_contents($file->getPathname());
            }
            
            return Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => "Analyze the following legal document and extract these details: 
                                judul (title), 
                                tanggal (date), 
                                pihak (first party), 
                                pihak2 (second party), 
                                perjanjian (agreement type), 
                                deskripsi (description).
                                
                                Also generate an HTML summary with key points highlighted and properly formatted with HTML tags.
                                
                                Return as JSON format with these exact field names: judul, tanggal, pihak, pihak2, perjanjian, deskripsi, htmlSummary.
                                
                                Document content: {$content}"]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.2,
                    'topP' => 0.95,
                    'topK' => 40,
                    'maxOutputTokens' => 4096,
                ],
            ]);
        }
    }
    
    private function parseAnalysisResponse(string $analysisText): array
    {
        $analysis = null;
        
        if (preg_match('/```json\s*(.*?)\s*```/s', $analysisText, $matches)) {
            $jsonContent = $matches[1];
            $analysis = json_decode($jsonContent, true);
        } else {
            $analysis = json_decode($analysisText, true);
        }
        
        if (!$analysis || !is_array($analysis)) {
            $analysis = $this->extractKeyValuePairs($analysisText);
        }
        
        if (!$analysis || !is_array($analysis)) {
            $analysis = [
                'judul' => 'Unknown Document',
                'tanggal' => date('Y-m-d'),
                'pihak' => 'Unknown Party 1',
                'pihak2' => 'Unknown Party 2',
                'perjanjian' => 'Unknown Agreement Type',
                'deskripsi' => 'Unable to extract description',
                'htmlSummary' => '<p>No summary could be generated for this document.</p>'
            ];
        }
        
        if (!isset($analysis['htmlSummary'])) {
            $analysis['htmlSummary'] = '<p>' . (isset($analysis['deskripsi']) ? $analysis['deskripsi'] : 'No summary available') . '</p>';
        }
        
        return $analysis;
    }
    
    private function saveDocument(Request $request, array $analysis, string $formattedDate, string $analysisText, string $filePath): Document
    {
        $document = Document::create([
            'user_id' => $request->user()->id,
            'type' => 'analyzed',
            'judul' => $analysis['judul'] ?? 'Unknown Document',
            'perjanjian' => $analysis['perjanjian'] ?? 'Unknown Agreement Type',
            'pihak1' => $analysis['pihak'] ?? 'Unknown Party 1',
            'pihak2' => $analysis['pihak2'] ?? 'Unknown Party 2',
            'deskripsi' => $analysis['deskripsi'] ?? 'Unable to extract description',
            'tanggal' => $formattedDate,
            'content' => $analysisText,
            'file_path' => $filePath,
        ]);
        
        Activity::create([
            'user_id' => $request->user()->id,
            'type' => 'analyzer',
            'title' => $analysis['judul'] ?? 'Document Analysis',
            'document_id' => $document->id,
        ]);
        
        return $document;
    }
}

