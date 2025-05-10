<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lawyer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LawyerController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = Lawyer::query();
        
        if ($request->filled('query')) {
            $searchTerm = $request->query('query');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('location', 'like', "%{$searchTerm}%");
            });
        }
        
        if ($request->filled('specialization')) {
            $specializations = $request->query('specialization');
            if (!is_array($specializations)) {
                $specializations = [$specializations];
            }
            
            $query->where(function ($q) use ($specializations) {
                foreach ($specializations as $spec) {
                    $q->orWhereJsonContains('specialization', $spec);
                }
            });
        }
        
        if ($request->filled('location')) {
            $query->where('location', 'like', "%{$request->query('location')}%");
        }
        
        if ($request->filled('rating')) {
            $query->where('rating', '>=', $request->query('rating'));
        }
        
        $sortBy = $request->query('sort_by', 'rating');
        $sortOrder = $request->query('sort_order', 'desc');
        
        if (in_array($sortBy, ['rating', 'experience_years', 'name'])) {
            $query->orderBy($sortBy, $sortOrder);
        }
        
        $perPage = $request->query('limit', 10);
        $page = $request->query('page', 1);
        
        $lawyers = $query->paginate($perPage, ['*'], 'page', $page);
        
        $formattedLawyers = $lawyers->map(function ($lawyer) {
            return [
                'id' => $lawyer->id,
                'name' => $lawyer->name,
                'specialization' => $lawyer->specialization,
                'location' => $lawyer->location,
                'rating' => $lawyer->rating,
                'experience_years' => $lawyer->experience_years,
                'image_url' => $lawyer->image_url,
                'contact_info' => [
                    'email' => $lawyer->email,
                    'phone' => $lawyer->phone,
                ],
                'available' => $lawyer->available,
            ];
        });
        
        return response()->json([
            'data' => $formattedLawyers,
            'total' => $lawyers->total(),
        ]);
    }
}

