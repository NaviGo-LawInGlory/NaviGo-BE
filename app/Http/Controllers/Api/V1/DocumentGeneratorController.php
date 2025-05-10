<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Activity;
use App\Traits\DocumentUtilityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;

class DocumentGeneratorController extends Controller
{
    use DocumentUtilityTrait;
    
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'perjanjian' => 'required|string|max:255',
            'pihak1' => 'required|string|max:255',
            'pihak2' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'tanggal' => 'required|date',
            'documentType' => 'sometimes|string|max:100',
        ]);

        $optionalFields = [
            'jabatan1' => 'sometimes|string|max:255',
            'jabatan2' => 'sometimes|string|max:255',
            'durasi' => 'sometimes|string|max:255',
            'tempatPenandatanganan' => 'sometimes|string|max:255',
            'nilai' => 'sometimes|string|max:255',
            'alamat' => 'sometimes|string|max:1000',
            'penyelesaianSengketa' => 'sometimes|string|max:255',
            'npwp' => 'sometimes|string|max:50',
            'hukumYangBerlaku' => 'sometimes|string|max:255',
        ];

        $request->validate($optionalFields);

        try {
            $apiKey = config('services.gemini.api_key');
            
            $prompt = $this->buildGenerationPrompt($request, $validated);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'topP' => 0.95,
                    'topK' => 40,
                    'maxOutputTokens' => 4096,
                ],
            ]);

            if ($response->failed()) {
                throw new \Exception('Gemini API error: ' . $response->body());
            }

            $responseData = $response->json();
            $content = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
            if (empty($content)) {
                throw new \Exception('Failed to generate document content');
            }

            $content = $this->cleanHtmlContent($content);
            
            if (!str_contains($content, '<!DOCTYPE html>') && !str_contains($content, '<html')) {
                $content = $this->wrapInHtmlDocument($content, $validated['judul']);
            }
            
            $document = $this->saveDocument($request, $validated, $content);
            
            return response()->json([
                'id' => $document->id,
                'content' => $content,
                'download_url' => url('/api/v1/documents/' . $document->id . '/download'),
                'html_url' => url('/api/v1/documents/' . $document->id . '/download'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate document: ' . $e->getMessage()], 500);
        }
    }
    
    private function buildGenerationPrompt(Request $request, array $validated): string
    {
        $prompt = "Buat dokumen perjanjian dengan format HTML dengan detail berikut:\n";
        
        $documentType = $request->input('documentType', 'default');
        $prompt .= "Tipe Dokumen: {$documentType}\n";
        
        $prompt .= "Judul: {$validated['judul']}\n";
        $prompt .= "Jenis Perjanjian: {$validated['perjanjian']}\n";
        $prompt .= "Pihak Pertama: {$validated['pihak1']}\n";
        $prompt .= "Pihak Kedua: {$validated['pihak2']}\n";
        $prompt .= "Deskripsi: {$validated['deskripsi']}\n";
        $prompt .= "Tanggal: {$validated['tanggal']}\n";
        
        $optionalFields = [
            'jabatan1' => 'Jabatan Pihak Pertama',
            'jabatan2' => 'Jabatan Pihak Kedua',
            'durasi' => 'Durasi', 
            'tempatPenandatanganan' => 'Tempat Penandatanganan',
            'nilai' => 'Nilai',
            'alamat' => 'Alamat',
            'penyelesaianSengketa' => 'Penyelesaian Sengketa',
            'npwp' => 'NPWP',
            'hukumYangBerlaku' => 'Hukum yang Berlaku',
        ];
        
        foreach ($optionalFields as $field => $label) {
            if ($request->has($field)) {
                $prompt .= "{$label}: {$request->input($field)}\n";
            }
        }
        
        $prompt .= $this->getDocumentTypeSpecificInstructions($documentType);
        
        $prompt .= "\nPenting: Jangan berikan output dalam format markdown atau dengan pembatas kode (code blocks). Berikan langsung HTML murni tanpa ``` di awal atau akhir.";
        
        return $prompt;
    }
    
    private function getDocumentTypeSpecificInstructions(string $documentType): string
    {
        $instructions = [
            'PERJANJIAN_KERJASAMA' => "\nBuat dokumen perjanjian kerjasama formal dalam format HTML lengkap yang mencakup ruang lingkup kerjasama, 
                hak dan kewajiban para pihak, jangka waktu, dan ketentuan penutup. Format dengan tag HTML yang sesuai (<h1>, <p>, <div>, dll) dan CSS styling 
                agar dokumen terlihat profesional saat dibuka di browser. Berikan langsung dalam format dokumen HTML lengkap dengan <html>, <head>, dan <body> tags, TANPA menggunakan markdown code block (jangan gunakan ```html atau ```).",
                
            'PERJANJIAN_JUAL_BELI' => "\nBuat dokumen perjanjian jual beli dalam format HTML lengkap yang mencakup objek transaksi, 
                harga dan pembayaran, penyerahan barang, dan jaminan-jaminan yang diberikan. Format dengan tag HTML yang sesuai dan CSS styling
                agar dokumen terlihat profesional saat dibuka di browser. Berikan langsung dalam format dokumen HTML lengkap dengan <html>, <head>, dan <body> tags, TANPA menggunakan markdown code block (jangan gunakan ```html atau ```).",
                
            'PERJANJIAN_SEWA' => "\nBuat dokumen perjanjian sewa dalam format HTML lengkap yang mencakup objek sewa, 
                jangka waktu, biaya sewa, metode pembayaran, dan ketentuan penggunaan. Format dengan tag HTML yang sesuai dan CSS styling
                agar dokumen terlihat profesional saat dibuka di browser. Berikan langsung dalam format dokumen HTML lengkap dengan <html>, <head>, dan <body> tags, TANPA menggunakan markdown code block (jangan gunakan ```html atau ```).",
                
            'SURAT_KUASA' => "\nBuat surat kuasa formal dalam format HTML lengkap yang mencakup identitas pemberi dan penerima kuasa, 
                hal-hal yang dikuasakan, dan jangka waktu pemberian kuasa. Format dengan tag HTML yang sesuai dan CSS styling
                agar dokumen terlihat profesional saat dibuka di browser. Berikan langsung dalam format dokumen HTML lengkap dengan <html>, <head>, dan <body> tags, TANPA menggunakan markdown code block (jangan gunakan ```html atau ```).",
                
            'PERJANJIAN_KERAHASIAAN' => "\nBuat perjanjian kerahasiaan (NDA) dalam format HTML lengkap yang mencakup definisi informasi rahasia, 
                kewajiban menjaga kerahasiaan, pengecualian, dan sanksi pelanggaran. Format dengan tag HTML yang sesuai dan CSS styling
                agar dokumen terlihat profesional saat dibuka di browser. Berikan langsung dalam format dokumen HTML lengkap dengan <html>, <head>, dan <body> tags, TANPA menggunakan markdown code block (jangan gunakan ```html atau ```).",
        ];
        
        return $instructions[$documentType] ?? "\nBerikan format dokumen resmi dalam bahasa Indonesia yang lengkap dengan format HTML yang baik dan CSS styling
                agar dokumen terlihat profesional. Berikan langsung dalam format dokumen HTML lengkap dengan <html>, <head>, dan <body> tags, TANPA menggunakan markdown code block (jangan gunakan ```html atau ```).";
    }

    private function saveDocument(Request $request, array $validated, string $content): Document
    {
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
        
        $fileName = Str::slug($validated['judul']) . '-' . time() . '.html';
        $filePath = 'documents/' . $request->user()->id . '/' . $fileName;
        
        $directory = dirname(Storage::path($filePath));
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        Storage::put($filePath, $content);
        
        $document->update(['file_path' => $filePath]);
        
        Activity::create([
            'user_id' => $request->user()->id,
            'type' => 'generator',
            'title' => $validated['judul'],
            'document_id' => $document->id,
        ]);

        return $document;
    }
}

