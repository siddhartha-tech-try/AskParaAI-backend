<?php


namespace App\Services\LLM;

class LLMManager
{
    public static function driver()
    {
        return match (config('llm.default')) {
            'gemini' => new GeminiProvider(),
            'gpt'    => new OpenAIProvider(),
            default  => new MockProvider(),
        };
    }
}
