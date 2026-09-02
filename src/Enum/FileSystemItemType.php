<?php

namespace Wexample\SymfonyFile\Enum;

enum FileSystemItemType: string
{
    case FILE = 'file';

    case DIRECTORY = 'directory';

    case LINK = 'link';

    public static function fromPath(string $absolutePath): self
    {
        return match (true) {
            is_link($absolutePath) => self::LINK,
            is_dir($absolutePath) => self::DIRECTORY,
            default => self::FILE,
        };
    }
}
