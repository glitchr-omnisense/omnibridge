<?php

namespace Omnibridge\Schema\Declaration;

/**
 * Reads an entity class's mapping attributes into its ClassDeclaration, by
 * reflection and without instantiating them: an attribute is its name and its
 * arguments (ReflectionAttribute::getArguments()), so its class need not
 * exist - the ORM need not be installed to read what the entities declare.
 *
 * Only the attributes of the vocabulary (Vocabulary::NAMESPACE) are read; a
 * property or a method that carries none is left out. A constant written as
 * an argument (UtcDateTimeImmutableType::NAME) is evaluated by PHP, so its
 * class must load.
 */
final class AttributeReader implements Declarations
{
    /** @var array<string, ClassDeclaration|false> */
    private array $declarations = [];

    /** The class's declaration, null for a class that is no entity, mapped superclass nor embeddable. */
    public function read(string|\ReflectionClass $class): ?ClassDeclaration
    {
        $reflection = $class instanceof \ReflectionClass ? $class : new \ReflectionClass($class);
        $name = $reflection->getName();

        if (!\array_key_exists($name, $this->declarations)) {
            $declaration = $this->declare($reflection);
            $this->declarations[$name] = null === $declaration->kind() ? false : $declaration;
        }

        return $this->declarations[$name] ?: null;
    }

    private function declare(\ReflectionClass $reflection): ClassDeclaration
    {
        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            if ($attributes = self::attributes($property->getAttributes())) {
                $properties[] = new Member($property->getName(), $property->class, $attributes);
            }
        }

        $methods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($attributes = self::attributes($method->getAttributes())) {
                $methods[] = new Member($method->getName(), $method->class, $attributes);
            }
        }

        return new ClassDeclaration($reflection->getName(), self::attributes($reflection->getAttributes()), $properties, $methods);
    }

    /**
     * @param list<\ReflectionAttribute<object>> $attributes
     *
     * @return list<Attribute>
     */
    private static function attributes(array $attributes): array
    {
        $read = [];
        foreach ($attributes as $attribute) {
            $name = Vocabulary::nameOf($attribute->getName());
            if (null !== $name) {
                $read[] = new Attribute($name, self::arguments($name, $attribute->getArguments()));
            }
        }

        return $read;
    }

    /**
     * The arguments by parameter name: a positional one named after the parameter it fills.
     *
     * @param array<int|string, mixed> $arguments
     *
     * @return array<string, mixed>
     */
    private static function arguments(string $name, array $arguments): array
    {
        $parameters = Vocabulary::parameters($name);
        $named = [];
        foreach ($arguments as $key => $value) {
            $named[\is_int($key) ? ($parameters[$key] ?? (string) $key) : $key] = self::value($value);
        }

        return $named;
    }

    /** A nested attribute (new JoinColumn(...) in a JoinTable) as an Attribute of its properties. */
    private static function value(mixed $value): mixed
    {
        if (\is_array($value)) {
            return array_map(self::value(...), $value);
        }

        if (\is_object($value) && null !== ($name = Vocabulary::nameOf($value::class))) {
            $arguments = [];
            foreach (Vocabulary::parameters($name) as $parameter) {
                if (property_exists($value, $parameter) && (new \ReflectionProperty($value, $parameter))->isInitialized($value)) {
                    $arguments[$parameter] = self::value($value->$parameter);
                }
            }

            return new Attribute($name, array_filter($arguments, static fn (mixed $argument, string $parameter) => $argument !== Vocabulary::defaultOf($name, $parameter), \ARRAY_FILTER_USE_BOTH));
        }

        return $value;
    }
}
