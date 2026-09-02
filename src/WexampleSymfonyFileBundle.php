<?php

namespace Wexample\SymfonyFile;

use Wexample\SymfonyHelpers\Class\AbstractBundle;
use Wexample\SymfonyPseudocode\Interface\PseudocodeBundleInterface;

class WexampleSymfonyFileBundle extends AbstractBundle implements PseudocodeBundleInterface
{
    public static function getPseudocodeSourcePaths(): array
    {
        return [__DIR__.'/'];
    }
}
