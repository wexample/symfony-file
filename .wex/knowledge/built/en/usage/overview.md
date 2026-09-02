`wexample/symfony-file` is a Symfony bundle meant to hold the file handling shared across the suite. It currently ships nothing but its own registration: installing it declares the bundle and its extension, which loads src/Resources/config/services.yaml into the container. Services will be added under `src/Service/` as the subject takes shape.

## Installation

```bash
composer require wexample/symfony-file
```

Then register the bundle in `config/bundles.php`:

```php
Wexample\SymfonyFile\WexampleSymfonyFileBundle::class => ['all' => true],
```
