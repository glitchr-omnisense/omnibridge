<?php

namespace Omnibridge\Schema\Declaration;

/**
 * The mapping attributes the entities are written with - those of the
 * Doctrine\ORM\Mapping namespace, the vocabulary omnibase's entities and their
 * applications' carry - as plain data: each attribute's parameters in the
 * order of its constructor, with their defaults, and whether it may be
 * written more than once. With it a declaration is read without the
 * attributes' classes (a positional argument gets its parameter's name, a
 * left-out one its default), so without the ORM installed.
 *
 * Kept equal to doctrine/orm 3's constructors: omnibridge/doctrine's
 * VocabularyTest compares the two, parameter by parameter.
 */
final class Vocabulary
{
    /** The namespace whose attributes are read. */
    public const NAMESPACE = 'Doctrine\\ORM\\Mapping\\';

    /** @var array<string, array<string, mixed>> attribute => parameter => default (REQUIRED for a parameter without one) */
    public const PARAMETERS = [
        'AssociationOverride' => ['name' => self::REQUIRED, 'joinColumns' => null, 'inverseJoinColumns' => null, 'joinTable' => null, 'inversedBy' => null, 'fetch' => null],
        'AssociationOverrides' => ['overrides' => self::REQUIRED],
        'AttributeOverride' => ['name' => self::REQUIRED, 'column' => self::REQUIRED],
        'AttributeOverrides' => ['overrides' => self::REQUIRED],
        'Cache' => ['usage' => 'READ_ONLY', 'region' => null],
        'ChangeTrackingPolicy' => ['value' => self::REQUIRED],
        'Column' => ['name' => null, 'type' => null, 'length' => null, 'precision' => null, 'scale' => null, 'unique' => false, 'nullable' => false, 'insertable' => true, 'updatable' => true, 'enumType' => null, 'options' => [], 'columnDefinition' => null, 'generated' => null, 'index' => false],
        'CustomIdGenerator' => ['class' => null],
        'DiscriminatorColumn' => ['name' => null, 'type' => null, 'length' => null, 'columnDefinition' => null, 'enumType' => null, 'options' => []],
        'DiscriminatorMap' => ['value' => self::REQUIRED],
        'Embeddable' => [],
        'Embedded' => ['class' => null, 'columnPrefix' => null],
        'Entity' => ['repositoryClass' => null, 'readOnly' => false],
        'EntityListeners' => ['value' => []],
        'GeneratedValue' => ['strategy' => 'AUTO'],
        'HasLifecycleCallbacks' => [],
        'Id' => ['position' => 0],
        'Index' => ['name' => null, 'columns' => null, 'fields' => null, 'flags' => null, 'options' => null],
        'InheritanceType' => ['value' => self::REQUIRED],
        'InverseJoinColumn' => ['name' => null, 'referencedColumnName' => null, 'deferrable' => false, 'unique' => false, 'nullable' => null, 'onDelete' => null, 'columnDefinition' => null, 'fieldName' => null, 'foreignKeyName' => null, 'options' => []],
        'JoinColumn' => ['name' => null, 'referencedColumnName' => null, 'deferrable' => false, 'unique' => false, 'nullable' => null, 'onDelete' => null, 'columnDefinition' => null, 'fieldName' => null, 'foreignKeyName' => null, 'options' => []],
        'JoinColumns' => ['value' => self::REQUIRED],
        'JoinTable' => ['name' => null, 'schema' => null, 'joinColumns' => [], 'inverseJoinColumns' => [], 'foreignKeyName' => null, 'inverseForeignKeyName' => null, 'options' => []],
        'ManyToMany' => ['targetEntity' => self::REQUIRED, 'mappedBy' => null, 'inversedBy' => null, 'cascade' => null, 'fetch' => 'LAZY', 'orphanRemoval' => false, 'indexBy' => null],
        'ManyToOne' => ['targetEntity' => null, 'cascade' => null, 'fetch' => 'LAZY', 'inversedBy' => null],
        'MappedSuperclass' => ['repositoryClass' => null],
        'OneToMany' => ['targetEntity' => null, 'mappedBy' => null, 'cascade' => null, 'fetch' => 'LAZY', 'orphanRemoval' => false, 'indexBy' => null],
        'OneToOne' => ['targetEntity' => null, 'mappedBy' => null, 'inversedBy' => null, 'cascade' => null, 'fetch' => 'LAZY', 'orphanRemoval' => false],
        'OrderBy' => ['value' => self::REQUIRED],
        'PostLoad' => [],
        'PostPersist' => [],
        'PostRemove' => [],
        'PostUpdate' => [],
        'PreFlush' => [],
        'PrePersist' => [],
        'PreRemove' => [],
        'PreUpdate' => [],
        'SequenceGenerator' => ['sequenceName' => null, 'allocationSize' => 1, 'initialValue' => 1],
        'Table' => ['name' => null, 'schema' => null, 'indexes' => null, 'uniqueConstraints' => null, 'options' => []],
        'UniqueConstraint' => ['name' => null, 'columns' => null, 'fields' => null, 'options' => null],
        'Version' => [],
    ];

    /** The attributes that may be written more than once on the same class or property. */
    public const REPEATABLE = ['Index', 'InverseJoinColumn', 'JoinColumn', 'UniqueConstraint'];

    /** The lifecycle attributes of a method, by the event they name. */
    public const CALLBACKS = [
        'PrePersist' => 'prePersist',
        'PostPersist' => 'postPersist',
        'PreUpdate' => 'preUpdate',
        'PostUpdate' => 'postUpdate',
        'PreRemove' => 'preRemove',
        'PostRemove' => 'postRemove',
        'PostLoad' => 'postLoad',
        'PreFlush' => 'preFlush',
    ];

    /** The default of a required parameter: there is none. */
    public const REQUIRED = "\0required";

    /** The attribute's name in the vocabulary of a class name (Doctrine\ORM\Mapping\Column: Column), null outside it. */
    public static function nameOf(string $className): ?string
    {
        if (!str_starts_with($className, self::NAMESPACE)) {
            return null;
        }

        $name = substr($className, \strlen(self::NAMESPACE));

        return isset(self::PARAMETERS[$name]) ? $name : null;
    }

    /** @return list<string> the attribute's parameters, in its constructor's order */
    public static function parameters(string $name): array
    {
        return array_keys(self::PARAMETERS[$name] ?? []);
    }

    /** @return array<string, mixed> the attribute's optional parameters and their defaults */
    public static function defaults(string $name): array
    {
        return array_filter(self::PARAMETERS[$name] ?? [], static fn (mixed $default) => self::REQUIRED !== $default);
    }

    public static function defaultOf(string $name, string $parameter): mixed
    {
        $default = self::PARAMETERS[$name][$parameter] ?? null;

        return self::REQUIRED === $default ? null : $default;
    }

    public static function isRepeatable(string $name): bool
    {
        return \in_array($name, self::REPEATABLE, true);
    }
}
