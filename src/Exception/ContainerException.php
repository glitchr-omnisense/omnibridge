<?php

namespace Omnibridge\Exception;

use Psr\Container\ContainerExceptionInterface;

/** Omnibridge\Container could not build a service: a parameter it cannot autowire, a cycle, a class that cannot be instantiated. */
class ContainerException extends \LogicException implements ContainerExceptionInterface
{
}
