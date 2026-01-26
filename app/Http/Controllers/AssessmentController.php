<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssessmentRequest;
use App\Services\SaveAssessmentService;
use Illuminate\Support\Facades\Log;

class AssessmentController extends Controller
{
    public function store(StoreAssessmentRequest $request, SaveAssessmentService $service) {
        $assessment = $service->execute($request->validated());
        
        return response()->json([
            'message' => 'Assessment saved successfully',
            'assessment_id' => $assessment->id
        ], 201);
    }
}
