<?php

namespace Omnibridge\Schema\Declaration;

/**
 * Where the declarations of the entity classes come from: their attributes
 * (AttributeReader), or a set kept as data (DeclarationSet: a file written
 * once, another vocabulary turned into this one). A persistence bridge maps
 * the classes from it - omnibridge/doctrine's DescriptionDriver gives Doctrine
 * its metadata from it - and never reads the classes' attributes itself.
 */
interface Declarations
{
    /** The class's declaration, null for a class it does not map. */
    public function read(string|\ReflectionClass $class): ?ClassDeclaration;
}
