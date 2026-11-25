<?php
declare(strict_types=1);

/**
 * Générateur de facture PDF autonome (aucune dépendance Composer).
 * Produit un PDF A4 valide avec polices standards Helvetica / Helvetica-Bold.
 * Les caractères UTF-8 sont convertis en Windows-1252 (accents français, €).
 */

if (!function_exists('iePdfText')) {
    function iePdfText(string $text): string
    {
        $text = (string)$text;

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252', $text);
            if ($converted === false) {
                $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
            }
            if ($converted !== false) {
                $text = $converted;
            } else {
                $text = preg_replace('/[^\x00-\x7F]/', '?', $text) ?? $text;
            }
        } else {
            $text = preg_replace('/[^\x00-\x7F]/', '?', $text) ?? $text;
        }

        // Échappement des caractères spéciaux PDF.
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
    }
}

final class InvoicePdf
{
    private float $pageWidth = 595.28;
    private float $pageHeight = 841.89;
    private float $margin = 38.0;

    /** @var string[] */
    private array $pages = [];
    private string $content = '';
    private float $y = 38.0;

    public function __construct()
    {
        $this->newPage();
    }

    private function newPage(): void
    {
        if ($this->content !== '') {
            $this->pages[] = $this->content;
        }
        $this->content = '';
        $this->y = $this->margin;
    }

    public function ensureSpace(float $height): void
    {
        if ($this->y + $height > $this->pageHeight - $this->margin - 18) {
            $this->newPage();
        }
    }

    /** @param array{0:float,1:float,2:float} $color */
    private function color(array $color): string
    {
        return sprintf('%.3f %.3f %.3f', $color[0], $color[1], $color[2]);
    }

    /**
     * @param array{0:float,1:float,2:float} $color
     */
    public function text(
        string $text,
        float $x,
        float $size = 10,
        bool $bold = false,
        array $color = [0.10, 0.10, 0.10],
        ?float $y = null
    ): float {
        $y = $y ?? $this->y;
        $py = $this->pageHeight - $y;
        $font = $bold ? '/F2' : '/F1';
        $this->content .= sprintf(
            "%s rg\nBT %s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
            $this->color($color),
            $font,
            $size,
            $x,
            $py,
            iePdfText($text)
        );
        return $y;
    }

    /** @param array{0:float,1:float,2:float} $fill */
    public function rect(float $x, float $yTop, float $width, float $height, array $fill): void
    {
        $py = $this->pageHeight - ($yTop + $height);
        $this->content .= sprintf(
            "%s rg\n%.2f %.2f %.2f %.2f re f\n",
            $this->color($fill),
            $x,
            $py,
            $width,
            $height
        );
    }

    /** @param array{0:float,1:float,2:float} $color */
    public function line(float $x1, float $y1, float $x2, float $y2, array $color = [0.80, 0.80, 0.80], float $width = 0.6): void
    {
        $this->content .= sprintf(
            "%s RG\n%.2f w\n%.2f %.2f m %.2f %.2f l S\n",
            $this->color($color),
            $width,
            $x1,
            $this->pageHeight - $y1,
            $x2,
            $this->pageHeight - $y2
        );
    }

    public function measure(string $text, float $size, bool $bold = false): float
    {
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        return (float)$length * $size * ($bold ? 0.55 : 0.50);
    }

    /** @return string[] */
    public function wrap(string $text, float $maxWidth, float $size, bool $bold = false): array
    {
        $text = trim((string)$text);
        if ($text === '') {
            return [''];
        }

        $words = preg_split('/\s+/u', $text) ?: [$text];
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($current !== '' && $this->measure($candidate, $size, $bold) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * @param array{0:float,1:float,2:float} $color
     * @return float nouvelle position Y
     */
    public function paragraph(
        string $text,
        float $x,
        float $width,
        float $size = 9,
        array $color = [0.35, 0.35, 0.35],
        bool $bold = false,
        float $lineHeight = 12
    ): float {
        foreach ($this->wrap($text, $width, $size, $bold) as $line) {
            $this->ensureSpace($lineHeight);
            $this->text($line, $x, $size, $bold, $color);
            $this->y += $lineHeight;
        }
        return $this->y;
    }

    public function output(): string
    {
        // Flush dernière page.
        $this->pages[] = $this->content;
        $total = count($this->pages);

        $footer = 'Document généré automatiquement - ' . $total . ' page(s)';
        foreach ($this->pages as $index => &$pageContent) {
            $pageContent .= sprintf(
                "0.45 0.45 0.45 rg\nBT /F1 7.5 Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
                $this->margin,
                20,
                iePdfText('EuroImport Export - Facture - Page ' . ($index + 1) . ' / ' . $total)
            );
            $pageContent .= sprintf(
                "0.45 0.45 0.45 rg\nBT /F1 7.5 Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET\n",
                $this->pageWidth - $this->margin - 130,
                20,
                iePdfText($footer)
            );
        }
        unset($pageContent);

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        $kids = [];
        for ($i = 0; $i < $total; $i++) {
            $kids[] = (5 + $i * 2) . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Count ' . $total . ' /Kids [' . implode(' ', $kids) . '] >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        foreach ($this->pages as $index => $pageContent) {
            $pageObj = 5 + $index * 2;
            $contentObj = $pageObj + 1;
            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . number_format($this->pageWidth, 2, '.', '') . ' '
                . number_format($this->pageHeight, 2, '.', '')
                . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '
                . $contentObj . ' 0 R >>';
            $objects[$contentObj] = '<< /Length ' . strlen($pageContent) . " >>\nstream\n"
                . $pageContent . "\nendstream";
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF\n";

        return $pdf;
    }
}
