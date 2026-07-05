<?php

namespace App\Services\LLM;

class MockProvider
{
    public function generateQuestions(string $context, array $questionTypes = []): array
    {
        return [
            [
                'type' => $questionTypes[0] ?? 'one_liner',
                'question' => 'What is the main idea of the given context?',
                'answer' => 'The main idea is derived from the provided text.',
            ],
            [
                'type' => $questionTypes[1] ?? 'descriptive',
                'question' => 'Why is this topic important?',
                'answer' => 'It explains the significance of the subject discussed.',
            ],
        ];
    }
}
