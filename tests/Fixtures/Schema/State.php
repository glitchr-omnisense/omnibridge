<?php

namespace Omnibridge\Tests\Fixtures\Schema;

/** A backed enumeration a typed property maps to a string column. */
enum State: string
{
    case Draft = 'draft';
    case Published = 'published';
}
