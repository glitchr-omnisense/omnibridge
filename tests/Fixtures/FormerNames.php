<?php

namespace Omnibridge\Tests\Fixtures;

use Omnibridge\Contract\Urls;

/** A contract's former name, kept by class_alias as a family keeps its own (Base\Contract\Urls). */
final class FormerNames
{
    public const URLS = 'Omnibridge\Tests\Fixtures\Former\Urls';
}

class_alias(Urls::class, FormerNames::URLS);
