<?php

namespace Omnibridge\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;
use Omnibridge\Tests\Fixtures\Schema\Translation;

/** The root of a hierarchy stored in one table, its map declared; a Translation for LocaleRule. */
#[ORM\Entity]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string', length: 20)]
#[ORM\DiscriminatorMap(['notice' => Notice::class, 'alert' => Alert::class])]
class Notice implements Translation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(type: 'text')]
    protected string $message = '';
}
