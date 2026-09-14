<?php

namespace Wexample\SymfonyFile\Repository;

use DateTimeImmutable;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Uid\Uuid;
use UnexpectedValueException;
use Wexample\SymfonyFile\Entity\FileSystemItem;
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
        return FileSystemItem::class;
    }

    /**
     * A file system item is addressed by its path: the uuid the entity carries
     * is a one way hash of that path, and cannot be resolved back to a location.
     */
    public function find(mixed $id): ?FileSystemItem
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

    public function findOneBy(array $criteria): ?FileSystemItem
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

    /**
     * How many items the criteria hold, which a listing needs in order to say it
     * was cut short. The directory is read a second time rather than carried
     * along with the page, and that second read lands on a warm cache.
     */
    public function countBy(array $criteria): int
    {
        return count($this->resolveCriteriaPaths($criteria));
    }

    private function createItem(string $absolutePath): FileSystemItem
    {
        // A link is described by what it is rather than by what it points at,
        // which is what the type already says of it. It is also the only thing
        // left to say when the target is out of reach — a listing dies on
        // `stat` there, and a directory holding one such entry shows nothing.
        $stat = is_link($absolutePath)
            ? lstat($absolutePath)
            : stat($absolutePath);

        return new FileSystemItem(
            $this->toRelativePath($absolutePath),
            FileSystemItemType::fromPath($absolutePath),
            $this->hasEntries($absolutePath),
            $stat['size'],
            (new DateTimeImmutable())->setTimestamp($stat['mtime']),
            // The low twelve bits of the mode are the permissions, the rest says
            // what kind of node it is, which the type already tells.
            sprintf('%04o', $stat['mode'] & 07777)
        );
    }

    /**
     * Whether a directory holds anything, told by opening it and stopping at the
     * first entry. Counting would cost the whole listing for an answer nothing
     * displays.
     */
    private function hasEntries(string $absolutePath): bool
    {
        if (! is_dir($absolutePath)) {
            return false;
        }

        $handle = opendir($absolutePath);

        if (false === $handle) {
            return false;
        }

        try {
            while (false !== ($entry = readdir($handle))) {
                if ('.' !== $entry && '..' !== $entry) {
                    return true;
                }
            }
        } finally {
            closedir($handle);
        }

        return false;
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

            if (null === $parent || ! is_dir($parent)) {
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
