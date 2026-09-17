`wexample/symfony-file` is the Symfony side of the suite's file handling: it indexes a tree into a table, serves one level of it over an API, and prints a size in a template. Everything that owes nothing to the framework — reading the disk, matching paths, formatting bytes — lives in ../php-file, which this bundle requires.

## Installation

```bash
composer require wexample/symfony-file
```

Then register the bundle in `config/bundles.php`:

```php
Wexample\SymfonyFile\WexampleSymfonyFileBundle::class => ['all' => true],
```

## Declaring the trees it serves

A root is a name and an absolute path. Nothing is served that was not named:

```yaml
# config/packages/wexample_symfony_file.yaml
wexample_symfony_file:
    roots:
        project: '%kernel.project_dir%'
        uploads: '%kernel.project_dir%/var/uploads'
```

The default is the one root above, `project`. src/Service/FileSystemItemScannerFactory.php hands out a scanner per name and answers null for anything else — an application serving trees it only discovers at runtime builds its own scanner instead of asking here.

## Reading a level

```
GET /api/file-system-item/{root}/list?parent=src&page=1&length=1000
```

src/Api/Controller/FileSystemItemController.php answers one directory, paginated. The level is read from the disk the first time it is asked for and from the table every time after — see src/Service/FileSystemItemIndexer.php. The `length` cap is there for the levels nobody wrote by hand: a `node_modules` must not be scanned, serialised and sent in one piece because someone opened it.

## In a service

```php
use Wexample\SymfonyFile\Service\FileSystemItemIndexer;
use Wexample\SymfonyFile\Service\FileSystemItemScannerFactory;

$scanner = $scannerFactory->getScanner('project');

$items = $indexer->level($scanner, 'src');   // one directory
$item  = $indexer->one($scanner, 'src/Kernel.php');
$total = $indexer->sweep($scanner);          // the whole tree, in batches
```

`sweep()` is what makes a question crossing the tree — a search, a selection, a sort — answerable without opening every directory first. It drops what it held for that root and writes it all back, since a file that is gone leaves no trace to find.

## In a template

```twig
{{ item.size|file_size }}   
```

The filter is registered by src/Twig/FileSizeExtension.php and does nothing but call `FileSizeHelper::format()` from ../php-file.
