<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at,
            'account_type' => $user->account_type,
            'location' => $user->location,
            'phone' => $user->phone,
            'bio' => $user->bio,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        
        $user->update([
            'name' => $request->input('name'),
            'location' => $request->input('location'),
            'phone' => $request->input('phone'),
            'bio' => $request->input('bio'),
        ]);
        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'created_at' => $user->created_at,
            'account_type' => $user->account_type,
            'location' => $user->location,
            'phone' => $user->phone,
            'bio' => $user->bio,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $documentsGenerated = Document::where('user_id', $user->id)
            ->where('type', 'generated')
            ->count();
            
        $documentsAnalyzed = Document::where('user_id', $user->id)
            ->where('type', 'analyzed')
            ->count();
            
        $recentActivities = Activity::where('user_id', $user->id)
            ->latest()
            ->limit(5)
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
            
        return response()->json([
            'documents_generated' => $documentsGenerated,
            'documents_analyzed' => $documentsAnalyzed,
            'recent_activities' => $recentActivities,
        ]);
    }
}
