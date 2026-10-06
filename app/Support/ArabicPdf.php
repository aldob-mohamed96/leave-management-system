<?php

namespace App\Support;

/**
 * Pre-shapes Arabic text so DomPDF (which lacks a real shaping engine) can render it.
 *
 * utf8Glyphs() returns glyphs already in visual LTR order for engines that draw
 * characters left-to-right. After shaping we force the document to LTR so DomPDF
 * does not reverse the glyphs again (which is what breaks Arabic direction).
 */
class ArabicPdf
{
    /**
     * Reshape Arabic runs inside an HTML document for DomPDF rendering.
     */
    public static function shapeHtml(string $html): string
    {
        if (! class_exists(\ArPHP\I18N\Arabic::class)) {
            return $html;
        }

        $arabic = new \ArPHP\I18N\Arabic();
        $positions = $arabic->arIdentify($html);

        for ($i = count($positions) - 1; $i >= 1; $i -= 2) {
            $start = $positions[$i - 1];
            $end = $positions[$i];
            $length = $end - $start;

            if ($length <= 0) {
                continue;
            }

            $segment = substr($html, $start, $length);

            // Skip anything that looks like a tag/attribute fragment
            if (str_contains($segment, '<') || str_contains($segment, '>')) {
                continue;
            }

            // High max_chars avoids mid-sentence line-split artifacts in bidi.
            $shaped = $arabic->utf8Glyphs($segment, 600);
            $html = substr_replace($html, $shaped, $start, $length);
        }

        // Shaped glyphs are visual-LTR — prevent DomPDF RTL from flipping them.
        return self::forceLtrDocument($html);
    }

    private static function forceLtrDocument(string $html): string
    {
        $html = preg_replace('/\bdir\s*=\s*([\'"])rtl\1/i', 'dir="ltr"', $html) ?? $html;
        $html = preg_replace('/direction\s*:\s*rtl/i', 'direction: ltr', $html) ?? $html;
        $html = preg_replace('/unicode-bidi\s*:\s*embed/i', 'unicode-bidi: normal', $html) ?? $html;

        return $html;
    }
}
