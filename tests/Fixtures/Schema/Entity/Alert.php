<?php

namespace Omnibridge\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;

/** A class of the single-table hierarchy: its column nullable in the shared table. */
#[ORM\Entity]
class Alert extends Notice
{
    #[ORM\Column]
    protected int $level = 0;
}
