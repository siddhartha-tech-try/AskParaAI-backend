<?php

namespace App\Services;

use App\Services\LLM\LLMManager;

class QuestionGenerationService
{
    public function generateFromContext(string $context): array
    {
        return LLMManager::driver()->generateQuestions($context);
    }
}
