<?php

namespace Wexample\SymfonyFile\Entity\Traits\Manipulator;

use Wexample\SymfonyFile\Entity\FileSystemItemEntity;
use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;

trait FileSystemItemEntityManipulatorTrait
{
    use EntityManipulatorTrait;

    public static function getEntityClassName(): string
    {
        return FileSystemItemEntity::class;
    }
}
