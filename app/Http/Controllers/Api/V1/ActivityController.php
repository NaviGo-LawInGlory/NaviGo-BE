<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $activities = Activity::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'title' => $activity->title,
                    'date' => $activity->created_at->toISOString(),
                    'document_id' => $activity->document_id,
                ];
            });
            
        return response()->json($activities);
    }
}
