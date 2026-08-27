<?php

declare(strict_types=1);

namespace WPCBTPro\Import\Word;

/**
 * Walks an OMML (<m:oMath>) tree and produces MathML, rendered client-side
 * by MathJax (§7, §28) — never a rasterized image and never hand-typed
 * LaTeX. Constructs this doesn't recognize are never silently dropped: they
 * fall back to their literal text and are recorded as a warning so the
 * Import Preview (§6.1) can flag "unsupported equation" (risk R-1, §27).
 */
final class OmmlToMathMlConverter
{
    private const M_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    /** @var string[] */
    private array $warnings = [];

    /** @return string[] */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function convert(\DOMElement $oMath): string
    {
        $this->warnings = [];
        $inner = $this->convertContainer($oMath);

        return '<math xmlns="http://www.w3.org/1998/Math/MathML">' . $inner . '</math>';
    }

    /**
     * wp_kses' default 'post' schema strips MathML — this is what any view
     * rendering converted equations back through wp_kses() needs to merge
     * in so the markup this converter produces survives escaping intact.
     *
     * @return array<string, array<string, bool>>
     */
    public static function allowedKsesTags(): array
    {
        $tags = [
            'math', 'mrow', 'mfrac', 'msqrt', 'mroot', 'msup', 'msub', 'msubsup',
            'munder', 'mover', 'munderover', 'mtable', 'mtr', 'mtd', 'mn', 'mi', 'mo', 'mtext',
        ];

        return array_fill_keys($tags, ['xmlns' => true]);
    }

    private function convertContainer(\DOMElement $container): string
    {
        $parts = [];
        $runBuffer = '';

        foreach ($container->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::M_NS) {
                continue;
            }

            $local = $child->localName;

            if ($local === 'r') {
                $runBuffer .= $this->extractRunText($child);
                continue;
            }

            if ($runBuffer !== '') {
                $parts[] = $this->tokenize($runBuffer);
                $runBuffer = '';
            }

            if (str_ends_with($local, 'Pr') || $local === 'ctrlPr') {
                continue;
            }

            $parts[] = $this->convertNode($child);
        }

        if ($runBuffer !== '') {
            $parts[] = $this->tokenize($runBuffer);
        }

        return implode('', $parts);
    }

    private function convertNode(\DOMElement $node): string
    {
        return match ($node->localName) {
            'oMath', 'oMathPara' => $this->convertContainer($node),
            'f' => $this->convertFraction($node),
            'rad' => $this->convertRadical($node),
            'sSup' => $this->convertScript($node, 'msup', ['e', 'sup']),
            'sSub' => $this->convertScript($node, 'msub', ['e', 'sub']),
            'sSubSup' => $this->convertScript($node, 'msubsup', ['e', 'sub', 'sup']),
            'nary' => $this->convertNary($node),
            'd' => $this->convertDelimiter($node),
            'm' => $this->convertMatrix($node),
            'limLow' => $this->convertScript($node, 'munder', ['e', 'lim']),
            'limUpp' => $this->convertScript($node, 'mover', ['e', 'lim']),
            default => $this->convertUnsupported($node),
        };
    }

    private function convertFraction(\DOMElement $node): string
    {
        $num = $this->childByLocalName($node, 'num');
        $den = $this->childByLocalName($node, 'den');

        return sprintf(
            '<mfrac><mrow>%s</mrow><mrow>%s</mrow></mfrac>',
            $num !== null ? $this->convertContainer($num) : '',
            $den !== null ? $this->convertContainer($den) : ''
        );
    }

    private function convertRadical(\DOMElement $node): string
    {
        $base = $this->childByLocalName($node, 'e');
        $degree = $this->childByLocalName($node, 'deg');
        $degreeHidden = $this->radicalDegreeHidden($node) || $degree === null || $this->convertContainer($degree) === '';

        $baseMl = $base !== null ? $this->convertContainer($base) : '';

        if ($degreeHidden) {
            return "<msqrt>{$baseMl}</msqrt>";
        }

        return sprintf('<mroot><mrow>%s</mrow><mrow>%s</mrow></mroot>', $baseMl, $this->convertContainer($degree));
    }

    private function radicalDegreeHidden(\DOMElement $rad): bool
    {
        $radPr = $this->childByLocalName($rad, 'radPr');
        if ($radPr === null) {
            return false;
        }
        $degHide = $this->childByLocalName($radPr, 'degHide');
        return $degHide !== null && $degHide->getAttributeNS(self::M_NS, 'val') !== '0';
    }

    /** @param string[] $childOrder */
    private function convertScript(\DOMElement $node, string $tag, array $childOrder): string
    {
        $parts = [];
        foreach ($childOrder as $localName) {
            $child = $this->childByLocalName($node, $localName);
            $parts[] = '<mrow>' . ($child !== null ? $this->convertContainer($child) : '') . '</mrow>';
        }

        return "<{$tag}>" . implode('', $parts) . "</{$tag}>";
    }

    private function convertNary(\DOMElement $node): string
    {
        $symbol = $this->naryChar($node);
        $sub = $this->childByLocalName($node, 'sub');
        $sup = $this->childByLocalName($node, 'sup');
        $body = $this->childByLocalName($node, 'e');
        $bodyMl = $body !== null ? $this->convertContainer($body) : '';

        $subMl = $sub !== null ? $this->convertContainer($sub) : '';
        $supMl = $sup !== null ? $this->convertContainer($sup) : '';

        if ($subMl === '' && $supMl === '') {
            return "<mrow><mo>{$symbol}</mo><mrow>{$bodyMl}</mrow></mrow>";
        }

        return sprintf(
            '<mrow><munderover><mo>%s</mo><mrow>%s</mrow><mrow>%s</mrow></munderover><mrow>%s</mrow></mrow>',
            $symbol,
            $subMl,
            $supMl,
            $bodyMl
        );
    }

    private function naryChar(\DOMElement $nary): string
    {
        $naryPr = $this->childByLocalName($nary, 'naryPr');
        if ($naryPr !== null) {
            $chr = $this->childByLocalName($naryPr, 'chr');
            if ($chr !== null) {
                $val = $chr->getAttributeNS(self::M_NS, 'val');
                if ($val !== '') {
                    return htmlspecialchars($val, ENT_XML1 | ENT_QUOTES);
                }
            }
        }

        return '&#8721;'; // summation as a sane default
    }

    private function convertDelimiter(\DOMElement $node): string
    {
        $dPr = $this->childByLocalName($node, 'dPr');
        $begin = $this->delimiterChar($dPr, 'begChr', '(');
        $end = $this->delimiterChar($dPr, 'endChr', ')');

        $inner = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::M_NS && $child->localName === 'e') {
                $inner[] = $this->convertContainer($child);
            }
        }

        return sprintf('<mrow><mo>%s</mo>%s<mo>%s</mo></mrow>', $begin, implode('<mo>,</mo>', $inner), $end);
    }

    private function delimiterChar(?\DOMElement $dPr, string $localName, string $default): string
    {
        if ($dPr === null) {
            return $default;
        }
        $el = $this->childByLocalName($dPr, $localName);
        $val = $el?->getAttributeNS(self::M_NS, 'val');
        return $val !== null && $val !== '' ? htmlspecialchars($val, ENT_XML1 | ENT_QUOTES) : $default;
    }

    private function convertMatrix(\DOMElement $node): string
    {
        $rows = [];
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement || $child->namespaceURI !== self::M_NS || $child->localName !== 'mr') {
                continue;
            }

            $cells = [];
            foreach ($child->childNodes as $cell) {
                if ($cell instanceof \DOMElement && $cell->namespaceURI === self::M_NS && $cell->localName === 'e') {
                    $cells[] = '<mtd>' . $this->convertContainer($cell) . '</mtd>';
                }
            }
            $rows[] = '<mtr>' . implode('', $cells) . '</mtr>';
        }

        return '<mtable>' . implode('', $rows) . '</mtable>';
    }

    private function convertUnsupported(\DOMElement $node): string
    {
        $this->warnings[] = sprintf(
            /* translators: %s: OMML element name, e.g. "m:eqArr" */
            __('Unsupported equation construct: %s', 'wp-cbt-pro'),
            'm:' . $node->localName
        );

        return '<mtext>' . htmlspecialchars(trim($node->textContent), ENT_XML1 | ENT_QUOTES) . '</mtext>';
    }

    private function extractRunText(\DOMElement $run): string
    {
        $text = '';
        foreach ($run->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::M_NS && $child->localName === 't') {
                $text .= $child->textContent;
            }
        }

        return $text;
    }

    private function tokenize(string $text): string
    {
        $pattern = '/([0-9]+(?:\.[0-9]+)?)|([+\-=<>≤≥±×÷·(){}\[\],])|([A-Za-zΑ-Ωα-ω]+)|(\s+)/u';
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        $out = [];
        foreach ($matches as $m) {
            if ($m[1] !== '') {
                $out[] = '<mn>' . htmlspecialchars($m[1], ENT_XML1 | ENT_QUOTES) . '</mn>';
            } elseif ($m[2] !== '') {
                $out[] = '<mo>' . htmlspecialchars($m[2], ENT_XML1 | ENT_QUOTES) . '</mo>';
            } elseif ($m[3] !== '') {
                $out[] = '<mi>' . htmlspecialchars($m[3], ENT_XML1 | ENT_QUOTES) . '</mi>';
            }
            // whitespace tokens (group 4) are intentionally dropped — MathML spacing is structural, not textual.
        }

        return '<mrow>' . implode('', $out) . '</mrow>';
    }

    private function childByLocalName(\DOMElement $parent, string $localName): ?\DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->namespaceURI === self::M_NS && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }
}
