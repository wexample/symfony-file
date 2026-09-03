# symfony-file

Version: 2.0.0

`wexample/symfony-file` is a Symfony bundle meant to hold the file handling shared across the suite. It currently ships nothing but its own registration: installing it declares the bundle and its extension, which loads src/Resources/config/services.yaml into the container. Services will be added under `src/Service/` as the subject takes shape.

## Installation

```bash
composer require wexample/symfony-file
```

Then register the bundle in `config/bundles.php`:

```php
Wexample\SymfonyFile\WexampleSymfonyFileBundle::class => ['all' => true],
```

## Table of Contents

- [Installation](#installation)
- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

The package holds a single layer for now: the Symfony integration that puts it in the container.

src/WexampleSymfonyFileBundle.php extends `AbstractBundle` from `wexample/symfony-helpers`, which provides the standard bundle wiring — template alias, bundle alias, asset paths.

src/DependencyInjection/WexampleSymfonyFileExtension.php extends `AbstractWexampleSymfonyExtension` and implements `load()` with a single call to `$this->loadConfig(__DIR__, $container)`, which reads src/Resources/config/services.yaml. The parent `prepend()` registers a Doctrine mapping only if a `src/Entity/` directory exists, so no entity configuration is needed until one does.

src/Resources/config/services.yaml declares `_defaults` (`autowire`, `autoconfigure`, `public: false`) and nothing else. The first service directory added to `src/` — `Service/`, `Command/`, `Controller/` — is registered there as a resource glob at the same time it is created: a glob whose directory does not exist makes the container fail to compile.

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.2
- wexample/symfony-helpers: >=6.0.0
- wexample/symfony-api: >=4.0.0
- wexample/php-pseudocode: >=1.0.0
- wexample/symfony-pseudocode: >=2.0.0
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
