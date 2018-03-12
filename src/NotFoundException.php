<?php
declare(strict_types = 1);
namespace Bnf\Di;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
}
