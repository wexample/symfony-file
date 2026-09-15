<?php

namespace Wexample\SymfonyFile\Service;

use DateTimeImmutable;
use Wexample\PhpFile\FileSystemItemScanner;
use Wexample\SymfonyFile\Entity\FileSystemItem;

/**
 * Translates an entry between what a scan says of it and its row.
 *
 * Where those values were read is not known here: a caller holding a directory,
 * an archive or a fixture passes the same thing.
 */
final readonly class FileSystemItemHydrator
{
    /**
     * @param array<string, mixed> $values
     */
    public function hydrate(
        FileSystemItem $item,
        array $values,
        string $parent,
    ): FileSystemItem {
        return $item
            ->setParent($parent)
            ->setType($values[FileSystemItemScanner::KEY_TYPE])
            ->setHasChildren((bool) $values[FileSystemItemScanner::KEY_HAS_CHILDREN])
            ->setSize((int) $values[FileSystemItemScanner::KEY_SIZE])
            ->setModifiedAt(
                (new DateTimeImmutable())->setTimestamp($values[FileSystemItemScanner::KEY_MODIFIED_AT])
            )
            ->setPermissions((string) $values[FileSystemItemScanner::KEY_PERMISSIONS]);
    }
}
