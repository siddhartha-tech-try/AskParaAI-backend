<?php


namespace App\Services\LLM;

class MockProvider
{
    public function generateQuestions(string $context): array
    {
        return [
            [
                'question' => 'What is the main idea of the given context?',
                'answer' => 'The main idea is derived from the provided text.'
            ],
            [
                'question' => 'Why is this topic important?',
                'answer' => 'It explains the significance of the subject discussed.'
            ],
        ];
    }
}
