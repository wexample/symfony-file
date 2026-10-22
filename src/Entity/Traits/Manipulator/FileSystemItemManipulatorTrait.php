<?php

namespace Wexample\SymfonyFile\Entity\Traits\Manipulator;

use Wexample\SymfonyFile\Entity\FileSystemItem;
use Wexample\SymfonyHelpers\Entity\Traits\Manipulator\EntityManipulatorTrait;

trait FileSystemItemManipulatorTrait
{
    use EntityManipulatorTrait;

    public static function getEntityClassName(): string
    {
        return FileSystemItem::class;
    }
}
