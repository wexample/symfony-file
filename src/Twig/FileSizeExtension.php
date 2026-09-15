<?php

namespace Wexample\SymfonyFile\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Wexample\PhpFile\Helper\FileSizeHelper;

class FileSizeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'file_size',
                FileSizeHelper::format(...)
            ),
        ];
    }
}
