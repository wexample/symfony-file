<?php

namespace Wexample\SymfonyFile\Service;

use UnexpectedValueException;
use Wexample\SymfonyFile\Enum\FileSystemItemType;

/**
 * Reads one level of a tree off the disk, and says what it found.
 *
 * It answers plain values and never rows: what becomes of them — an index, a
 * listing, a checksum — is the caller's business. One level and never the tree,
 * because a directory may hold node_modules, vendor or .git, which no caller can
 * afford to receive whole.
 *
 * Built on a root and confined to it: everything outside is unreachable rather
 * than forbidden.
 */
class FileSystemItemScanner
{
    public const KEY_HAS_CHILDREN = 'has_children';
    public const KEY_MODIFIED_AT = 'modified_at';
    public const KEY_NAME = 'name';
    public const KEY_PATH = 'path';
    public const KEY_PERMISSIONS = 'permissions';
    public const KEY_SIZE = 'size';
    public const KEY_TYPE = 'type';

    public function __construct(
        protected string $rootPath
    ) {
    }

    public function getAbsoluteRootPath(): string
    {
        $root = realpath($this->rootPath);

        if (false === $root) {
            throw new UnexpectedValueException(
                'No such directory to read a tree from: '.$this->rootPath
            );
        }

        return $root;
    }

    /**
     * What one directory holds, in the order the file system gives.
     *
     * @return array<int, array<string, mixed>>
     */
    public function scanLevel(string $parent = ''): array
    {
        $absolute = $this->toContainedAbsolutePath($parent);

        if (null === $absolute || ! is_dir($absolute)) {
            return [];
        }

        $items = [];

        foreach (array_diff(scandir($absolute), ['.', '..']) as $name) {
            $items[] = $this->describe($absolute.DIRECTORY_SEPARATOR.$name);
        }

        return $items;
    }

    /**
     * One entry, told apart from where it lies rather than from what it points
     * at — see `describe()`.
     *
     * @return array<string, mixed>|null
     */
    public function scanOne(string $path): ?array
    {
        $absolute = $this->toContainedEntryPath($path);

        return null !== $absolute ? $this->describe($absolute) : null;
    }

    /**
     * What a single stat says about one entry.
     *
     * A link is described by what it is rather than by what it points at, which
     * is what the type already says of it. It is also the only thing left to say
     * when the target is out of reach — `stat` dies there, and a directory
     * holding one such entry would show nothing.
     *
     * @return array<string, mixed>
     */
    private function describe(string $absolutePath): array
    {
        $stat = is_link($absolutePath)
            ? lstat($absolutePath)
            : stat($absolutePath);

        return [
            self::KEY_PATH => $this->toRelativePath($absolutePath),
            self::KEY_NAME => basename($absolutePath),
            self::KEY_TYPE => FileSystemItemType::fromPath($absolutePath),
            self::KEY_HAS_CHILDREN => $this->hasEntries($absolutePath),
            self::KEY_SIZE => $stat['size'],
            self::KEY_MODIFIED_AT => $stat['mtime'],
            // The low twelve bits of the mode are the permissions, the rest says
            // what kind of node it is, which the type already tells.
            self::KEY_PERMISSIONS => sprintf('%04o', $stat['mode'] & 07777),
        ];
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
     * One entry of the tree, which is not the same question as a directory to
     * read into.
     *
     * The entry is not followed: a link belongs to the directory holding it, so
     * it is described where it lies rather than refused for where it points.
     * What holds it is resolved as ever, so a traversal is caught exactly as
     * before, and reading *inside* a link leading out is still refused.
     */
    private function toContainedEntryPath(string $relativePath): ?string
    {
        $directory = $this->toContainedAbsolutePath(dirname($relativePath));

        if (null === $directory) {
            return null;
        }

        $path = $directory.DIRECTORY_SEPARATOR.basename($relativePath);

        return is_link($path) || file_exists($path) ? $path : null;
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
}
