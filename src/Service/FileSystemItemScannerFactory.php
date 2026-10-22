<?php

namespace Wexample\SymfonyFile\Service;

use Wexample\PhpFile\Class\FileSystemItemScanner;

/**
 * Hands out a reader for each tree the application declared.
 *
 * A root is named in configuration and nowhere else: an application serving
 * trees it discovers at runtime — one per mounted project, say — builds its own
 * scanner on the path it knows, and never asks here.
 */
class FileSystemItemScannerFactory
{
    /**
     * @param array<string, string> $roots absolute paths, indexed by root name
     */
    public function __construct(
        protected array $roots
    ) {
    }

    public function getScanner(string $rootName): ?FileSystemItemScanner
    {
        $rootPath = $this->roots[$rootName] ?? null;

        return null !== $rootPath
            ? new FileSystemItemScanner($rootPath)
            : null;
    }
}
