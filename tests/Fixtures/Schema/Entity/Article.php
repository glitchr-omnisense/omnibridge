<?php

namespace Omnibridge\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;

/** A class of the joined hierarchy: its own table, keyed by the root's identifier. */
#[ORM\Entity]
class Article extends Thread
{
    #[ORM\Column(length: 100, nullable: true)]
    protected ?string $title = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'SET NULL')]
    protected ?User $author = null;

    /** @var array<Article> a relation of the class to itself: the join table names its columns source and target */
    #[ORM\ManyToMany(targetEntity: Article::class)]
    protected array $connexes = [];
}
