<?php

namespace App\Services\Input;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class InputResolver
{
    public function __construct(private readonly FileTextExtractor $fileTextExtractor)
    {
    }

    public function resolve(Request $request): string
    {
        if ($request->filled('context')) {
            return $request->context;
        }

        if ($request->filled('url')) {
            return $this->extractFromUrl($request->url);
        }

        if ($request->hasFile('file')) {
            return $this->extractFromFile($request->file('file'));
        }

        throw new \Exception('No valid input provided');
    }

    protected function extractFromUrl(string $url): string
    {
        $html = Http::timeout(30)->get($url)->body();

        return trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? strip_tags($html));
    }

    protected function extractFromFile($file): string
    {
        return $this->fileTextExtractor->extract($file);
    }
}
