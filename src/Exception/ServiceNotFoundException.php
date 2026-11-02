<?php

namespace Omnibridge\Exception;

use Psr\Container\NotFoundExceptionInterface;

/** Omnibridge\Container has no service (or no parameter) of that name. */
final class ServiceNotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
