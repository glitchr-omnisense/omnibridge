<?php

namespace Omnibridge\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;

/** A mapped superclass: its field and its index go to every entity below it. */
#[ORM\MappedSuperclass]
#[ORM\Index(name: 'idx_created', columns: ['createdAt'])]
abstract class Timestamps
{
    #[ORM\Column]
    protected \DateTimeImmutable $createdAt;
}
