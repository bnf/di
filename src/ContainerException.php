<?php
declare(strict_types = 1);
namespace Bnf\Di;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class ContainerException extends Exception implements ContainerExceptionInterface
{
}
