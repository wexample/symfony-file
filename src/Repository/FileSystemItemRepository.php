<?php

namespace Wexample\SymfonyFile\Repository;

use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Uid\Uuid;
use UnexpectedValueException;
use Wexample\SymfonyFile\Entity\FileSystemItemEntity;
use Wexample\SymfonyFile\Enum\FileSystemItemType;

class FileSystemItemRepository implements ObjectRepository
{
    public const CRITERIA_PATH = 'path';

    public const CRITERIA_PARENT = 'parent';

    public function __construct(
        protected string $rootPath
    ) {
    }

    public function getClassName(): string
    {
        return FileSystemItemEntity::class;
    }

    /**
     * A file system item is addressed by its path: the uuid the entity carries
     * is a one way hash of that path, and cannot be resolved back to a location.
     */
    public function find(mixed $id): ?FileSystemItemEntity
    {
        if ($id instanceof Uuid) {
            throw new UnexpectedValueException(
                'A file system item is addressed by path, not by uuid.'
            );
        }

        return $this->findOneBy([
            static::CRITERIA_PATH => $id,
        ]);
    }

    public function findOneBy(array $criteria): ?FileSystemItemEntity
    {
        return $this->findBy($criteria, null, 1)[0] ?? null;
    }

    /**
     * Answers one level and never the tree: a directory may hold node_modules,
     * vendor or .git, which no caller can afford to receive whole.
     */
    public function findBy(
        array $criteria,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        if (null !== $orderBy) {
            throw new UnexpectedValueException(
                'File system items come in the order given by the file system.'
            );
        }

        $paths = $this->resolveCriteriaPaths($criteria);

        if (null !== $limit || null !== $offset) {
            $paths = array_slice($paths, $offset ?? 0, $limit);
        }

        return array_map(
            $this->createItem(...),
            $paths
        );
    }

    public function findAll(): array
    {
        return $this->findBy([
            static::CRITERIA_PARENT => '',
        ]);
    }

    private function createItem(string $absolutePath): FileSystemItemEntity
    {
        return new FileSystemItemEntity(
            $this->toRelativePath($absolutePath),
            FileSystemItemType::fromPath($absolutePath)
        );
    }

    /**
     * @return string[] absolute paths
     */
    private function resolveCriteriaPaths(array $criteria): array
    {
        if (array_key_exists(static::CRITERIA_PATH, $criteria)) {
            $path = $this->toContainedAbsolutePath($criteria[static::CRITERIA_PATH]);

            return null !== $path ? [$path] : [];
        }

        if (array_key_exists(static::CRITERIA_PARENT, $criteria)) {
            $parent = $this->toContainedAbsolutePath($criteria[static::CRITERIA_PARENT]);

            if (null === $parent || !is_dir($parent)) {
                return [];
            }

            return array_map(
                static fn (string $name): string => $parent.DIRECTORY_SEPARATOR.$name,
                array_values(
                    array_diff(scandir($parent), ['.', '..'])
                )
            );
        }

        throw new UnexpectedValueException(
            'A file system item is looked up by '
            .static::CRITERIA_PATH.' or '.static::CRITERIA_PARENT.'.'
        );
    }

    /**
     * Paths travel as relative to the root, and come back from routes: resolve
     * symlinks and traversals, then answer null for anything landing outside.
     */
    private function toContainedAbsolutePath(string $relativePath): ?string
    {
        $root = $this->getAbsoluteRootPath();
        $resolved = realpath($root.DIRECTORY_SEPARATOR.$relativePath);

        if (false === $resolved) {
            return null;
        }

        return $resolved === $root || str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)
            ? $resolved
            : null;
    }

    private function toRelativePath(string $absolutePath): string
    {
        return ltrim(
            substr($absolutePath, strlen($this->getAbsoluteRootPath())),
            DIRECTORY_SEPARATOR
        );
    }

    private function getAbsoluteRootPath(): string
    {
        $root = realpath($this->rootPath);

        if (false === $root) {
            throw new UnexpectedValueException(
                'File system repository root does not exist: '.$this->rootPath
            );
        }

        return $root;
    }
}
