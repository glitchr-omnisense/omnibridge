<?php

namespace Omnibridge\Tests\Fixtures\Schema\Referential;

use Doctrine\ORM\Mapping as ORM;

/**
 * A foreign key per ON DELETE an entity may declare: the ones Doctrine DBAL reads as no action (RESTRICT, NO ACTION,
 * in any case), the ones it keeps (in upper case), none.
 */
#[ORM\Entity]
class Holding
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    protected ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    protected ?Owner $restricted = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'restrict')]
    protected ?Owner $restrictedLower = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'NO ACTION')]
    protected ?Owner $noAction = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'no action')]
    protected ?Owner $noActionLower = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'cascade')]
    protected ?Owner $cascaded = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'set null')]
    protected ?Owner $setNull = null;

    #[ORM\ManyToOne]
    protected ?Owner $plain = null;
}
