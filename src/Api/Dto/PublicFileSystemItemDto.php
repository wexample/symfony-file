<?php

namespace Wexample\SymfonyFile\Api\Dto;

use DateTimeInterface;
use Wexample\SymfonyApi\Api\Dto\AbstractEntityDto;
use Wexample\SymfonyFile\Entity\FileSystemItem;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

class PublicFileSystemItemDto extends AbstractEntityDto
{
    public string $path;

    public string $name;

    public string $type;

    public bool $hasChildren;

    public int $size;

    public ?string $modifiedAt;

    public string $permissions;

    /**
     * @param FileSystemItem $entity
     */
    public static function fromEntity(AbstractEntity $entity): self
    {
        $dto = parent::fromEntity($entity);

        $dto->path = $entity->getPath();
        $dto->name = $entity->getName();
        $dto->type = $entity->getType()->value;
        $dto->hasChildren = $entity->hasChildren();
        $dto->size = $entity->getSize();
        $dto->modifiedAt = $entity->getModifiedAt()?->format(DateTimeInterface::ATOM);
        $dto->permissions = $entity->getPermissions();

        return $dto;
    }
}
