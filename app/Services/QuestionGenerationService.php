<?php

namespace App\Services;

use App\Services\Input\InputResolver;
use App\Services\Input\UnsupportedFileExtractionException;
use App\Services\LLM\GeminiProvider;
use App\Services\LLM\LLMManager;

class QuestionGenerationService
{
    public function generate($request): array
    {
        $driver = LLMManager::driver();

        if ($request->hasFile('file')) {
            try {
                $context = app(InputResolver::class)->resolve($request);

                return $driver->generateQuestions($context, $request->questionTypes);
            } catch (UnsupportedFileExtractionException $exception) {
                if ($driver instanceof GeminiProvider) {
                    return $driver->generateQuestionsFromUploadedFile(
                        $request->file('file'),
                        $request->questionTypes
                    );
                }

                throw $exception;
            }
        }

        $context = app(InputResolver::class)->resolve($request);

        return $driver->generateQuestions($context, $request->questionTypes);
    }
}
