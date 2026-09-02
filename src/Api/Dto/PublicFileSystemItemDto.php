<?php

namespace Wexample\SymfonyFile\Api\Dto;

use Wexample\SymfonyApi\Api\Dto\AbstractEntityDto;
use Wexample\SymfonyFile\Entity\FileSystemItemEntity;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;

class PublicFileSystemItemDto extends AbstractEntityDto
{
    public string $path;

    public string $name;

    public string $type;

    /**
     * @param FileSystemItemEntity $entity
     */
    public static function fromEntity(AbstractEntity $entity): self
    {
        $dto = parent::fromEntity($entity);

        $dto->path = $entity->getPath();
        $dto->name = $entity->getName();
        $dto->type = $entity->getType()->value;

        return $dto;
    }
}
