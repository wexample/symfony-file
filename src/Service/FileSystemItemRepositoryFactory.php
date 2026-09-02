<?php

namespace Wexample\SymfonyFile\Service;

use Wexample\SymfonyFile\Repository\FileSystemItemRepository;

class FileSystemItemRepositoryFactory
{
    /**
     * @param array<string, string> $roots absolute paths, indexed by root name
     */
    public function __construct(
        protected array $roots
    ) {
    }

    public function getRepository(string $rootName): ?FileSystemItemRepository
    {
        $rootPath = $this->roots[$rootName] ?? null;

        return null !== $rootPath
            ? new FileSystemItemRepository($rootPath)
            : null;
    }
}
