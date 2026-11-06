<?php
// includes/pdf.php
// Minimal dependency-free PDF writer for generated documents (admission letters, certificates).
// Uses the PDF base-14 Helvetica family with WinAnsi encoding, so no font files are embedded.
// Coordinates are in points from the TOP-LEFT of the page; text y is the baseline.

class SimplePdf
{
    // Advance widths (1/1000 em) for ASCII 32..126, from the standard Helvetica AFM metrics.
    private const W_REGULAR = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
    private const W_BOLD = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];
    private const FONTS = ['regular' => ['F1', 'Helvetica'], 'bold' => ['F2', 'Helvetica-Bold'], 'italic' => ['F3', 'Helvetica-Oblique']];

    public float $width;
    public float $height;
    private array $pages = [];
    private string $font = 'regular';
    private float $size = 11;
    private string $title;

    public function __construct(string $orientation = 'P', string $title = 'Document')
    {
        [$this->width, $this->height] = $orientation === 'L' ? [841.89, 595.28] : [595.28, 841.89];
        $this->title = $title;
    }

    public function addPage(): void
    {
        $this->pages[] = '';
    }

    private function out(string $op): void
    {
        $this->pages[count($this->pages) - 1] .= $op . "\n";
    }

    public function setFont(string $style, float $size): void
    {
        $this->font = isset(self::FONTS[$style]) ? $style : 'regular';
        $this->size = $size;
    }

    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#');
        return sprintf('%.3F %.3F %.3F', hexdec(substr($hex, 0, 2)) / 255, hexdec(substr($hex, 2, 2)) / 255, hexdec(substr($hex, 4, 2)) / 255);
    }

    public function setTextColor(string $hex): void
    {
        $this->out(self::rgb($hex) . ' rg');
    }

    public function setFillColor(string $hex): void
    {
        $this->out(self::rgb($hex) . ' rg');
    }

    public function setDrawColor(string $hex): void
    {
        $this->out(self::rgb($hex) . ' RG');
    }

    /** UTF-8 (possibly HTML-escaped) text to a WinAnsi byte string. */
    public static function encode(string $s): string
    {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace(['₦', "\u{2013}", "\u{2014}", "\u{2019}", "\u{2018}", "\u{201C}", "\u{201D}"], ['NGN ', '-', '-', "'", "'", '"', '"'], $s);
        $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        return $out === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $out;
    }

    public function textWidth(string $s, ?string $style = null, ?float $size = null): float
    {
        $table = ($style ?? $this->font) === 'bold' ? self::W_BOLD : self::W_REGULAR;
        $bytes = self::encode($s);
        $w = 0;
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $c = ord($bytes[$i]);
            $w += ($c >= 32 && $c <= 126) ? $table[$c - 32] : 556;
        }
        return $w * ($size ?? $this->size) / 1000;
    }

    public function text(float $x, float $y, string $s): void
    {
        $bytes = strtr(self::encode($s), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
        $f = self::FONTS[$this->font][0];
        $this->out(sprintf('BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', $f, $this->size, $x, $this->height - $y, $bytes));
    }

    /** Text aligned inside [x, x+w]: 'L', 'C' or 'R'. */
    public function textAligned(float $x, float $y, float $w, string $s, string $align = 'L'): void
    {
        $tw = $this->textWidth($s);
        $dx = $align === 'C' ? ($w - $tw) / 2 : ($align === 'R' ? $w - $tw : 0);
        $this->text($x + $dx, $y, $s);
    }

    /** Word-wrap $s to lines no wider than $w. */
    public function wrap(string $s, float $w): array
    {
        $lines = [];
        foreach (preg_split("/\r?\n/", $s) as $para) {
            $line = '';
            foreach (preg_split('/\s+/', trim($para)) as $word) {
                $try = $line === '' ? $word : "$line $word";
                if ($line !== '' && $this->textWidth($try) > $w) {
                    $lines[] = $line;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /** Wrapped paragraph; returns the y after the last line. */
    public function paragraph(float $x, float $y, float $w, string $s, float $lineHeight, string $align = 'L'): float
    {
        foreach ($this->wrap($s, $w) as $line) {
            $this->textAligned($x, $y, $w, $line, $align);
            $y += $lineHeight;
        }
        return $y;
    }

    /** Rectangle; $style: 'F' fill, 'S' stroke, 'B' both. */
    public function rect(float $x, float $y, float $w, float $h, string $style = 'S', float $lineWidth = 1): void
    {
        $op = ['F' => 'f', 'S' => 'S', 'B' => 'B'][$style] ?? 'S';
        $this->out(sprintf('%.2F w %.2F %.2F %.2F %.2F re %s', $lineWidth, $x, $this->height - $y - $h, $w, $h, $op));
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $lineWidth = 1): void
    {
        $this->out(sprintf('%.2F w %.2F %.2F m %.2F %.2F l S', $lineWidth, $x1, $this->height - $y1, $x2, $this->height - $y2));
    }

    public function output(): string
    {
        if (!$this->pages) {
            $this->addPage();
        }
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $fontObj = [];
        $n = 3;
        foreach (self::FONTS as [$ref, $base]) {
            $fontObj[$ref] = $n;
            $objects[$n++] = "<< /Type /Font /Subtype /Type1 /BaseFont /$base /Encoding /WinAnsiEncoding >>";
        }
        $fontRes = '';
        foreach ($fontObj as $ref => $id) {
            $fontRes .= "/$ref $id 0 R ";
        }
        $kids = [];
        foreach ($this->pages as $content) {
            $pageId = $n++;
            $contentId = $n++;
            $kids[] = "$pageId 0 R";
            $objects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << %s>> >> /Contents %d 0 R >>', $this->width, $this->height, $fontRes, $contentId);
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $infoId = $n++;
        $title = strtr(self::encode($this->title), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
        $objects[$infoId] = "<< /Title ($title) /Producer (Royal Solutions ERP) /CreationDate (D:" . date('YmdHis') . ") >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($pdf);
        $count = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size $count /Root 1 0 R /Info $infoId 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}

/** Money for PDFs (the Naira sign is not in the base-14 fonts). */
function pdf_money($amount)
{
    return 'NGN ' . number_format((float) $amount, 2);
}

/** Send PDF bytes to the browser as a download (or inline view). */
function send_pdf($bytes, $filename, $inline = false)
{
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: private, no-store');
    echo $bytes;
    exit;
}
