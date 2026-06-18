<?php

namespace App\Services\StaffPick;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

/**
 * Builds a single printable guide (cover page + every topic, in manifest order) for a
 * help role and renders it to PDF with dompdf — a pure-PHP library, so it works in the
 * Railway container with no external binary. Shared by the help center's
 * "Download as PDF" action and the staffpick:export-docs command.
 */
class HelpPdfExporter
{
    public function __construct(private HelpService $help) {}

    public function filename(string $role): string
    {
        return $role.'-guide.pdf';
    }

    /**
     * Render the role's guide to PDF binary.
     */
    public function pdf(string $role, ?Carbon $generatedAt = null): string
    {
        return Pdf::loadHTML($this->html($role, $generatedAt))
            ->setPaper('letter')
            ->output();
    }

    /**
     * Render and write to disk, returning the absolute path. Defaults to
     * storage/app/docs/{role}-guide.pdf.
     */
    public function save(string $role, ?string $path = null, ?Carbon $generatedAt = null): string
    {
        $path ??= storage_path('app/docs/'.$this->filename($role));

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $this->pdf($role, $generatedAt));

        return $path;
    }

    /**
     * The full HTML document: cover page followed by each topic.
     */
    public function html(string $role, ?Carbon $generatedAt = null): string
    {
        $generatedAt ??= Carbon::now();
        $label = $this->help->roleLabel($role);
        $date = $generatedAt->format('F j, Y');

        $cover = <<<HTML
            <div class="cover">
                <div class="brand">StaffPick</div>
                <div class="guide">{$this->e($label)}</div>
                <div class="org">First Class Therapy Solutions</div>
                <div class="date">Generated {$this->e($date)}</div>
            </div>
            <div class="page-break"></div>
            HTML;

        $body = '';

        foreach ($this->help->topics($role) as $topic) {
            $markdown = $this->help->rawMarkdown($topic['file']);

            if ($markdown === null) {
                continue;
            }

            $body .= '<section class="topic">'.$this->help->toHtml($markdown).'</section>';
        }

        return $this->document($cover.$body);
    }

    private function document(string $inner): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
            <meta charset="utf-8">
            <style>
                * { font-family: DejaVu Sans, sans-serif; }
                body { color: #1f2937; font-size: 12px; line-height: 1.5; }
                .cover { text-align: center; padding-top: 220px; }
                .cover .brand { font-size: 52px; font-weight: bold; color: #1d4ed8; letter-spacing: -1px; }
                .cover .guide { font-size: 26px; margin-top: 16px; color: #111827; }
                .cover .org { font-size: 15px; margin-top: 40px; color: #374151; }
                .cover .date { font-size: 12px; margin-top: 8px; color: #6b7280; }
                .page-break { page-break-after: always; }
                .topic { page-break-before: always; }
                h1 { font-size: 22px; color: #111827; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; }
                h2 { font-size: 16px; color: #1f2937; margin-top: 18px; }
                h3 { font-size: 13px; color: #374151; margin-top: 14px; }
                p, li { font-size: 12px; }
                code { background: #f3f4f6; padding: 1px 4px; border-radius: 3px; font-family: DejaVu Sans Mono, monospace; }
                blockquote { border-left: 3px solid #93c5fd; margin-left: 0; padding-left: 12px; color: #4b5563; }
                table { border-collapse: collapse; width: 100%; margin: 10px 0; }
                th, td { border: 1px solid #e5e7eb; padding: 5px 8px; text-align: left; font-size: 11px; }
                th { background: #f9fafb; }
                a { color: #1d4ed8; text-decoration: none; }
            </style>
            </head>
            <body>{$inner}</body>
            </html>
            HTML;
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
