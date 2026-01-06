<?php

namespace App\Services\LLM;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider
{
    protected string $endpoint =
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    private const TYPE_DESCRIPTIONS = [
        'mcq_single' => 'MCQ (single correct answer)',
        'mcq_multiple' => 'MCQ (multiple correct answers)',
        'fill_blanks' => 'Fill in the blanks',
        'one_word' => 'One word answer',
        'one_liner' => 'One line answer',
        'descriptive' => 'Descriptive long answer',
    ];

    /**
     * @param string $context
     * @param array $questionTypes
     * @return array
     */
    public function generateQuestions(string $context, array $questionTypes): array
    {
        $prompt = $this->buildPrompt($context, $questionTypes);

        $response = Http::withQueryParameters([
            'key' => config('services.gemini.key'),
        ])->post($this->endpoint, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
        ]);

        if (!$response->successful()) {
            Log::error('Gemini API error', ['response' => $response->json()]);
            throw new \Exception('Gemini API failed');
        }

        return $this->parseResponse($response->json());
    }

    /**
     * Build a strict prompt enforcing schemas per type
     */
    private function buildPrompt(string $context, array $questionTypes): string
    {
        $typesList = array_values(array_intersect(
            $questionTypes,
            array_keys(self::TYPE_DESCRIPTIONS)
        ));

        $typesHumanReadable = implode("\n", array_map(
            fn ($t) => "- " . self::TYPE_DESCRIPTIONS[$t],
            $typesList
        ));

        return <<<PROMPT
You are an expert educator and assessment designer.

Generate questions STRICTLY based on the selected types below:
{$typesHumanReadable}

IMPORTANT RULES:
- Generate 2–3 questions PER selected type
- Do NOT mix question types
- Questions must be based ONLY on the given context
- Answers must be accurate and concise
- Do NOT add explanations outside the answer field
- Return ONLY a valid JSON array (no markdown, no comments)

SCHEMAS (FOLLOW EXACTLY):

For MCQ (single correct answer):
{
  "type": "mcq_single",
  "question": "...",
  "options": ["...", "...", "...", "..."],
  "correct_option_index": 0,
  "answer": "..."
}

For MCQ (multiple correct answers):
{
  "type": "mcq_multiple",
  "question": "...",
  "options": ["...", "...", "...", "..."],
  "correct_option_indexes": [0, 2],
  "answer": "..."
}

For Fill in the Blanks:
{
  "type": "fill_blanks",
  "question": "... ____ ...",
  "answer": "..."
}

For One Word:
{
  "type": "one_word",
  "question": "...",
  "answer": "..."
}

For One Liner:
{
  "type": "one_liner",
  "question": "...",
  "answer": "..."
}

For Descriptive:
{
  "type": "descriptive",
  "question": "...",
  "answer": "..."
}

Context:
{$context}
PROMPT;
    }

    /**
     * Parse and validate Gemini response
     */
    private function parseResponse(array $json): array
    {
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Remove ```json fences if Gemini adds them
        $text = preg_replace('/^```json\s*|\s*```$/', '', trim($text));

        $decoded = json_decode($text, true);

        if (!is_array($decoded)) {
            Log::error('Invalid Gemini response format', ['raw_text' => $text]);
            throw new \Exception('Invalid Gemini response format');
        }

        // Light sanity filter (keep only valid-looking items)
        return array_values(array_filter($decoded, function ($q) {
            return isset($q['type'], $q['question'], $q['answer']);
        }));
    }
}
