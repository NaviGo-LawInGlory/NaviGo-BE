<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DocumentController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $controller = app(DocumentGeneratorController::class);
        return $controller->generate($request);
    }
    
    public function analyze(Request $request): JsonResponse
    {
        $controller = app(DocumentAnalyzerController::class);
        return $controller->analyze($request);
    }
    
    public function download(Request $request, $id)
    {
        $controller = app(DocumentContentController::class);
        return $controller->download($request, $id);
    }
    
    public function getContent(Request $request, $id): JsonResponse
    {
        $controller = app(DocumentContentController::class);
        return $controller->getContent($request, $id);
    }
}

