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
    private const MIME_BY_EXTENSION = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
    ];

    private \ZipArchive $zip;

    /** @var array<string, string>|null */
    private ?array $relationshipsCache = null;

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
        if ($this->relationshipsCache !== null) {
            return $this->relationshipsCache;
        }

        $raw = $this->zip->getFromName('word/_rels/document.xml.rels');
        if ($raw === false) {
            return $this->relationshipsCache = [];
        }

        $dom = new \DOMDocument();
        $dom->loadXML($raw);

        $map = [];
        foreach ($dom->getElementsByTagName('Relationship') as $rel) {
            $id = $rel->getAttribute('Id');
            $target = $rel->getAttribute('Target');
            $map[$id] = 'word/' . ltrim($target, '/');
        }

        return $this->relationshipsCache = $map;
    }

    public function readBinary(string $pathInPackage): ?string
    {
        $data = $this->zip->getFromName($pathInPackage);
        return $data === false ? null : $data;
    }

    /**
     * Resolves a w:drawing's r:embed relationship id to an inline data: URI,
     * for the import preview to render before anything is uploaded (§6.3).
     * Only common web-renderable raster formats are supported — a .docx can
     * embed EMF/WMF vector images, which browsers can't display inline, so
     * those are silently skipped rather than producing a broken <img>.
     */
    public function imageDataUri(string $relationshipId): ?string
    {
        $target = $this->relationships()[$relationshipId] ?? null;
        if ($target === null) {
            return null;
        }

        $mime = self::MIME_BY_EXTENSION[strtolower((string) pathinfo($target, PATHINFO_EXTENSION))] ?? null;
        if ($mime === null) {
            return null;
        }

        $binary = $this->readBinary($target);
        if ($binary === null) {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    public function close(): void
    {
        $this->zip->close();
    }
}
