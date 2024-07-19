PSR-7 Multipart Stream Builder
==============================

[![Latest Version](https://img.shields.io/github/release/kodus/multipart-stream-builder.svg?style=flat-square)](https://github.com/kodus/multipart-stream-builder/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build Status](https://github.com/kodus/multipart-stream-builder/actions/workflows/tests.yml/badge.svg)](https://github.com/php-http/multipart-stream-builder/actions/workflows/tests.yml)

*A slightly more decoupled version of [php-http/multipart-stream-builder](https://github.com/php-http/multipart-stream-builder) - This version does not auto-bootstrap dependencies on PSR-17
implementations and most importantly, does not require [php-http/discovery](https://github.com/php-http/discovery). This is deferred to the projects that wish to utilize the multipart-stream-builder,
allowing for fewer dependencies between individual packages.*

**A builder for Multipart PSR-7 Streams. The builder create streams independently form any PSR-7 implementation.**

## Install

Via Composer

``` bash
$ composer require php-http/multipart-stream-builder
```

## Documentation

The main difference between `kodus/multipart-stream-builder` and `php-http/multipart-stream-builder` is that `kodus/multipart-stream-builder` requires you to provide an implementation of the
`Psr\Http\Message\StreamFactoryInterface` (PSR-17) up front. You will need an implementing library - we recommend using [nyholm/psr-7](https://github.com/nyholm/psr7). You can also choose to use
`php-http/discovery` for this, as shown in the original documentation, but you will need to require it as it does follow with this version of the library.

```php
use Http\Message\MultipartStream\MultipartStreamBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;

$multipartStreamBuilder = new MultipartStreamBuilder(new Psr17Factory());
```

Since no other functionality is changed from the original library, we refer to the php-http documentation for instructions on usage.

Please see the [official documentation](http://php-http.readthedocs.org/en/latest/components/multipart-stream-builder.html).


## Contributing

At this point, you should probably still contribute any improvements you have to the original library instead of here. We will aim to keep this fork up-to-date with the original.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
