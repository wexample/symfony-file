<?php

namespace Wexample\SymfonyFile\Repository;

use Doctrine\DBAL\Connection;
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

    /**
     * Writes rows straight through, without making an entity of each.
     *
     * A sweep reads tens of thousands of entries, and the identity map alone
     * would exhaust the memory before the walk ends: a row is what the disk
     * said, it is inserted and forgotten.
     *
     * The values are written into the statement rather than bound to it. Two
     * reasons, both about scale: a multi-row insert of this width would bind
     * hundreds of thousands of parameters, and in development every one of them
     * is kept with a clone of the statement — the sweep then dies of what was
     * collected about it rather than of what it did.
     *
     * @param array<int, array<string, mixed>> $rows columns indexed by name
     */
    public function insertMany(array $rows): void
    {
        if (! $rows) {
            return;
        }

        $connection = $this->getEntityManager()->getConnection();
        $columns = array_keys($rows[0]);
        $tuples = [];

        foreach ($rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = $this->literal($connection, $row[$column]);
            }

            $tuples[] = '('.implode(',', $values).')';
        }

        $connection->executeStatement(
            'INSERT INTO file_system_item ('.implode(',', $columns).') VALUES '
            .implode(',', $tuples)
        );
    }

    /**
     * One value, as the statement spells it.
     *
     * Quoting is the connection's, which is the only thing that knows how this
     * database escapes: nothing here builds a string by hand.
     */
    private function literal(
        Connection $connection,
        mixed $value,
    ): string {
        return match (true) {
            null === $value => 'NULL',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            default => $connection->quote((string) $value),
        };
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
