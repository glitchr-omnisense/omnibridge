<?php

namespace Omnibridge\Tests\Fixtures\Schema;

use Omnibridge\Schema\Builder;
use Omnibridge\Schema\Builder\Draft;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\Declaration\ClassDeclaration;

/**
 * An application's convention, as omnibase's listeners add one: a Translation
 * gets a locale column once its declaration is read, and a unique constraint
 * over it once the class is whole. The moments are recorded.
 */
final class LocaleRule implements Rule
{
    /** @var list<string> "moment class" */
    public array $moments = [];

    public function before(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        $this->moments[] = 'before '.$class->name;
    }

    public function declared(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        $this->moments[] = 'declared '.$class->name;
        if (is_subclass_of($class->name, Translation::class) && !$class->hasField('locale')) {
            $class->mapField(['fieldName' => 'locale', 'type' => 'string', 'length' => 5]);
        }
    }

    public function loaded(Draft $class, ClassDeclaration $declaration, Builder $builder): void
    {
        $this->moments[] = 'loaded '.$class->name;
        if (is_subclass_of($class->name, Translation::class) && $class->isRootEntity()) {
            $class->table['uniqueConstraints'][$class->table['name'].'_unique_translation']['columns'] = ['locale'];
        }
    }
}
