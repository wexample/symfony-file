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
