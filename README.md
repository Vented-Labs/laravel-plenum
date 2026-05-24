# Application-layer routing for Laravel. Pin requests to specific backing connections (database, Redis, custom) using consistent hashing, pluggable routing strategies, active health checks, and automatic failover.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/vented-labs/laravel-plenum.svg?style=flat-square)](https://packagist.org/packages/vented-labs/laravel-plenum)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/vented-labs/laravel-plenum/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/vented-labs/laravel-plenum/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/vented-labs/laravel-plenum/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/vented-labs/laravel-plenum/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/vented-labs/laravel-plenum.svg?style=flat-square)](https://packagist.org/packages/vented-labs/laravel-plenum)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-plenum.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-plenum)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require vented-labs/laravel-plenum
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-plenum-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-plenum-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-plenum-views"
```

## Usage

```php
$plenum = new Vented\Plenum();
echo $plenum->echoPhrase('Hello, Vented!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Mason Curry](https://github.com/masoncurry)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
