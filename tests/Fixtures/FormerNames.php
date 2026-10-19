<?php

namespace Omnibridge\Tests\Fixtures;

use Omnibridge\Contract\Urls;
use Omnibridge\Runtime;

/** A contract's former name, kept by Runtime::alias() as a family keeps its own (Base\Contract\Urls). */
final class FormerNames
{
    public const URLS = 'Omnibridge\Tests\Fixtures\Former\Urls';
}

Runtime::alias(Urls::class, FormerNames::URLS);
