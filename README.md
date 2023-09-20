# Typhoon Exporter

## Installation

`composer require typhoon/exporter`

## Usage

```php
use Typhoon\Exporter\Exporter;

$exported = Exporter::export($value);

file_put_contents('code.php', '<?php return '.$exported.';');

\assert(require_once 'code.php' == $value);
```
