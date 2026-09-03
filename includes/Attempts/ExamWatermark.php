<?php

namespace WPCBTPro\Attempts;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A faint, tiled overlay identifying who was looking at the exam page and
 * when. Screenshots can't be blocked by any browser or OS technology, so
 * this doesn't try to — it makes a leaked screenshot traceable back to a
 * specific candidate and moment instead.
 */
final class ExamWatermark
{
    public static function render(array $candidate): string
    {
        $name = trim(($candidate['first_name'] ?? '') . ' ' . ($candidate['last_name'] ?? ''));
        $ref = (string) ($candidate['candidate_ref'] ?? '');
        $timestamp = current_time('Y-m-d H:i:s');
        $text = trim($name . '  ' . $ref . '  ' . $timestamp);

        if ($text === '') {
            return '';
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="260">'
            . '<text x="210" y="130" transform="rotate(-30 210 130)" font-family="sans-serif" font-size="15" '
            . 'fill="rgba(0,0,0,0.06)" text-anchor="middle">' . htmlspecialchars($text, ENT_QUOTES | ENT_XML1) . '</text>'
            . '</svg>';

        $dataUri = 'data:image/svg+xml;base64,' . base64_encode($svg);
        $style = "background-image:url('" . $dataUri . "');";

        return '<div class="wpcbtpro-watermark" aria-hidden="true" style="' . esc_attr($style) . '"></div>';
    }
}
