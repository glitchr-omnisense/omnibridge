<?php

namespace Omnibridge\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;
use Omnibridge\Tests\Fixtures\Schema\State;

/** The root of a hierarchy stored one table per class, its map generated. */
#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'class', type: 'string')]
class Thread extends Timestamps
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(unique: true)]
    protected string $slug = '';

    #[ORM\Column]
    protected State $state = State::Draft;

    /** @var array<Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'threads')]
    protected array $tags = [];
}
