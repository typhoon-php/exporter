# Typhoon Exporter

[![Latest Stable Version](https://poser.pugx.org/typhoon/exporter/v/stable.png)](https://packagist.org/packages/typhoon/exporter)
[![Total Downloads](https://poser.pugx.org/typhoon/exporter/downloads.png)](https://packagist.org/packages/typhoon/exporter)
[![psalm-level](https://shepherd.dev/github/typhoon-php/exporter/level.svg)](https://shepherd.dev/github/typhoon-php/exporter)
[![type-coverage](https://shepherd.dev/github/typhoon-php/exporter/coverage.svg)](https://shepherd.dev/github/typhoon-php/exporter)
[![Code Coverage](https://codecov.io/gh/typhoon-php/exporter/branch/0.2.x/graph/badge.svg)](https://codecov.io/gh/typhoon-php/exporter/tree/0.2.x)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Ftyphoon-php%2Fexporter%2F0.2.x)](https://dashboard.stryker-mutator.io/reports/github.com/typhoon-php/exporter/0.2.x)

## Installation

`composer require typhoon/exporter`

## Usage

```php
use Typhoon\Exporter\Exporter;

$exported = Exporter::export($value);

file_put_contents('code.php', '<?php return '.$exported.';');

\assert(require_once 'code.php' == $value);
```
