<?php

namespace App\Services\Input;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class FileTextExtractor
{
    public function extract(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'txt', 'md', 'markdown', 'csv', 'tsv', 'json', 'xml', 'html', 'htm', 'log' => $this->extractTextLikeFile($file, $extension),
            'docx' => $this->extractDocx($file),
            'odt' => $this->extractOdt($file),
            'pptx' => $this->extractPptx($file),
            'xlsx' => $this->extractXlsx($file),
            'rtf' => $this->extractRtf($file),
            'pdf' => $this->extractPdf($file),
            default => throw new UnsupportedFileExtractionException("Unsupported file type: {$extension}"),
        };
    }

    private function extractTextLikeFile(UploadedFile $file, string $extension): string
    {
        $content = $file->get();

        if (in_array($extension, ['html', 'htm', 'xml'], true)) {
            $content = strip_tags($content);
        }

        return $this->normalizeWhitespace($content);
    }

    private function extractDocx(UploadedFile $file): string
    {
        return $this->extractZipXmlText($file->getRealPath(), [
            'word/document.xml',
            'word/header1.xml',
            'word/header2.xml',
            'word/footer1.xml',
            'word/footer2.xml',
        ]);
    }

    private function extractOdt(UploadedFile $file): string
    {
        return $this->extractZipXmlText($file->getRealPath(), ['content.xml']);
    }

    private function extractPptx(UploadedFile $file): string
    {
        $zip = new ZipArchive();

        if ($zip->open($file->getRealPath()) !== true) {
            throw new UnsupportedFileExtractionException('Unable to open PPTX file');
        }

        $slides = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name !== false && str_starts_with($name, 'ppt/slides/slide') && str_ends_with($name, '.xml')) {
                $slides[] = $this->xmlToText($zip->getFromIndex($i) ?: '');
            }
        }

        $zip->close();

        $content = $this->normalizeWhitespace(implode("\n\n", $slides));

        if ($content === '') {
            throw new UnsupportedFileExtractionException('No readable text found in PPTX file');
        }

        return $content;
    }

    private function extractXlsx(UploadedFile $file): string
    {
        $zip = new ZipArchive();

        if ($zip->open($file->getRealPath()) !== true) {
            throw new UnsupportedFileExtractionException('Unable to open XLSX file');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetText = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name !== false && str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                $sheetText[] = $this->extractWorksheetText($zip->getFromIndex($i) ?: '', $sharedStrings);
            }
        }

        $zip->close();

        $content = $this->normalizeWhitespace(implode("\n", $sheetText));

        if ($content === '') {
            throw new UnsupportedFileExtractionException('No readable text found in XLSX file');
        }

        return $content;
    }

    private function extractRtf(UploadedFile $file): string
    {
        $content = $file->get();

        $content = preg_replace('/\\\\par[d]?/', "\n", $content) ?? $content;
        $content = preg_replace('/\\\\[a-z]+\d* ?/i', ' ', $content) ?? $content;
        $content = preg_replace('/[{}]/', ' ', $content) ?? $content;

        return $this->normalizeWhitespace($content);
    }

    private function extractPdf(UploadedFile $file): string
    {
        $content = $file->get();
        preg_match_all('/stream(.*?)endstream/s', $content, $matches);

        $chunks = [];

        foreach ($matches[1] ?? [] as $stream) {
            $stream = ltrim($stream, "\r\n");
            $decoded = @gzuncompress($stream);

            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }

            if ($decoded === false) {
                $decoded = $stream;
            }

            preg_match_all('/\((.*?)\)/s', $decoded, $textMatches);

            foreach ($textMatches[1] ?? [] as $text) {
                $chunks[] = $this->decodePdfText($text);
            }
        }

        $text = $this->normalizeWhitespace(implode(' ', $chunks));

        if ($text === '') {
            throw new UnsupportedFileExtractionException('Unable to extract text from PDF locally');
        }

        return $text;
    }

    private function extractZipXmlText(string $path, array $entries): string
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new UnsupportedFileExtractionException('Unable to open document archive');
        }

        $parts = [];

        foreach ($entries as $entry) {
            $content = $zip->getFromName($entry);

            if ($content !== false) {
                $parts[] = $this->xmlToText($content);
            }
        }

        $zip->close();

        $text = $this->normalizeWhitespace(implode("\n\n", $parts));

        if ($text === '') {
            throw new UnsupportedFileExtractionException('No readable text found in document');
        }

        return $text;
    }

    private function xmlToText(string $xml): string
    {
        $xml = str_replace(['</w:p>', '</a:p>', '</text:p>', '</row>', '</si>'], ["\n", "\n", "\n", "\n", "\n"], $xml);
        $xml = strip_tags($xml);

        return html_entity_decode($xml, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = @simplexml_load_string($xml);

        if ($document === false) {
            return [];
        }

        $strings = [];

        foreach ($document->si as $item) {
            $strings[] = trim((string) implode('', $item->xpath('.//t') ?: []));
        }

        return $strings;
    }

    private function extractWorksheetText(string $xml, array $sharedStrings): string
    {
        $document = @simplexml_load_string($xml);

        if ($document === false) {
            return '';
        }

        $document->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = $document->xpath('//main:sheetData/main:row/main:c') ?: [];

        $rows = [];
        $currentRow = [];

        foreach ($cells as $cell) {
            $valueNode = $cell->v ?? null;
            $value = $valueNode ? (string) $valueNode : '';

            if ((string) ($cell['t'] ?? '') === 's') {
                $value = $sharedStrings[(int) $value] ?? $value;
            }

            if ($value !== '') {
                $currentRow[] = $value;
            }

            if (count($currentRow) > 0 && str_ends_with((string) ($cell['r'] ?? ''), '1')) {
                $rows[] = implode(' | ', $currentRow);
                $currentRow = [];
            }
        }

        if ($currentRow !== []) {
            $rows[] = implode(' | ', $currentRow);
        }

        return implode("\n", $rows);
    }

    private function decodePdfText(string $text): string
    {
        $text = str_replace(['\\(', '\\)', '\\n', '\\r', '\\t'], ['(', ')', "\n", ' ', ' '], $text);
        $text = preg_replace('/\\\\\d{3}/', ' ', $text) ?? $text;

        return $text;
    }

    private function normalizeWhitespace(string $content): string
    {
        $content = preg_replace("/\r\n|\r/", "\n", $content) ?? $content;
        $content = preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;
        $content = preg_replace('/[ \t]+/', ' ', $content) ?? $content;

        return trim($content);
    }
}
