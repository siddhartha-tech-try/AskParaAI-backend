<?php

namespace App\Services\LLM;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider
{
    protected string $endpoint =
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function generateQuestions(string $context): array
    {
        $prompt = $this->buildPrompt($context);
        $response = Http::withQueryParameters([
            'key' => config('services.gemini.key'),
        ])->post($this->endpoint, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ]);


        if (!$response->successful()) {
            throw new \Exception('Gemini API failed');
        }

        return $this->parseResponse($response->json());
    }

    private function buildPrompt(string $context): string
    {
        return <<<PROMPT
You are an expert educator.

From the context below, generate 5 high-quality questions with clear answers.

Rules:
- Keep questions factual and conceptual
- Answers must be concise
- Return ONLY valid JSON in this format:

[
  {
    "question": "...",
    "answer": "..."
  }
]

Context:
{$context}
PROMPT;
    }

    private function parseResponse(array $json): array
    {
        $text =
            $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
            
        $text = preg_replace('/^```json\s*|\s*```$/', '', trim($text));
        $decoded = json_decode($text, true);

        if (!is_array($decoded)) {
            throw new \Exception('Invalid Gemini response format');
        }

        return $decoded;
    }
}
