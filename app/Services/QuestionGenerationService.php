<?php

namespace App\Services;

use App\Services\Input\InputResolver;
use App\Services\Questions\QuestionTypePromptFactory;
use App\Services\LLM\LLMManager;

class QuestionGenerationService
{
    public function generate($request): array
    {
        $context = app(InputResolver::class)->resolve($request);

        $questionTypesPrompt =
            QuestionTypePromptFactory::build($request->questionTypes);

        return LLMManager::driver()->generateQuestions($context, $request->questionTypes);

    }
}
