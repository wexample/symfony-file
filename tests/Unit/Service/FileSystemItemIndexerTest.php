<?php

declare(strict_types=1);

namespace Wexample\SymfonyFile\Tests\Unit\Service;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Wexample\PhpFile\Class\FileSystemItemScanner;
use Wexample\PhpFile\Enum\FileSystemItemType;
use Wexample\SymfonyFile\Entity\FileSystemItem;
use Wexample\SymfonyFile\Repository\FileSystemItemRepository;
use Wexample\SymfonyFile\Service\FileSystemItemHydrator;
use Wexample\SymfonyFile\Service\FileSystemItemIndexer;

class FileSystemItemIndexerTest extends TestCase
{
    private string $tree;

    protected function setUp(): void
    {
        $this->tree = sys_get_temp_dir().'/'.uniqid('indexer', true);
        mkdir($this->tree.'/sub', recursive: true);
        file_put_contents($this->tree.'/a.txt', 'hello');
        file_put_contents($this->tree.'/sub/b.txt', 'b');
    }

    protected function tearDown(): void
    {
        unlink($this->tree.'/sub/b.txt');
        unlink($this->tree.'/a.txt');
        rmdir($this->tree.'/sub');
        rmdir($this->tree);
    }

    public function testALevelIsReadFromTheTableOnceAndItsRowsUpdated(): void
    {
        $scanner = new FileSystemItemScanner($this->tree);
        $root = $scanner->getAbsoluteRootPath();
        $known = new FileSystemItem($root, 'a.txt');
        $directory = (new FileSystemItem($root, ''))->setType(FileSystemItemType::DIRECTORY);

        $items = $this->createPartialMock(FileSystemItemRepository::class, ['findLevel', 'findOneByLocation']);
        $items->expects($this->once())->method('findLevel')->with($root, '')->willReturn([$known]);
        $items->expects($this->never())->method('findOneByLocation');

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (FileSystemItem $item) use (&$persisted): void {
            $persisted[$item->getPath()] = $item;
        });
        $entityManager->expects($this->once())->method('flush');

        (new FileSystemItemIndexer($items, new FileSystemItemHydrator(), $entityManager))
            ->index($scanner, $root, '', $directory);

        $this->assertEqualsCanonicalizing(['a.txt', 'sub', ''], array_keys($persisted));
        // The row the table held is the one written, not a second one.
        $this->assertSame($known, $persisted['a.txt']);
        $this->assertSame(5, $known->getSize());
        $this->assertSame(FileSystemItemType::DIRECTORY, $persisted['sub']->getType());
        $this->assertNotNull($directory->getChildrenScannedAt());
    }

    public function testASweepDropsAndWritesTheTreeInOneTransaction(): void
    {
        $scanner = new FileSystemItemScanner($this->tree);
        $inTransaction = false;
        $calls = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('transactional')->willReturnCallback(
            static function (Closure $work) use ($connection, &$inTransaction): mixed {
                $inTransaction = true;

                try {
                    return $work($connection);
                } finally {
                    $inTransaction = false;
                }
            }
        );

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $items = $this->createPartialMock(FileSystemItemRepository::class, ['removeRoot', 'insertMany']);
        $items->expects($this->once())->method('removeRoot')->willReturnCallback(static function () use (&$inTransaction, &$calls): int {
            $calls[] = ['remove', $inTransaction];

            return 0;
        });
        $items->expects($this->once())->method('insertMany')->willReturnCallback(static function (array $rows) use (&$inTransaction, &$calls): void {
            $calls[] = ['insert', $inTransaction, count($rows)];
        });

        $count = (new FileSystemItemIndexer($items, new FileSystemItemHydrator(), $entityManager))->sweep($scanner);

        $this->assertSame(3, $count);
        // The root, `a.txt`, `sub` and `sub/b.txt`, in one batch.
        $this->assertSame([['remove', true], ['insert', true, 4]], $calls);
    }
}
