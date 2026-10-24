<?php

namespace Omnibridge\Schema\Builder;

use Omnibridge\Schema\Naming;

/**
 * A class of the description while it is built: what its parent gave it,
 * then what its declaration adds, then what the rules add - each mapping in
 * plain arrays, completed as it is added (a column's name, a type guessed
 * from the property, a relation's join columns or join table), the way an
 * ORM's metadata complete themselves. The Builder turns it into an Entity
 * once it is whole; a Rule (an application's own conventions) changes it on
 * the way, as an ORM's listener would.
 *
 * Fields: property => {fieldName, columnName, type, length, precision, scale,
 * nullable, unique, index, options, id, enumType, inherited, declared};
 * relations: property => {fieldName, type (a Relation::* kind), targetEntity,
 * sourceEntity, mappedBy, inversedBy, isOwningSide, joinColumns (list of
 * {name, referencedColumnName, nullable, unique, onDelete}), joinTable ({name,
 * joinColumns, inverseJoinColumns}), orderBy, indexBy, id, inherited,
 * declared}. Indexes and unique constraints by name (or position): {columns}
 * or {fields}, flags, options.
 */
final class Draft
{
    public const NONE = 'none';

    /** @var list<string> the entities above it, the nearest first (not the mapped superclasses) */
    public array $parentClasses = [];
    public string $rootEntityName;
    public bool $isMappedSuperclass = false;
    public bool $isEmbeddedClass = false;

    /** @var array{name: string, indexes?: array<int|string, array<string, mixed>>, uniqueConstraints?: array<int|string, array<string, mixed>>, options?: array<string, mixed>} */
    public array $table;

    public string $inheritanceType = self::NONE;
    /** @var array{name: string, type: string, length: ?int}|null */
    public ?array $discriminatorColumn = null;
    /** @var array<string, string> value => class */
    public array $discriminatorMap = [];
    public ?string $discriminatorValue = null;
    /** @var list<string> */
    public array $subClasses = [];

    /** auto, identity, sequence, none or custom */
    public string $generatorType = self::NONE;
    /** @var list<string> */
    public array $identifier = [];
    /** @var array<string, int> */
    public array $identifierPositions = [];

    /** @var array<string, array<string, mixed>> */
    public array $fieldMappings = [];
    /** @var array<string, string> column => property */
    public array $fieldNames = [];
    /** @var array<string, array<string, mixed>> */
    public array $associationMappings = [];

    public function __construct(
        public readonly string $name,
        private readonly Naming $naming,
    ) {
        $this->rootEntityName = $name;
        $this->table = ['name' => $naming->classToTableName($name)];
    }

    public function naming(): Naming
    {
        return $this->naming;
    }

    public function isInheritanceTypeNone(): bool
    {
        return self::NONE === $this->inheritanceType;
    }

    public function isInheritanceTypeSingleTable(): bool
    {
        return 'single_table' === $this->inheritanceType;
    }

    public function isInheritanceTypeJoined(): bool
    {
        return 'joined' === $this->inheritanceType;
    }

    public function isRootEntity(): bool
    {
        return $this->name === $this->rootEntityName;
    }

    public function hasField(string $property): bool
    {
        return isset($this->fieldMappings[$property]);
    }

    public function hasAssociation(string $property): bool
    {
        return isset($this->associationMappings[$property]);
    }

    /** A field's mapping as declared ({fieldName, type?, columnName?, length?, nullable?, id?...}), completed and added. */
    public function mapField(array $mapping): void
    {
        $property = $mapping['fieldName'];
        if ($this->isTypedProperty($property)) {
            $mapping = $this->typedField($mapping, new \ReflectionProperty($this->name, $property));
        }

        $mapping['type'] ??= 'string';
        $mapping['columnName'] ??= $this->naming->propertyToColumnName($property, $this->name);
        $mapping += ['length' => null, 'precision' => null, 'scale' => null, 'nullable' => false, 'unique' => false, 'index' => false, 'options' => [], 'id' => false, 'enumType' => null, 'inherited' => null, 'declared' => null];

        if (isset($this->fieldNames[$mapping['columnName']]) || ($this->discriminatorColumn && $this->discriminatorColumn['name'] === $mapping['columnName'])) {
            throw new \LogicException(\sprintf('Duplicate column "%s" in %s.', $mapping['columnName'], $this->name));
        }

        $this->fieldNames[$mapping['columnName']] = $property;
        if ($mapping['id'] && !\in_array($property, $this->identifier, true)) {
            $this->registerIdentifier($property, $mapping['idPosition'] ?? 0);
        }

        unset($mapping['idPosition']);
        $this->fieldMappings[$property] = $mapping;
    }

    /** A relation's mapping as declared ({fieldName, type, targetEntity?, mappedBy?, inversedBy?, joinColumns?, joinTable?...}), completed and added. */
    public function mapRelation(array $mapping): void
    {
        $property = $mapping['fieldName'];
        $mapping['sourceEntity'] = $this->name;
        $toOne = 'one_to_one' === $mapping['type'] || 'many_to_one' === $mapping['type'];

        if ($toOne && !isset($mapping['targetEntity']) && $this->isTypedProperty($property)) {
            $type = (new \ReflectionProperty($this->name, $property))->getType();
            if ($type instanceof \ReflectionNamedType) {
                $mapping['targetEntity'] = $type->getName();
            }
        }

        $target = $mapping['targetEntity'] ?? null;
        if (!\is_string($target)) {
            throw new \LogicException(\sprintf('No target for %s::$%s.', $this->name, $property));
        }
        $mapping['targetEntity'] = ltrim($target, '\\');
        if (!str_contains($mapping['targetEntity'], '\\') && str_contains($this->name, '\\')) {
            $mapping['targetEntity'] = substr($this->name, 0, strrpos($this->name, '\\') + 1).$mapping['targetEntity'];
        }

        $mapping['mappedBy'] = $mapping['mappedBy'] ?? null ?: null;
        $mapping['inversedBy'] = $mapping['inversedBy'] ?? null ?: null;
        $mapping['indexBy'] = $mapping['indexBy'] ?? null ?: null;
        $mapping['isOwningSide'] = null === $mapping['mappedBy'] && 'one_to_many' !== $mapping['type'];
        $mapping += ['orderBy' => [], 'id' => false, 'inherited' => null, 'declared' => null, 'joinColumns' => [], 'joinTable' => null];

        if ($mapping['id'] && !\in_array($property, $this->identifier, true)) {
            $this->registerIdentifier($property, $mapping['idPosition'] ?? 0);
        }
        unset($mapping['idPosition']);

        if ($toOne && $mapping['isOwningSide']) {
            $mapping['joinColumns'] = $this->joinColumns($mapping);
        } else {
            $mapping['joinColumns'] = [];
        }

        if ('many_to_many' === $mapping['type'] && $mapping['isOwningSide']) {
            $mapping['joinTable'] = $this->joinTable($mapping);
        } else {
            $mapping['joinTable'] = null;
        }

        $this->associationMappings[$property] = $mapping;
    }

    /** The default join column, or each one declared completed: its name, the column it references, nullable unless part of the identifier. */
    private function joinColumns(array $mapping): array
    {
        $joinColumns = $mapping['joinColumns'] ?: [['name' => null, 'referencedColumnName' => null]];
        foreach ($joinColumns as $i => $joinColumn) {
            $joinColumn['name'] = $joinColumn['name'] ?? null ?: $this->naming->joinColumnName($mapping['fieldName'], $this->name);
            $joinColumn['referencedColumnName'] = $joinColumn['referencedColumnName'] ?? null ?: $this->naming->referenceColumnName();
            $joinColumn['nullable'] = $mapping['id'] ? false : ($joinColumn['nullable'] ?? true);
            $joinColumn['unique'] = (bool) ($joinColumn['unique'] ?? false);
            if ('one_to_one' === $mapping['type'] && !$this->isInheritanceTypeSingleTable() && 1 === \count($joinColumns) && !$mapping['id']) {
                $joinColumn['unique'] = true;
            }
            $joinColumn['onDelete'] = $joinColumn['onDelete'] ?? null;
            $joinColumns[$i] = array_intersect_key($joinColumn, array_flip(['name', 'referencedColumnName', 'nullable', 'unique', 'onDelete']));
        }

        return array_values($joinColumns);
    }

    /** The join table of an owning many-to-many relation: its name, its columns toward the source, then the target. */
    private function joinTable(array $mapping): array
    {
        $joinTable = $mapping['joinTable'] ?? [];
        $reference = $this->naming->referenceColumnName();
        $complete = function (array $joinColumns, string $entity) use ($reference): array {
            foreach ($joinColumns as $i => $joinColumn) {
                $joinColumn['referencedColumnName'] = $joinColumn['referencedColumnName'] ?? null ?: $reference;
                $joinColumn['name'] = $joinColumn['name'] ?? null ?: $this->naming->joinKeyColumnName($entity, $joinColumn['referencedColumnName']);
                $joinColumn['nullable'] = false;
                $joinColumn['unique'] = (bool) ($joinColumn['unique'] ?? false);
                $joinColumn['onDelete'] = $joinColumn['onDelete'] ?? null;
                $joinColumns[$i] = array_intersect_key($joinColumn, array_flip(['name', 'referencedColumnName', 'nullable', 'unique', 'onDelete']));
            }

            return array_values($joinColumns);
        };

        $self = $mapping['sourceEntity'] === $mapping['targetEntity'] && empty($joinTable['joinColumns']) && empty($joinTable['inverseJoinColumns']);
        $joinColumns = $complete($joinTable['joinColumns'] ?? [], $mapping['sourceEntity']);
        $inverseJoinColumns = $complete($joinTable['inverseJoinColumns'] ?? [], $mapping['targetEntity']);

        return [
            'name' => $joinTable['name'] ?? null ?: $this->naming->joinTableName($mapping['sourceEntity'], $mapping['targetEntity'], $mapping['fieldName']),
            'joinColumns' => $joinColumns ?: [['name' => $this->naming->joinKeyColumnName($mapping['sourceEntity'], $self ? 'source' : null), 'referencedColumnName' => $reference, 'nullable' => false, 'unique' => false, 'onDelete' => 'CASCADE']],
            'inverseJoinColumns' => $inverseJoinColumns ?: [['name' => $this->naming->joinKeyColumnName($mapping['targetEntity'], $self ? 'target' : null), 'referencedColumnName' => $reference, 'nullable' => false, 'unique' => false, 'onDelete' => 'CASCADE']],
        ];
    }

    /** The parent's discriminator map added to this class's: its own value found there, its subclasses among them. */
    public function setDiscriminatorMap(array $map): void
    {
        foreach ($map as $value => $className) {
            $className = ltrim($className, '\\');
            $this->discriminatorMap[(string) $value] = $className;
            if ($className === $this->name) {
                $this->discriminatorValue = (string) $value;
            } else {
                $this->addSubClass($className);
            }
        }
    }

    public function addSubClass(string $className): void
    {
        if (is_subclass_of($className, $this->name) && !\in_array($className, $this->subClasses, true)) {
            $this->subClasses[] = $className;
        }
    }

    public function setIdentifier(array $identifier, array $positions): void
    {
        $this->identifier = [];
        $this->identifierPositions = [];
        foreach ($identifier as $property) {
            $this->registerIdentifier($property, $positions[$property] ?? 0);
        }
    }

    private function registerIdentifier(string $property, int $position): void
    {
        $this->identifier[] = $property;
        $this->identifierPositions[$property] = $position;
        usort($this->identifier, fn (string $a, string $b) => $this->identifierPositions[$a] <=> $this->identifierPositions[$b]);
    }

    private function isTypedProperty(string $property): bool
    {
        return property_exists($this->name, $property) && (new \ReflectionProperty($this->name, $property))->hasType();
    }

    /** The type a property's own type gives a field that names none (DateTime: datetime, int: integer, a backed enumeration its backing type). */
    private function typedField(array $mapping, \ReflectionProperty $property): array
    {
        $type = $property->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return $mapping;
        }

        $name = $type->getName();
        if (!$type->isBuiltin() && enum_exists($name) && !isset($mapping['type'])) {
            $backing = (new \ReflectionEnum($name))->getBackingType();
            if (!$backing instanceof \ReflectionNamedType) {
                throw new \LogicException(\sprintf('%s::$%s: a backed enumeration is required.', $property->class, $property->getName()));
            }
            $mapping['enumType'] = $name;
            $name = $backing->getName();
        }

        if (!isset($mapping['type'])) {
            $mapping['type'] = [
                \DateInterval::class => 'dateinterval',
                \DateTime::class => 'datetime',
                \DateTimeImmutable::class => 'datetime_immutable',
                'array' => 'json',
                'bool' => 'boolean',
                'float' => 'float',
                'int' => 'integer',
                'string' => 'string',
            ][$name] ?? null;
            if (null === $mapping['type']) {
                unset($mapping['type']);
            }
        }

        return $mapping;
    }
}
