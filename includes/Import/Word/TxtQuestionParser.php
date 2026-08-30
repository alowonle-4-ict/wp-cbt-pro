<?php

declare(strict_types=1);

namespace WPCBTPro\Import\Word;

/**
 * The plain-text counterpart to DocxQuestionParser — for institutions that
 * already have a bank of MCQ questions as plain text and don't want to
 * reformat them into the Word template first. Blocks are separated by a
 * blank line; within a block, everything before the first lettered option
 * line is the question text, `A.`/`B.`/… lines are options, and an
 * `ANSWER: <letter>` line marks the correct one. SUBJECT/TOPIC/MARKS/
 * NEGATIVE lines are recognized too (same vocabulary as the Word
 * template) but optional — a block with nothing but a question and
 * options is enough. Produces the exact same block shape
 * DocxQuestionParser does, so WordImportService::buildPreviewRow() and
 * everything downstream of it don't need to know or care which parser
 * produced a given row.
 *
 * Only MCQ is supported here (unlike the Word importer, there's no TYPE
 * marker vocabulary for this format) — every block is type 'MCQ_SINGLE'.
 */
final class TxtQuestionParser
{
    /** @return array<int, array<string, mixed>> parsed blocks, one per blank-line-separated section */
    public function parse(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $blockTexts = preg_split('/\n\s*\n/', trim($text)) ?: [];

        $blocks = [];
        $index = 0;
        foreach ($blockTexts as $blockText) {
            $blockText = trim($blockText);
            if ($blockText === '') {
                continue;
            }

            $index++;
            $blocks[] = $this->parseBlock($index, $blockText);
        }

        return $blocks;
    }

    /** @return array<string, mixed> */
    private function parseBlock(int $index, string $blockText): array
    {
        $block = [
            'index' => $index,
            'type' => 'MCQ_SINGLE',
            'subject' => '',
            'topic' => '',
            'marks' => null,
            'negative' => 0.0,
            'data_structure' => null,
            'options' => [],
            'answer' => '',
            'has_equation' => false,
            'equation_warnings' => [],
        ];

        $bodyLines = [];

        foreach (preg_split('/\n/', $blockText) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^SUBJECT:\s*(.*)$/i', $line, $m)) {
                $block['subject'] = trim($m[1]);
            } elseif (preg_match('/^TOPIC:\s*(.*)$/i', $line, $m)) {
                $block['topic'] = trim($m[1]);
            } elseif (preg_match('/^MARKS:\s*([\d.]+)/i', $line, $m)) {
                $block['marks'] = (float) $m[1];
            } elseif (preg_match('/^NEGATIVE:\s*([\d.]+)/i', $line, $m)) {
                $block['negative'] = (float) $m[1];
            } elseif (preg_match('/^ANSWER:\s*(.*)$/i', $line, $m)) {
                $block['answer'] = trim(wp_strip_all_tags($m[1]));
            } elseif (preg_match('/^([A-Za-z])[.)]\s*(.+)$/', $line, $m)) {
                $block['options'][] = ['letter' => strtoupper($m[1]), 'html' => htmlspecialchars($m[2], ENT_QUOTES)];
            } elseif ($block['options'] === []) {
                // Still before the first option line — part of the question text.
                $bodyLines[] = htmlspecialchars($line, ENT_QUOTES);
            }
            // A plain line after options have started (and that isn't itself
            // recognized as a marker) is ignored rather than guessed at —
            // same as DocxQuestionParser only appending to the last option
            // when a continuation is unambiguous.
        }

        $block['body_html'] = $bodyLines === [] ? '' : '<p>' . implode('</p><p>', $bodyLines) . '</p>';

        return $block;
    }
}
