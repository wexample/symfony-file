<?php

namespace Wexample\SymfonyFile\Entity;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;
use Wexample\Pseudocode\Attribute\PseudocodeExport;
use Wexample\SymfonyFile\Enum\FileSystemItemType;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

#[PseudocodeExport(inherited: true)]
class FileSystemItem extends AbstractEntity
{
    /**
     * Fixed namespace the path is hashed under, so that reading the same item
     * twice yields the same identity, in another request or another process.
     */
    public const ID_NAMESPACE = 'a890fa7b-9bf7-4552-b7fe-93f1ca0fbda0';

    protected string $name;

    public function __construct(
        protected string $path,
        protected FileSystemItemType $type,
        /**
         * Whether the item opens onto something. Carried rather than deduced, so
         * that a tree knows not to draw a chevron on an empty directory without
         * having to list it first.
         */
        protected bool $hasChildren = false,
        /**
         * What a single stat of the item gives. Anything asking for its content —
         * a mime type, a line count — is left out on purpose: a listing builds one
         * item per entry, and would open every file to fill it.
         */
        protected int $size = 0,
        protected ?DateTimeImmutable $modifiedAt = null,
        /** Octal, as chmod writes it: 0644, 0755. */
        protected string $permissions = '',
    ) {
        // Held as a property and not only derived: the exported schema is built
        // from properties, and the API sends the name on the wire.
        $this->name = basename($path);

        $this->setId(
            Uuid::v5(
                Uuid::fromString(static::ID_NAMESPACE),
                $path
            )
        );
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getType(): FileSystemItemType
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function hasChildren(): bool
    {
        return $this->hasChildren;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getModifiedAt(): ?DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function getPermissions(): string
    {
        return $this->permissions;
    }
}
