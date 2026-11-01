<?php

namespace Omnibridge\Persistence\Lifecycle;

use Omnibridge\Persistence\UnitOfWork;

/** A moment of a flush, for every entity it writes. */
abstract class FlushEvent
{
    public function __construct(public readonly UnitOfWork $unitOfWork)
    {
    }
}
