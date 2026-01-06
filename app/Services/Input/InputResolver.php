<?php

namespace App\Services\Input;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class InputResolver
{
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
        $html = Http::get($url)->body();

        // TEMP SIMPLE EXTRACTION (improve later)
        return strip_tags($html);
    }

    protected function extractFromFile($file): string
    {
        $path = $file->store('uploads');

        // TEMP: only text files
        if ($file->getClientOriginalExtension() === 'txt') {
            return Storage::get($path);
        }

        throw new \Exception('Unsupported file type for now');
    }
}
