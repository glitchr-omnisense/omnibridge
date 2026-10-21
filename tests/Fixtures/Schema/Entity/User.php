<?php

namespace Omnibridge\Tests\Fixtures\Schema\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_balance', fields: ['balance'])]
class User
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(unique: true)]
    protected string $email = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    protected string $balance = '0.00';

    #[ORM\Column(options: ['default' => 0, 'unsigned' => true])]
    protected int $score = 0;

    #[ORM\OneToOne(inversedBy: 'user')]
    protected ?Profile $profile = null;
}
