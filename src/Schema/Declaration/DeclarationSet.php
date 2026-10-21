<?php

namespace Omnibridge\Schema\Declaration;

/** Declarations kept as data, by class: read back from their plain form (toArray(), a JSON file), or gathered from another source. */
final class DeclarationSet implements Declarations
{
    /** @var array<string, ClassDeclaration> */
    private array $declarations = [];

    /** @param iterable<ClassDeclaration> $declarations */
    public function __construct(iterable $declarations = [])
    {
        foreach ($declarations as $declaration) {
            $this->declarations[$declaration->class] = $declaration;
        }
    }

    /** The declarations of these classes, as a reader reads them. @param iterable<string> $classes */
    public static function of(Declarations $declarations, iterable $classes): self
    {
        $set = [];
        foreach ($classes as $class) {
            if (null !== ($declaration = $declarations->read($class))) {
                $set[] = $declaration;
            }
        }

        return new self($set);
    }

    public function read(string|\ReflectionClass $class): ?ClassDeclaration
    {
        return $this->declarations[$class instanceof \ReflectionClass ? $class->getName() : $class] ?? null;
    }

    /** @return array<string, ClassDeclaration> */
    public function all(): array
    {
        return $this->declarations;
    }

    /** @return array<string, array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn (ClassDeclaration $declaration) => $declaration->toArray(), $this->declarations);
    }

    /** @param array<string, array<string, mixed>> $data */
    public static function fromArray(array $data): self
    {
        return new self(array_map(ClassDeclaration::fromArray(...), array_values($data)));
    }
}
