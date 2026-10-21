<?php

namespace Omnibridge\Schema;

use Omnibridge\Schema\Builder\Draft;
use Omnibridge\Schema\Builder\Rule;
use Omnibridge\Schema\Builder\Tables;
use Omnibridge\Schema\Declaration\Attribute;
use Omnibridge\Schema\Declaration\AttributeReader;
use Omnibridge\Schema\Declaration\ClassDeclaration;
use Omnibridge\Schema\Declaration\Declarations;

/**
 * The description of a set of entity classes, built from their declarations
 * alone - no ORM asked: each class completed the way Doctrine completes its
 * metadata (its parent's fields and relations inherited, a column named by
 * the naming, a type guessed from the property's, a relation's default join
 * columns and join table, the inheritance and its discriminator map), the
 * application's rules applied at the moments Doctrine's listeners act, then
 * the tables those classes make, as Doctrine's SchemaTool would create them
 * (Builder\Tables).
 *
 * The same description as omnibridge/doctrine's MetadataDescription reads
 * from an entity manager over the same classes: that is the proof that the
 * structure is described outside the ORM, and that another persistence can
 * map the same tables from it.
 *
 * A generated identifier with the AUTO strategy is the database's own
 * (identity: an auto-increment column, MySQL's); $autoGenerator says another.
 */
final class Builder
{
    /** @var array<string, Draft> */
    private array $drafts = [];

    /** @var list<string> */
    private array $classes = [];

    /** @param list<Rule> $rules */
    public function __construct(
        private readonly Declarations $declarations = new AttributeReader(),
        private readonly Naming $naming = new CamelCaseNaming(),
        private readonly array $rules = [],
        private readonly string $autoGenerator = 'identity',
    ) {
    }

    /** @param iterable<string> $classNames the classes to describe (a folder's, an ORM driver's): the entities, their mapped superclasses */
    public function build(iterable $classNames): Description
    {
        $this->drafts = [];
        $this->classes = [];
        foreach ($classNames as $className) {
            if (null !== $this->declarations->read($className)) {
                $this->classes[] = (new \ReflectionClass($className))->getName();
            }
        }
        $this->classes = array_values(array_unique($this->classes));

        $sorted = $this->classes;
        sort($sorted);
        $entities = [];
        foreach ($sorted as $className) {
            $entities[$className] = $this->entity($this->draft($className));
        }

        $drafts = array_map(fn (string $className) => $this->draft($className), $sorted);

        return new Description($entities, (new Tables($this->naming, $this))->of($drafts));
    }

    /** @return list<string> the classes described, in the order they were given */
    public function classes(): array
    {
        return $this->classes;
    }

    public function naming(): Naming
    {
        return $this->naming;
    }

    public function declaration(string $className): ?ClassDeclaration
    {
        return class_exists($className) || interface_exists($className) ? $this->declarations->read($className) : null;
    }

    /** The class as described so far, its parents first: what an ORM's metadata factory hands out. */
    public function draft(string $className): Draft
    {
        $className = (new \ReflectionClass($className))->getName();
        if (isset($this->drafts[$className])) {
            return $this->drafts[$className];
        }

        // The classes above it that the mapping holds (entities, mapped superclasses), the farthest first.
        $chain = [$className];
        for ($parent = get_parent_class($className); false !== $parent; $parent = get_parent_class($parent)) {
            $kind = $this->declaration($parent)?->kind();
            if ('Entity' === $kind || 'MappedSuperclass' === $kind) {
                array_unshift($chain, $parent);
            }
        }

        $parent = null;
        $rootEntityFound = false;
        $nonSuperclassParents = [];
        foreach ($chain as $name) {
            if (!isset($this->drafts[$name])) {
                $draft = new Draft($name, $this->naming);
                $this->drafts[$name] = $draft;
                $this->load($draft, $parent, $rootEntityFound, $nonSuperclassParents);
            }

            $parent = $this->drafts[$name];
            if (!$parent->isMappedSuperclass) {
                $rootEntityFound = true;
                array_unshift($nonSuperclassParents, $name);
            }
        }

        return $this->drafts[$className];
    }

    /** ClassMetadataFactory::doLoadMetadata(): the parent's state, the declaration, the identifier's generator, the inheritance. */
    private function load(Draft $class, ?Draft $parent, bool $rootEntityFound, array $nonSuperclassParents): void
    {
        $declaration = $this->declarations->read($class->name) ?? throw new \LogicException(\sprintf('No declaration for %s.', $class->name));

        if (null !== $parent) {
            $class->inheritanceType = $parent->inheritanceType;
            $class->discriminatorColumn = $parent->discriminatorColumn;
            $class->generatorType = $parent->generatorType;
            foreach ($parent->fieldMappings as $property => $mapping) {
                $mapping['inherited'] ??= $parent->isMappedSuperclass ? null : $parent->name;
                $mapping['declared'] ??= $parent->name;
                $class->fieldMappings[$property] = $mapping;
                $class->fieldNames[$mapping['columnName']] = $property;
            }
            foreach ($parent->associationMappings as $property => $mapping) {
                $mapping['inherited'] ??= $parent->isMappedSuperclass ? null : $parent->name;
                $mapping['declared'] ??= $parent->name;
                if (null === $mapping['inherited']) {
                    $mapping['sourceEntity'] = $class->name;
                }
                $class->associationMappings[$property] = $mapping;
            }
            $class->setIdentifier($parent->identifier, $parent->identifierPositions);
            $class->setDiscriminatorMap($parent->discriminatorMap);
            foreach ($parent->subClasses as $subClass) {
                $class->addSubClass($subClass);
            }
        }

        foreach ($this->rules as $rule) {
            $rule->before($class, $declaration, $this);
        }

        $this->declare($class, $declaration);

        foreach ($this->rules as $rule) {
            $rule->declared($class, $declaration, $this);
        }

        if (null !== $parent && $rootEntityFound) {
            if (Draft::NONE !== $parent->generatorType) {
                $class->generatorType = $parent->generatorType;
            }
        } elseif ('auto' === $class->generatorType) {
            $class->generatorType = $this->autoGenerator;
        }

        if (null !== $parent) {
            if ($parent->isInheritanceTypeSingleTable()) {
                $class->table = ['name' => $parent->table['name']] + array_intersect_key($parent->table, array_flip(['indexes', 'uniqueConstraints', 'options'])) + $class->table;
            }

            if ($parent->isMappedSuperclass) {
                foreach (['uniqueConstraints', 'indexes'] as $type) {
                    foreach ($parent->table[$type] ?? [] as $name => $index) {
                        $class->table[$type][$name] ??= $index;
                    }
                }
            }
        }

        $class->parentClasses = $nonSuperclassParents;
        if ($nonSuperclassParents) {
            $class->rootEntityName = end($nonSuperclassParents);
        }

        // Doctrine's default map: the class and its subclasses, each under its short name.
        if ($class->isRootEntity() && !$class->isInheritanceTypeNone() && !$class->discriminatorMap && !$class->isMappedSuperclass) {
            $map = [strtolower(substr($class->name, (int) strrpos($class->name, '\\') + 1)) => $class->name];
            foreach ($this->classes as $candidate) {
                if (is_subclass_of($candidate, $class->name)) {
                    $map[strtolower(substr($candidate, (int) strrpos($candidate, '\\') + 1))] = $candidate;
                }
            }
            $class->setDiscriminatorMap($map);
        }

        foreach ($this->rules as $rule) {
            $rule->loaded($class, $declaration, $this);
        }
    }

    /** The declaration read as Doctrine's attribute driver reads the attributes (omnibridge/doctrine's DeclarationDriver). */
    private function declare(Draft $class, ClassDeclaration $declaration): void
    {
        match ($declaration->kind()) {
            'MappedSuperclass' => $class->isMappedSuperclass = true,
            'Embeddable' => $class->isEmbeddedClass = true,
            default => null,
        };

        if (null !== ($table = $declaration->attribute('Table'))) {
            if (null !== $table->get('name')) {
                $class->table['name'] = $table->get('name');
            }
            if ($table->get('options')) {
                $class->table['options'] = $table->get('options');
            }
        }

        foreach (['Index' => 'indexes', 'UniqueConstraint' => 'uniqueConstraints'] as $attributeName => $key) {
            $indexes = [];
            foreach ($declaration->attributes($attributeName) as $attribute) {
                $index = array_filter([
                    'columns' => $attribute->get('columns'),
                    'fields' => $attribute->get('fields'),
                    'flags' => 'Index' === $attributeName ? $attribute->get('flags') : null,
                    'options' => $attribute->get('options'),
                ]);
                if (!empty($attribute->get('name'))) {
                    $indexes[$attribute->get('name')] = $index;
                } else {
                    $indexes[] = $index;
                }
            }
            if ($indexes) {
                $class->table[$key] = $indexes;
            }
        }

        if (null !== ($inheritance = $declaration->attribute('InheritanceType'))) {
            $class->inheritanceType = strtolower((string) $inheritance->get('value'));
            if (!$class->isInheritanceTypeNone()) {
                $column = $declaration->attribute('DiscriminatorColumn');
                $class->discriminatorColumn = null !== $column
                    ? ['name' => $column->get('name'), 'type' => $column->get('type') ?? 'string', 'length' => $column->get('length') ?? 255]
                    : ['name' => 'dtype', 'type' => 'string', 'length' => 255];

                if (null !== ($map = $declaration->attribute('DiscriminatorMap'))) {
                    $class->setDiscriminatorMap($map->get('value'));
                }
            }
        }

        foreach ($declaration->properties as $property) {
            if (($class->fieldMappings[$property->name]['declared'] ?? null) === $property->class
                || ($class->associationMappings[$property->name]['declared'] ?? null) === $property->class) {
                continue;
            }

            $this->property($class, $property->name, $property->attributes);
        }
    }

    /** @param list<Attribute> $attributes */
    private function property(Draft $class, string $property, array $attributes): void
    {
        $find = static function (string $name) use ($attributes): ?Attribute {
            $found = null;
            foreach ($attributes as $attribute) {
                if ($attribute->name === $name) {
                    $found = $attribute;
                }
            }

            return $found;
        };
        $joinColumns = array_map(self::joinColumn(...), array_values(array_filter($attributes, static fn (Attribute $attribute) => 'JoinColumn' === $attribute->name)));
        $id = $find('Id');

        if (null !== ($column = $find('Column'))) {
            $mapping = ['fieldName' => $property, 'type' => $column->get('type'), 'length' => $column->get('length'), 'precision' => $column->get('precision'), 'scale' => $column->get('scale'), 'unique' => (bool) $column->get('unique'), 'nullable' => (bool) $column->get('nullable'), 'index' => (bool) $column->get('index'), 'options' => $column->get('options') ?? []];
            if (null !== $column->get('name')) {
                $mapping['columnName'] = $column->get('name');
            }
            if (null !== $column->get('enumType')) {
                $mapping['enumType'] = $column->get('enumType');
            }
            if (null !== $id) {
                $mapping['id'] = true;
                $mapping['idPosition'] = (int) $id->get('position');
            }
            if (null === $mapping['type']) {
                unset($mapping['type']);
            }
            if (null !== ($generated = $find('GeneratedValue'))) {
                $class->generatorType = strtolower((string) $generated->get('strategy'));
            }
            $class->mapField($mapping);

            return;
        }

        foreach (['OneToOne' => 'one_to_one', 'OneToMany' => 'one_to_many', 'ManyToOne' => 'many_to_one', 'ManyToMany' => 'many_to_many'] as $name => $type) {
            if (null === ($relation = $find($name))) {
                continue;
            }

            $mapping = ['fieldName' => $property, 'type' => $type, 'targetEntity' => $relation->get('targetEntity'), 'mappedBy' => $relation->has('mappedBy') || 'ManyToOne' !== $name ? $relation->get('mappedBy') : null, 'inversedBy' => 'OneToMany' !== $name ? $relation->get('inversedBy') : null, 'indexBy' => \in_array($name, ['OneToMany', 'ManyToMany'], true) ? $relation->get('indexBy') : null];
            if (null !== $id && 'OneToMany' !== $name && 'ManyToMany' !== $name) {
                $mapping['id'] = true;
                $mapping['idPosition'] = (int) $id->get('position');
            }
            if (null !== ($orderBy = $find('OrderBy'))) {
                $mapping['orderBy'] = array_map(static fn ($direction) => strtoupper($direction instanceof \UnitEnum ? $direction->name : (string) $direction), $orderBy->get('value'));
            }
            if ('ManyToMany' === $name) {
                $joinTable = $find('JoinTable');
                $mapping['joinTable'] = null === $joinTable ? [] : array_filter([
                    'name' => $joinTable->get('name'),
                    'joinColumns' => array_map(self::joinColumn(...), $joinTable->get('joinColumns') ?? []),
                    'inverseJoinColumns' => array_map(self::joinColumn(...), $joinTable->get('inverseJoinColumns') ?? []),
                ]);
                if ($joinColumns) {
                    $mapping['joinTable']['joinColumns'] = array_merge($mapping['joinTable']['joinColumns'] ?? [], $joinColumns);
                }
                $inverse = array_map(self::joinColumn(...), array_values(array_filter($attributes, static fn (Attribute $attribute) => 'InverseJoinColumn' === $attribute->name)));
                if ($inverse) {
                    $mapping['joinTable']['inverseJoinColumns'] = array_merge($mapping['joinTable']['inverseJoinColumns'] ?? [], $inverse);
                }
            } else {
                $mapping['joinColumns'] = $joinColumns;
            }

            $class->mapRelation($mapping);

            return;
        }
    }

    /** @return array{name: ?string, referencedColumnName: ?string, nullable: ?bool, unique: bool, onDelete: ?string} */
    private static function joinColumn(Attribute $attribute): array
    {
        return [
            'name' => $attribute->get('name'),
            'referencedColumnName' => $attribute->get('referencedColumnName'),
            'nullable' => $attribute->get('nullable'),
            'unique' => (bool) $attribute->get('unique'),
            'onDelete' => $attribute->get('onDelete'),
        ];
    }

    private function entity(Draft $class): Entity
    {
        $kind = $class->isMappedSuperclass ? Entity::MAPPED_SUPERCLASS : ($class->isEmbeddedClass ? Entity::EMBEDDABLE : Entity::ENTITY);
        $inheritance = null;
        if (!$class->isInheritanceTypeNone() && null !== $class->discriminatorColumn) {
            $column = $class->discriminatorColumn;
            $map = array_map('strval', $class->discriminatorMap);
            ksort($map);
            $inheritance = new Inheritance($class->inheritanceType, new Column($column['name'], $column['type'], false, $column['length']), $map, $class->discriminatorValue);
        }

        $generated = Draft::NONE === $class->generatorType ? null : $class->generatorType;
        $fields = [];
        foreach ($class->fieldMappings as $property => $mapping) {
            $fields[$property] = new Field(
                $property,
                $mapping['columnName'],
                $mapping['type'],
                (bool) $mapping['nullable'],
                $mapping['length'],
                $mapping['precision'],
                $mapping['scale'],
                (bool) $mapping['unique'],
                $mapping['options'],
                (bool) $mapping['id'],
                $mapping['id'] ? $generated : null,
                $mapping['enumType'],
                $mapping['inherited'],
                $mapping['declared'],
            );
        }

        $relations = [];
        foreach ($class->associationMappings as $property => $mapping) {
            $joinColumn = static fn (array $column) => new JoinColumn($column['name'], $column['referencedColumnName'], (bool) $column['nullable'], (bool) $column['unique'], $column['onDelete']);
            $relations[$property] = new Relation(
                $property,
                $mapping['type'],
                $mapping['targetEntity'],
                $mapping['isOwningSide'],
                $mapping['mappedBy'],
                $mapping['inversedBy'],
                array_map($joinColumn, $mapping['joinColumns']),
                null === $mapping['joinTable'] ? null : new JoinTable($mapping['joinTable']['name'], array_map($joinColumn, $mapping['joinTable']['joinColumns']), array_map($joinColumn, $mapping['joinTable']['inverseJoinColumns'])),
                $mapping['orderBy'],
                $mapping['indexBy'],
                (bool) $mapping['id'],
                $mapping['inherited'],
                $mapping['declared'],
            );
        }

        $subclasses = $class->subClasses;
        sort($subclasses);

        return new Entity(
            $class->name,
            Entity::ENTITY === $kind ? $class->table['name'] : null,
            $kind,
            $class->parentClasses[0] ?? null,
            Entity::ENTITY === $kind && !$class->isRootEntity() ? $class->rootEntityName : null,
            $inheritance,
            $class->identifier,
            $fields,
            $relations,
            $subclasses,
        );
    }
}
