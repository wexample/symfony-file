<?php

namespace Wexample\SymfonyFile\Api\Normalizer\Entity\FileSystemItem;

use ArrayObject;
use Wexample\SymfonyFile\Api\Dto\PublicFileSystemItemDto;
use Wexample\SymfonyFile\Entity\FileSystemItem;
use Wexample\SymfonyFile\Entity\Traits\Manipulator\FileSystemItemManipulatorTrait;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyHelpers\Interface\NormalizableDataInterface;
use Wexample\SymfonyHelpers\Normalizer\AbstractEntityNormalizer;

class DefaultFileSystemItemNormalizer extends AbstractEntityNormalizer
{
    use FileSystemItemManipulatorTrait;

    public function normalizeEntity(
        FileSystemItem|AbstractEntity $entity,
        ?string $format = null,
        array $context = []
    ): array|string|int|float|bool|ArrayObject|NormalizableDataInterface|null {
        return PublicFileSystemItemDto::fromEntity($entity);
    }
}
