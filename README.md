# PSR-11+ Dependency Injection Container

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bnf/di/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/bnf/di/?branch=master)
[![Build Status](https://api.travis-ci.org/bnf/di.png)](https://travis-ci.org/bnf/di)
[![Coverage Status](https://coveralls.io/repos/github/bnf/di/badge.svg)](https://coveralls.io/github/bnf/di)


Provides [PSR-11](www.php-fig.org/psr/psr-11/) and
[container-interop/service-provider](https://github.com/container-interop/service-provider) support.

## Installation

```sh
$ composer require bnf/di:~0.1.0
```

## Usage

```php
<?php
require 'vendor/autoload.php';

use Bnf\Di\Container;
use Psr\Container\ContainerInterface;
use Interop\Container\ServiceProviderInterface;

$container = new Container([new class implements ServiceProviderInterface {
    public function getFactories(): array
    {
        return [
            stdClass::class => function (ContainerInterface $container): stdClass {
                return new stdClass;
            }
        ];
    }
    public function getExtensions(): array
    {
        return [];
    }
}]);

$class = $container->get(stdClass::class);
var_dump($class);
```
