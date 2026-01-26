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
            'questionTypes' => 'required|array|min:1',
            'questionTypes.*' => 'string',

            'context' => 'nullable|string|min:50',
            'url' => 'nullable|url',
            'file' => 'nullable|file|max:25600', // 25MB
        ]);

        try {
            $questions = $service->generate($request);

            return response()->json([
                'success' => true,
                'data' => [
                    'questions' => $questions
                ]
            ]);
        } catch (\Throwable $e) {
            logger()->error($e);

            return response()->json([
                'success' => false,
                'message' => 'Question generation failed'
            ], 500);
        }

    }

}
