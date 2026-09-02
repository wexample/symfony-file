<?php

namespace Wexample\SymfonyFile\Entity;

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
}
