<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Google\Cloud\AIPlatform\V1\EndpointServiceClient;
use Google\Cloud\AIPlatform\V1\PredictResponse;
use Google\Cloud\AIPlatform\V1\DeployedModel;
use Google\Cloud\AIPlatform\V1\ModelServiceClient;
use Google\Protobuf\Value;
use Google\Protobuf\ListValue;
use Google\ApiCore\ApiException;
use Google\GenerativeAI\GenerativeModel;
use Google\GenerativeAI\Part;

class GeminiController extends Controller
{
    public function generateContent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text' => ['required_without:file', 'string'],
            'file' => [
                'required_without:text',
                'file',
                'mimes:jpg,jpeg,png,pdf,txt,docx',
                'max:10240'
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $apiKey = config('services.gemini.api_key');
            if (!$apiKey) {
                return response()->json(['message' => 'Gemini API key is not configured.'], 500);
            }

            $model = new GenerativeModel('gemini-pro-vision', $apiKey);

            $parts = [];

            if ($request->has('text')) {
                $parts[] = Part::text($request->input('text'));
            }

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $mimeType = $file->getMimeType();
                $fileData = base64_encode($file->get());

                $parts[] = Part::data($mimeType, $fileData);
            }

            $response = $model->generateContent($parts);

            return response()->json([
                'generated_text' => $response->text()
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate content: ' . $e->getMessage()], 500);
        }
    }
}

