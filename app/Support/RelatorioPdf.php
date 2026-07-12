<?php

namespace App\Support;

use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Helper comum para gerar PDFs de relatorios via Browsershot/Chromium
 * (configurado no docker/php/Dockerfile).
 */
class RelatorioPdf
{
    public static function view(string $view, array $data = []): PdfBuilder
    {
        return Pdf::view($view, $data)
            ->withBrowsershot(function (Browsershot $browsershot) {
                $browsershot->setChromePath('/usr/bin/chromium')->noSandbox();
            });
    }
}
