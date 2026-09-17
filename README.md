# symfony-file

Version: 4.0.0

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

## Table of Contents

- [Installation](#installation)
- [Declaring the trees it serves](#declaring-the-trees-it-serves)
- [Reading a level](#reading-a-level)
- [In a service](#in-a-service)
- [In a template](#in-a-template)
- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

The line that decides where a file goes runs between this package and ../php-file: reading the disk, matching paths and formatting sizes need no framework and live there; a container, an entity manager, a controller or a Twig environment is what makes something belong here. Nothing in `src/` reimplements what the other package already answers.

### The layers

src/WexampleSymfonyFileBundle.php extends `AbstractBundle` from `wexample/symfony-helpers` — template alias, bundle alias, asset paths.

src/DependencyInjection/WexampleSymfonyFileExtension.php loads src/Resources/config/services.yaml and sets one parameter, `wexample_symfony_file.roots`, from src/DependencyInjection/Configuration.php. That parameter is injected into src/Service/FileSystemItemScannerFactory.php and read nowhere else.

src/Entity/FileSystemItem.php is one entry of a tree as it stood when it was last read. The disk owns the truth and the row is an index of it — nothing is ever written back from here. Identity is `Uuid::v5` over the root *and* the path inside it, because the same relative path exists in every app.

src/Service/FileSystemItemIndexer.php keeps the table in step with the disk. src/Service/FileSystemItemHydrator.php translates one scanned entry into its row and knows nothing of where the values came from.

src/Api/Controller/FileSystemItemController.php serves one level, paginated, through the normalizer in `src/Api/Normalizer/`. src/Twig/FileSizeExtension.php registers the `file_size` filter.

### Why an index rather than a watcher

A level is read from the disk the first time somebody opens it and from the table afterwards, and `childrenScannedAt` being null is the whole of the invalidation. It costs nothing to arrange, because the explorer already asks for one directory at a time: the laziness that was there for the browser is the one that indexes.

A watcher was not chosen on purpose. A `git checkout` replaces hundreds of files without a single usable event — an index that re-reads absorbs that, where a watcher would produce noise.

`sweep()` drops the root's rows and writes them all back in batches rather than reconciling: a file that is gone leaves no trace to find, and telling which rows those are costs more than writing them all.

### Adding to the container

src/Resources/config/services.yaml registers `Repository` and `Service` as a resource glob, controllers and normalizers by tag, `Twig/` as extensions. A glob whose directory does not exist makes the container fail to compile, so a new directory under `src/` is declared there at the moment it is created.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.5
- wexample/php-file: >=2.0.0
- wexample/symfony-helpers: >=8.0.0
- wexample/symfony-api: >=5.0.0
- wexample/php-pseudocode: >=1.0.0
- wexample/symfony-pseudocode: >=3.0.0
- symfony/uid: >=6.2

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
