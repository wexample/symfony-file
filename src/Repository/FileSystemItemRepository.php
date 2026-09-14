<?php

namespace Wexample\SymfonyFile\Repository;

use Doctrine\ORM\QueryBuilder;
use Wexample\SymfonyFile\Entity\FileSystemItem;
use Wexample\SymfonyFile\Entity\Traits\Manipulator\FileSystemItemManipulatorTrait;
use Wexample\SymfonyHelpers\Repository\AbstractRepository;

/**
 * @method FileSystemItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method FileSystemItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method FileSystemItem[]    findAll()
 * @method FileSystemItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method FileSystemItem      saveNewFileSystemItem(string $root, string $path)
 */
class FileSystemItemRepository extends AbstractRepository
{
    use FileSystemItemManipulatorTrait;

    /**
     * An entry of a tree, at a location and with nothing said about it yet.
     *
     * Nothing else is set: the disk owns what an entry is, so whoever read it
     * fills the row from what it found there.
     */
    public function createNewFileSystemItem(
        string $root,
        string $path,
    ): FileSystemItem {
        return new FileSystemItem($root, $path);
    }

    public function findOneByLocation(
        string $root,
        string $path,
    ): ?FileSystemItem {
        return $this->find(FileSystemItem::idFor($root, $path));
    }

    /**
     * What one directory holds, as the last reading of it recorded.
     *
     * Ordered directories first and then by name, which is the order a tree is
     * read in and the one thing the file system does not promise.
     *
     * @return FileSystemItem[]
     */
    public function findLevel(
        string $root,
        string $parent,
    ): array {
        return $this->queryLevel($root, $parent)
            ->getQuery()
            ->getResult();
    }

    public function countLevel(
        string $root,
        string $parent,
    ): int {
        return $this->countAll($this->queryLevel($root, $parent));
    }

    /**
     * Forgets a whole tree, for whoever is about to read it again.
     *
     * A row whose file is gone must not survive the next reading, and telling
     * which ones those are costs more than writing them all back.
     */
    public function removeRoot(string $root): int
    {
        return $this->createQueryBuilder('item')
            ->delete()
            ->where('item.root = :root')
            ->setParameter('root', $root)
            ->getQuery()
            ->execute();
    }

    private function queryLevel(
        string $root,
        string $parent,
    ): QueryBuilder {
        return $this->createQueryBuilder('item')
            ->where('item.root = :root')
            ->andWhere('item.parent = :parent')
            // A directory is not one of its own children. Only the root is ever
            // caught by this, its path and its parent being equally empty.
            ->andWhere('item.path != :parent')
            ->setParameter('root', $root)
            ->setParameter('parent', $parent)
            ->orderBy('item.type', self::SORT_ASC)
            ->addOrderBy('item.name', self::SORT_ASC);
    }
}
