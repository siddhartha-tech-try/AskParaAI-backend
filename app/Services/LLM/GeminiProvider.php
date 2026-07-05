<?php

namespace App\Services\LLM;

use Illuminate\Http\UploadedFile;
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

    public function generateQuestions(string $context, array $questionTypes): array
    {
        $response = $this->makeRequest([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $this->buildPrompt($questionTypes, $context)],
                    ],
                ],
            ],
        ]);

        return $this->parseResponse($response->json());
    }

    public function generateQuestionsFromUploadedFile(UploadedFile $file, array $questionTypes): array
    {
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $base64File = base64_encode($file->get());

        $response = $this->makeRequest([
            'contents' => [
                [
                    'parts' => [
                        ['text' => $this->buildFilePrompt($questionTypes, $file->getClientOriginalName())],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64File,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        return $this->parseResponse($response->json());
    }

    private function makeRequest(array $payload)
    {
        $response = Http::timeout(120)
            ->withQueryParameters([
                'key' => config('services.gemini.key'),
            ])
            ->post($this->endpoint, $payload);

        if (! $response->successful()) {
            Log::error('Gemini API error', ['response' => $response->json() ?: $response->body()]);
            throw new \Exception('Gemini API failed');
        }

        return $response;
    }

    private function buildPrompt(array $questionTypes, string $context): string
    {
        $typesHumanReadable = $this->buildTypesList($questionTypes);

        return <<<PROMPT
You are an expert educator and assessment designer.

Generate questions STRICTLY based on the selected types below:
{$typesHumanReadable}

IMPORTANT RULES:
- Generate 2-3 questions PER selected type
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

    private function buildFilePrompt(array $questionTypes, string $fileName): string
    {
        $typesHumanReadable = $this->buildTypesList($questionTypes);

        return <<<PROMPT
You are an expert educator and assessment designer.

The user uploaded a file named "{$fileName}".
Read the file content carefully and generate questions STRICTLY based on the file.

Selected question types:
{$typesHumanReadable}

IMPORTANT RULES:
- Generate 2-3 questions PER selected type
- Do NOT mix question types
- Use ONLY the uploaded file content
- If the file has little readable text, use only what is available and do not invent facts
- Return ONLY a valid JSON array (no markdown, no comments)

Use the exact same JSON schemas as instructed for question generation.
PROMPT;
    }

    private function buildTypesList(array $questionTypes): string
    {
        $typesList = array_values(array_intersect(
            $questionTypes,
            array_keys(self::TYPE_DESCRIPTIONS)
        ));

        return implode("\n", array_map(
            fn ($type) => '- ' . self::TYPE_DESCRIPTIONS[$type],
            $typesList
        ));
    }

    private function parseResponse(array $json): array
    {
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = preg_replace('/^```json\s*|\s*```$/', '', trim($text));

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            Log::error('Invalid Gemini response format', ['raw_text' => $text]);
            throw new \Exception('Invalid Gemini response format');
        }

        return array_values(array_filter($decoded, function ($question) {
            return isset($question['type'], $question['question'], $question['answer']);
        }));
    }
}
