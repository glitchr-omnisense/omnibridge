<?php

namespace Omnibridge\Schema\Builder;

use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Declaration\ClassDeclaration;

/**
 * An application's own convention over the structure its classes declare -
 * what an ORM's listeners add to its metadata (omnibase: the translations'
 * relations, the discriminator values, a vault's unique key) - applied to
 * each class while the description is built, at the three moments an ORM
 * gives its listeners: before the class's declaration is read (its
 * inheritance and its parent's state are there), once it is read, and once
 * the class is whole (Doctrine's loadClassMetadata).
 */
interface Rule
{
    public function before(Draft $class, ClassDeclaration $declaration, Builder $builder): void;

    public function declared(Draft $class, ClassDeclaration $declaration, Builder $builder): void;

    public function loaded(Draft $class, ClassDeclaration $declaration, Builder $builder): void;
}
