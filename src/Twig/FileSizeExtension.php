<?php

namespace Wexample\SymfonyFile\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Wexample\SymfonyHelpers\Helper\FileHelper;

class FileSizeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'file_size',
                FileHelper::formatBytes(...)
            ),
        ];
    }
}
