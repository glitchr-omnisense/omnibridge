<?php

namespace Omnibridge\Tests\Fixtures\Schema\Referential;

use Doctrine\ORM\Mapping as ORM;

/** What Holding's foreign keys reference. */
#[ORM\Entity]
class Owner
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;
}
