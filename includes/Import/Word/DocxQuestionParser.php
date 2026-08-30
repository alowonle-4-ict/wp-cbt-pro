<?php

declare(strict_types=1);

namespace WPCBTPro\Import\Word;

/**
 * Walks word/document.xml paragraph by paragraph, recognizing the marker
 * vocabulary from the downloadable template (§6.2) — TYPE, SUBJECT, MARKS,
 * lettered options, ANSWER. Marker-driven rather than layout-driven, so
 * font/spacing variance between authors doesn't break extraction.
 */
final class DocxQuestionParser
{
    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const M_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/math';
    private const A_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const R_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @var (callable(string): ?string)|null set for the duration of a single parse() call */
    private $imageResolver = null;

    public function __construct(private readonly OmmlToMathMlConverter $equationConverter)
    {
    }

    /**
     * @param (callable(string): ?string)|null $imageResolver maps a w:drawing's
     *        r:embed relationship id to an inline data: URI, or null to skip it
     * @return array<int, array<string, mixed>> parsed blocks, one per QUESTION marker
     */
    public function parse(string $documentXml, ?callable $imageResolver = null): array
    {
        $this->imageResolver = $imageResolver;

        $dom = new \DOMDocument();
        $dom->loadXML($documentXml, LIBXML_NONET | LIBXML_NOENT);

        $body = $dom->getElementsByTagNameNS(self::W_NS, 'body')->item(0);
        if ($body === null) {
            $this->imageResolver = null;
            return [];
        }

        $blocks = [];
        $current = null;
        $section = null;

        foreach ($body->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->namespaceURI !== self::W_NS || $node->localName !== 'p') {
                continue;
            }

            [$line, $hadEquation, $equationWarnings] = $this->renderParagraph($node);
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^QUESTION\s+(\d+)\s*$/i', $line, $m)) {
                if ($current !== null) {
                    $blocks[] = $this->finalize($current);
                }
                $current = $this->newBlock((int) $m[1]);
                $section = null;
                continue;
            }

            if ($current === null) {
                continue; // content before the first QUESTION marker (title, instructions)
            }

            if (preg_match('/^TYPE:\s*(.*)$/i', $line, $m)) {
                $current['type'] = trim($m[1]);
                $section = null;
            } elseif (preg_match('/^SUBJECT:\s*(.*)$/i', $line, $m)) {
                $current['subject'] = trim($m[1]);
            } elseif (preg_match('/^TOPIC:\s*(.*)$/i', $line, $m)) {
                $current['topic'] = trim($m[1]);
            } elseif (preg_match('/^MARKS:\s*([\d.]+)/i', $line, $m)) {
                $current['marks'] = (float) $m[1];
            } elseif (preg_match('/^NEGATIVE:\s*([\d.]+)/i', $line, $m)) {
                $current['negative'] = (float) $m[1];
            } elseif (preg_match('/^DATA_STRUCTURE:\s*(.*)$/i', $line, $m)) {
                $current['data_structure'] = trim($m[1]);
            } elseif (preg_match('/^QUESTION:\s*$/i', $line)) {
                $section = 'body';
            } elseif (preg_match('/^([A-Za-z])\.\s*(.+)$/', $line, $m)) {
                $section = 'options';
                $current['options'][] = ['letter' => strtoupper($m[1]), 'html' => $m[2]];
            } elseif (preg_match('/^ANSWER:\s*(.*)$/i', $line, $m)) {
                $current['answer'] = trim(wp_strip_all_tags($m[1]));
                $section = 'answer';
            } elseif ($section === 'body') {
                $current['body_lines'][] = $line;
            } elseif ($section === 'options' && $current['options'] !== []) {
                $lastIndex = array_key_last($current['options']);
                $current['options'][$lastIndex]['html'] .= ' ' . $line;
            }

            if ($hadEquation) {
                $current['has_equation'] = true;
                array_push($current['equation_warnings'], ...$equationWarnings);
            }
        }

        if ($current !== null) {
            $blocks[] = $this->finalize($current);
        }

        $this->imageResolver = null;

        return $blocks;
    }

    /** @return array<string, mixed> */
    private function newBlock(int $index): array
    {
        return [
            'index' => $index,
            'type' => '',
            'subject' => '',
            'topic' => '',
            'marks' => null,
            'negative' => 0.0,
            'data_structure' => null,
            'body_lines' => [],
            'options' => [],
            'answer' => '',
            'has_equation' => false,
            'equation_warnings' => [],
        ];
    }

    /** @param array<string, mixed> $block */
    private function finalize(array $block): array
    {
        $block['body_html'] = $block['body_lines'] === []
            ? ''
            : '<p>' . implode('</p><p>', $block['body_lines']) . '</p>';
        unset($block['body_lines']);

        return $block;
    }

    /**
     * @return array{0: string, 1: bool, 2: string[]} [rendered HTML line, had an equation, equation warnings]
     */
    private function renderParagraph(\DOMElement $paragraph): array
    {
        $html = '';
        $hadEquation = false;
        $warnings = [];

        foreach ($this->descendantsInOrder($paragraph) as $node) {
            if ($node->namespaceURI === self::W_NS && $node->localName === 't') {
                $html .= htmlspecialchars($node->textContent, ENT_QUOTES | ENT_XML1);
            } elseif ($node->namespaceURI === self::W_NS && $node->localName === 'tab') {
                $html .= ' ';
            } elseif ($node->namespaceURI === self::M_NS && $node->localName === 'oMath') {
                $html .= $this->equationConverter->convert($node);
                $hadEquation = true;
                array_push($warnings, ...$this->equationConverter->warnings());
            } elseif ($node->namespaceURI === self::W_NS && $node->localName === 'drawing') {
                $html .= $this->renderDrawing($node);
            }
        }

        return [$html, $hadEquation, $warnings];
    }

    /**
     * A w:drawing wraps a:blip r:embed, which names a relationship id rather
     * than the image bytes themselves — resolving it needs the package's
     * relationships/media, which this parser doesn't have, so the caller
     * supplies a resolver instead of this class knowing about ZIP internals.
     */
    private function renderDrawing(\DOMElement $drawing): string
    {
        if ($this->imageResolver === null) {
            return '';
        }

        $blip = $drawing->getElementsByTagNameNS(self::A_NS, 'blip')->item(0);
        if ($blip === null) {
            return '';
        }

        $relationshipId = $blip->getAttributeNS(self::R_NS, 'embed');
        if ($relationshipId === '') {
            return '';
        }

        $dataUri = ($this->imageResolver)($relationshipId);
        if ($dataUri === null) {
            return '';
        }

        return '<img src="' . htmlspecialchars($dataUri, ENT_QUOTES | ENT_XML1) . '" alt="">';
    }

    /** @return \DOMElement[] leaf nodes (w:t, w:tab, m:oMath, w:drawing) in document order, without descending into an already-consumed m:oMath/w:drawing */
    private function descendantsInOrder(\DOMElement $paragraph): array
    {
        $result = [];
        $this->walk($paragraph, $result);
        return $result;
    }

    /** @param \DOMElement[] $result */
    private function walk(\DOMElement $node, array &$result): void
    {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::M_NS && $child->localName === 'oMath') {
                $result[] = $child;
                continue; // don't descend — the converter walks this subtree itself
            }

            if ($child->namespaceURI === self::W_NS && in_array($child->localName, ['t', 'tab', 'drawing'], true)) {
                $result[] = $child;
                continue;
            }

            $this->walk($child, $result);
        }
    }
}
