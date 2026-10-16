# Installation

The package is licensed under MIT but is not published on Packagist and has no release.
For local evaluation, add it as a Composer path repository from a Laravel
application:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../lzk-laravel-duplicate-detector",
            "options": { "symlink": true }
        }
    ]
}
```

Then require the package using the local development version appropriate to the
workspace. Do not interpret that version as a published release.

```bash
composer require lzk/laravel-duplicate-detector:@dev
php artisan vendor:publish \
    --provider="Lzk\DuplicateDetector\DuplicateDetectorServiceProvider" \
    --tag=duplicate-detector-config
```

Laravel package discovery registers the provider. The provider merges and
publishes configuration and binds the resolver/manager as lazy singletons. It
does not connect to a database or compile model definitions during bootstrap.

Configure admitted models in `config/duplicate-detector.php`, following
[configuration.md](configuration.md). Every detection call must supply an
authorized Eloquent scope. No migrations or package-owned tables are required.

For package development:

```bash
composer install
PHP_INI_SCAN_DIR=:$PWD/.tools/sqlite/conf.d vendor/bin/phpunit
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpcs
```

The custom `PHP_INI_SCAN_DIR` is specific to this verification workspace. Normal
projects should use their regular PHP installation with PDO SQLite when running
the SQLite integration suite.
