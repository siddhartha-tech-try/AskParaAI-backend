<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\QuestionGenerationService;

class QuestionController extends Controller
{
    public function generate(Request $request, QuestionGenerationService $service)
    {
        $request->validate([
            'context' => 'required|string|min:50',
        ]);

        try {
            $questions = $service->generateFromContext($request->context);
            Log::info($questions);
    
            // TEMP: mock response
            return response()->json([
                'success' => true,
                'data' => [
                    'questions' => $questions
                ]
            ]);
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'AI generation failed'
            ], 500);
        }

    }

}
