<?php

namespace App\Services\AiAgent\Modules\Ingestion;

use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Trích text từ file PDF (pdftotext nếu có, fallback smalot/pdfparser).
 */
final class PdfParser
{
    public function extract(string $absolutePath): string
    {
        if ($absolutePath === '' || ! is_readable($absolutePath)) {
            return '';
        }
        if (function_exists('shell_exec')) {
            $cmd = 'pdftotext -layout '.escapeshellarg($absolutePath).' - 2>/dev/null';
            $out = @shell_exec($cmd);
            if (is_string($out) && mb_strlen(trim($out), 'UTF-8') > 8) {
                return trim($out);
            }
        }

        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($absolutePath);
            $out = $pdf->getText();
            if (is_string($out) && mb_strlen(trim($out), 'UTF-8') > 8) {
                return trim($out);
            }
        } catch (Throwable) {
            // PDF lỗi cấu trúc / mã hóa
        }

        return '';
    }
}
