<?php

declare(strict_types=1);

namespace WPCBTPro\Import\Word;

/**
 * A .docx is a ZIP of XML parts (§6) — this opens the archive and hands back
 * the raw document XML rather than any "extract to text" shortcut, so the
 * structural parser and OMML converter can walk real nodes.
 */
final class DocxPackage
{
    private \ZipArchive $zip;

    private function __construct(private readonly string $path)
    {
    }

    public static function open(string $path): self
    {
        $package = new self($path);

        $package->zip = new \ZipArchive();
        $result = $package->zip->open($path);
        if ($result !== true) {
            throw new \RuntimeException("Unable to open '{$path}' as a .docx package (error {$result}).");
        }

        if ($package->zip->locateName('word/document.xml') === false) {
            throw new \RuntimeException("'{$path}' does not contain word/document.xml — not a valid .docx file.");
        }

        return $package;
    }

    public function documentXml(): string
    {
        $xml = $this->zip->getFromName('word/document.xml');
        if ($xml === false) {
            throw new \RuntimeException('Unable to read word/document.xml from the package.');
        }

        return $xml;
    }

    /** @return array<string, string> relationship id => target path within the package */
    public function relationships(): array
    {
        $raw = $this->zip->getFromName('word/_rels/document.xml.rels');
        if ($raw === false) {
            return [];
        }

        $dom = new \DOMDocument();
        $dom->loadXML($raw);

        $map = [];
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            $id = $rel->getAttribute('Id');
            $target = $rel->getAttribute('Target');
            $map[$id] = 'word/' . ltrim($target, '/');
        }

        return $map;
    }

    public function readBinary(string $pathInPackage): ?string
    {
        $data = $this->zip->getFromName($pathInPackage);
        return $data === false ? null : $data;
    }

    public function close(): void
    {
        $this->zip->close();
    }
}
