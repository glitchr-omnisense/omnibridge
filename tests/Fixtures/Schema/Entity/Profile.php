<?php

namespace Omnibridge\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Profile
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 30)]
    protected string $nick = '';

    #[ORM\OneToOne(mappedBy: 'profile')]
    protected ?User $user = null;
}
