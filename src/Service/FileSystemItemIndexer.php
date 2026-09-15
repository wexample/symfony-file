<?php

namespace Wexample\SymfonyFile\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Wexample\SymfonyFile\Entity\FileSystemItem;
use Wexample\SymfonyFile\Enum\FileSystemItemType;
use Wexample\SymfonyFile\Repository\FileSystemItemRepository;

/**
 * Keeps the index of a tree in step with the disk, one level at a time.
 *
 * A level is read from the disk the first time somebody opens it, and from the
 * table every time after. That is the whole of the invalidation, and it costs
 * nothing to arrange: the explorer already asks for one directory at a time, so
 * the laziness that was there for the browser is the one that indexes.
 *
 * What it deliberately is not: a watcher. A `git checkout` replaces hundreds of
 * files without a single usable event — an index that re-reads absorbs that,
 * where a watcher would produce noise.
 */
final readonly class FileSystemItemIndexer
{
    /** How many rows a sweep holds before writing them. */
    private const int BATCH_SIZE = 2000;

    public function __construct(
        private FileSystemItemRepository $items,
        private FileSystemItemHydrator $hydrator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * What a directory holds, read from the disk if nobody has read it yet.
     *
     * @return FileSystemItem[]
     */
    public function level(
        FileSystemItemScanner $scanner,
        string $parent = '',
    ): array {
        $root = $scanner->getAbsoluteRootPath();
        $directory = $this->directory($root, $parent);

        if (null === $directory->getChildrenScannedAt()) {
            $this->index($scanner, $root, $parent, $directory);
        }

        return $this->items->findLevel($root, $parent);
    }

    /**
     * Reads a whole tree, level by level, and forgets whatever it held before.
     *
     * Dropping first rather than reconciling: a file that is gone leaves no
     * trace to find, and telling which rows those are costs more than writing
     * them all back. What this buys over indexing on demand is completeness —
     * a question crossing the tree, a search or a selection, can be answered
     * without opening every directory first.
     *
     * @return int how many entries the tree holds
     */
    public function sweep(FileSystemItemScanner $scanner): int
    {
        $root = $scanner->getAbsoluteRootPath();

        $this->items->removeRoot($root);

        // The root stands for itself, and is the only entry nothing else opens:
        // every other directory is written as an entry of the level above it.
        $rows = [
            $this->row($root, '', '', [
                FileSystemItemScanner::KEY_NAME => '',
                FileSystemItemScanner::KEY_TYPE => FileSystemItemType::DIRECTORY,
                FileSystemItemScanner::KEY_HAS_CHILDREN => true,
                FileSystemItemScanner::KEY_SIZE => 0,
                FileSystemItemScanner::KEY_MODIFIED_AT => time(),
                FileSystemItemScanner::KEY_PERMISSIONS => '',
            ]),
        ];

        $count = $this->sweepLevel($scanner, $root, '', $rows);

        $this->items->insertMany($rows);

        return $count;
    }

    /**
     * One level and everything under it, collecting rows as it goes.
     *
     * Written in batches rather than one by one: the cost of a sweep is the
     * round trip to the database, and a tree runs to tens of thousands of
     * entries.
     *
     * Recursive rather than a queue: a tree is as deep as a filesystem lets a
     * path be, which no stack has ever minded.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function sweepLevel(
        FileSystemItemScanner $scanner,
        string $root,
        string $parent,
        array &$rows,
    ): int {
        $level = $scanner->scanLevel($parent);
        $count = count($level);
        $directories = [];

        foreach ($level as $values) {
            $path = $values[FileSystemItemScanner::KEY_PATH];

            $rows[] = $this->row($root, $path, $parent, $values);

            // A link is not followed: it was described where it lies, and
            // walking into one leading out of the root would index another tree.
            if (FileSystemItemType::DIRECTORY === $values[FileSystemItemScanner::KEY_TYPE]) {
                $directories[] = $path;
            }
        }

        if (count($rows) >= self::BATCH_SIZE) {
            $this->items->insertMany($rows);
            $rows = [];
        }

        foreach ($directories as $path) {
            $count += $this->sweepLevel($scanner, $root, $path, $rows);
        }

        return $count;
    }

    /**
     * What one entry is, as columns rather than as an entity.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function row(
        string $root,
        string $path,
        string $parent,
        array $values,
    ): array {
        $isDirectory = FileSystemItemType::DIRECTORY === $values[FileSystemItemScanner::KEY_TYPE];

        return [
            'id' => FileSystemItem::idFor($root, $path)->toRfc4122(),
            'root' => $root,
            'path' => $path,
            'name' => $values[FileSystemItemScanner::KEY_NAME],
            'parent' => $parent,
            'type' => $values[FileSystemItemScanner::KEY_TYPE]->value,
            'has_children' => (bool) $values[FileSystemItemScanner::KEY_HAS_CHILDREN],
            'size' => (int) $values[FileSystemItemScanner::KEY_SIZE],
            'modified_at' => date('Y-m-d H:i:s', $values[FileSystemItemScanner::KEY_MODIFIED_AT]),
            'permissions' => $values[FileSystemItemScanner::KEY_PERMISSIONS],
            // A sweep reads every level, so every directory it wrote is read:
            // saying so here is what tells a later opening not to go to the disk.
            'children_scanned_at' => $isDirectory ? date('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * One entry, read from the disk when the index has never heard of it.
     *
     * The tree indexes a level before anything in it can be clicked, so this
     * finds a row nearly every time; an address typed by hand is the case it is
     * here for.
     */
    public function one(
        FileSystemItemScanner $scanner,
        string $path,
    ): ?FileSystemItem {
        $root = $scanner->getAbsoluteRootPath();
        $item = $this->items->findOneByLocation($root, $path);

        if (null !== $item) {
            return $item;
        }

        $values = $scanner->scanOne($path);

        if (null === $values) {
            return null;
        }

        $item = $this->hydrator->hydrate(
            $this->items->createNewFileSystemItem($root, $path),
            $values,
            dirname($path) === '.' ? '' : dirname($path)
        );

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    /**
     * Reads one level off the disk and writes what it found, whatever the table
     * already held for it.
     */
    public function index(
        FileSystemItemScanner $scanner,
        string $root,
        string $parent,
        FileSystemItem $directory,
    ): void {
        foreach ($scanner->scanLevel($parent) as $values) {
            $path = $values[FileSystemItemScanner::KEY_PATH];

            $item = $this->items->findOneByLocation($root, $path)
                ?? $this->items->createNewFileSystemItem($root, $path);

            $this->hydrator->hydrate($item, $values, $parent);

            $this->entityManager->persist($item);
        }

        // Stamped last and in the same flush: a level half written and called
        // read is a level nobody would read again.
        $directory->setChildrenScannedAt(new DateTimeImmutable());

        $this->entityManager->persist($directory);
        $this->entityManager->flush();
    }

    /**
     * The row standing for a directory, opened if this is the first time it is
     * heard of.
     *
     * The root of a tree is an item like any other here, with an empty path: it
     * is what carries the moment the top level was read.
     */
    private function directory(
        string $root,
        string $parent,
    ): FileSystemItem {
        $directory = $this->items->findOneByLocation($root, $parent);

        if (null === $directory) {
            $directory = $this->items
                ->createNewFileSystemItem($root, $parent)
                ->setType(FileSystemItemType::DIRECTORY)
                ->setHasChildren(true);
        }

        return $directory;
    }
}
