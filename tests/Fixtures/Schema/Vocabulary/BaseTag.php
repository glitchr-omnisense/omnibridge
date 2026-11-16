<?php

namespace Omnibridge\Tests\Fixtures\Schema\Vocabulary;

use Base\Mapping as ORM;
use Omnibridge\Tests\Fixtures\Schema\Entity\Thread;

/**
 * Entity\Tag written in omnibase's own vocabulary (Base\Mapping, the core's src/Mapping, not installed here): the
 * same attributes, read as the same declaration.
 */
#[ORM\Entity]
#[ORM\Table(name: 'threadTag')]
#[ORM\UniqueConstraint(name: 'uniq_tag_label', columns: ['label'])]
class BaseTag
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 64)]
    protected string $label = '';

    /** @var array<Thread> */
    #[ORM\ManyToMany(targetEntity: Thread::class, mappedBy: 'tags')]
    protected array $threads = [];
}
