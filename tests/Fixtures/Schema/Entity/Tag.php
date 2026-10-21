<?php

namespace Omnibridge\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;

/** A class that names its table, and a unique constraint it names. */
#[ORM\Entity]
#[ORM\Table(name: 'threadTag')]
#[ORM\UniqueConstraint(name: 'uniq_tag_label', columns: ['label'])]
class Tag
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 64)]
    protected string $label = '';

    /** @var array<Thread> */
    #[ORM\ManyToMany(targetEntity: Thread::class, mappedBy: 'tags')]
    protected array $threads = [];
}
