<?php

namespace Wexample\SymfonyFile\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Wexample\PhpFile\Enum\FileSystemItemType;
use Wexample\Pseudocode\Attribute\PseudocodeExport;
use Wexample\SymfonyFile\Repository\FileSystemItemRepository;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

/**
 * One entry of a tree, as it stood when it was last read.
 *
 * The disk owns what it says and this row is an index of it: a question about
 * one directory is answered by reading that directory, but a question crossing
 * the tree — a pattern, a search, a sort — is a query, and a query needs a
 * table. Nothing is ever written from here to the disk.
 *
 * Two things make a row rather than one: the root it was read under, and its
 * path inside that root. The same relative path exists in every app, so the
 * identity hashes both.
 */
#[PseudocodeExport(inherited: true)]
#[ORM\Entity(repositoryClass: FileSystemItemRepository::class)]
#[ORM\Table(name: 'file_system_item')]
#[ORM\UniqueConstraint(columns: ['root', 'path'])]
#[ORM\Index(columns: ['root', 'parent'])]
class FileSystemItem extends AbstractEntity
{
    /**
     * Fixed namespace the location is hashed under, so that reading the same
     * item twice yields the same identity, in another request or another
     * process.
     */
    public const ID_NAMESPACE = 'a890fa7b-9bf7-4552-b7fe-93f1ca0fbda0';

    /** Where the tree this item belongs to is mounted. */
    #[ORM\Column(type: Types::STRING, length: 512)]
    protected string $root;

    /** Where the item lies inside that root, empty for the root itself. */
    #[ORM\Column(type: Types::STRING, length: 1024)]
    protected string $path;

    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $name;

    /**
     * The directory holding it, as a path inside the root — empty for the
     * entries of the top level. Carried rather than derived, because it is what
     * turns a level into a query.
     */
    #[ORM\Column(type: Types::STRING, length: 1024)]
    protected string $parent = '';

    #[ORM\Column(type: Types::STRING, length: 16, enumType: FileSystemItemType::class)]
    protected FileSystemItemType $type;

    /**
     * Whether the item opens onto something. Carried rather than deduced, so
     * that a tree knows not to draw a chevron on an empty directory without
     * having to list it first.
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $hasChildren = false;

    /**
     * What a single stat of the item gives. Anything asking for its content —
     * a mime type, a line count — is left out on purpose: a listing builds one
     * item per entry, and would open every file to fill it.
     */
    #[ORM\Column(type: Types::BIGINT)]
    protected int $size = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $modifiedAt = null;

    /** Octal, as chmod writes it: 0644, 0755. */
    #[ORM\Column(type: Types::STRING, length: 8)]
    protected string $permissions = '';

    /**
     * When this directory last had its children read, and null for one nobody
     * opened yet. That null is the whole of the invalidation: a level is scanned
     * the first time it is asked for, and answered from the table afterwards.
     *
     * Always null on a file, which holds nothing to list.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $childrenScannedAt = null;

    public function __construct(
        string $root,
        string $path,
    ) {
        parent::__construct();

        $this->root = $root;
        $this->path = $path;
        // Held as a property and not only derived: the exported schema is built
        // from properties, and the API sends the name on the wire.
        $this->name = basename($path);
        $this->type = FileSystemItemType::FILE;

        $this->setId(self::idFor($root, $path));
    }

    /** The identity of one location, which is a root and a path inside it. */
    public static function idFor(
        string $root,
        string $path,
    ): Uuid {
        return Uuid::v5(
            Uuid::fromString(static::ID_NAMESPACE),
            $root."\0".$path
        );
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): string
    {
        return $this->parent;
    }

    public function setParent(string $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getType(): FileSystemItemType
    {
        return $this->type;
    }

    public function setType(FileSystemItemType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function hasChildren(): bool
    {
        return $this->hasChildren;
    }

    public function setHasChildren(bool $hasChildren): self
    {
        $this->hasChildren = $hasChildren;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getModifiedAt(): ?DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(?DateTimeImmutable $modifiedAt): self
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function getPermissions(): string
    {
        return $this->permissions;
    }

    public function setPermissions(string $permissions): self
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function getChildrenScannedAt(): ?DateTimeImmutable
    {
        return $this->childrenScannedAt;
    }

    public function setChildrenScannedAt(?DateTimeImmutable $childrenScannedAt): self
    {
        $this->childrenScannedAt = $childrenScannedAt;

        return $this;
    }
}
