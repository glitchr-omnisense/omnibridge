<?php

namespace Omnibridge\Schema;

use Omnibridge\Schema\Declaration\Vocabulary;

/**
 * The omni family's names: App\Entity\Thread\Tag is the table threadTag,
 * App\Entity\User\Profile userProfile - the App\ or Base\ prefix and the
 * first Entity\ off, the rest in camel case -, a property its own column
 * (lcfirst), Thread::$tags the join table thread_tags (thread_id,
 * threadTag_id), a relation's join column <property>_id. A class that names
 * its table (#[Table(name: ...)]) keeps that name. An index or a foreign key
 * nobody named is named as DBAL names it - its prefix and the CRC32 of its
 * table and columns, upper case, at most 64 characters (MySQL's limit) - so
 * that the description finds the names of the existing tables.
 *
 * omnibridge/doctrine's NamingStrategy (Doctrine's) and omniscient/omnibase's
 * answer with this class: one place says how a class becomes a table.
 */
class CamelCaseNaming implements Naming
{
    public const TABLE_NAME_SIZE = 64;
    public const TABLE_I18N_SUFFIX = 'Intl';
    public const IDENTIFIER_SIZE = 64;

    /** @param list<string> $prefixes the namespaces a class's table name leaves out */
    public function __construct(private readonly array $prefixes = ['App\\', 'Base\\'])
    {
    }

    public function classToTableName(string $className): string
    {
        if (!$className) {
            return '';
        }

        $tableName = null;
        $className = class_exists($className) ? (new \ReflectionClass($className))->getName() : $className;

        // A #[Table(name: ...)] says it: the last one written that names one.
        if (class_exists($className)) {
            foreach (array_reverse((new \ReflectionClass($className))->getAttributes(Vocabulary::NAMESPACE.'Table')) as $attribute) {
                $arguments = $attribute->getArguments();
                $name = $arguments['name'] ?? $arguments[0] ?? null;
                if (!empty($name)) {
                    $tableName = $name;
                    break;
                }
            }
        }

        if (!$tableName) {
            // The prefixes off, the first Entity\ off, the rest in camel case.
            $tableName = self::lstrip($className, $this->prefixes);
            $pos = strpos($tableName, 'Entity\\');
            if (false !== $pos) {
                $tableName = substr_replace($tableName, '', $pos, \strlen('Entity\\'));
            }
            if ('' === $tableName) {
                $tableName = false !== strrpos($className, '\\') ? lcfirst(substr($className, strrpos($className, '\\') + 1)) : $className;
            }

            $tableName = str_replace('\\', '', $tableName);
            $parts = array_unique(explode('_', self::camel2snake($tableName)));
            $tableName = lcfirst(self::snake2camel(implode('_', $parts)));
            $tableName = preg_replace('/'.self::TABLE_I18N_SUFFIX.'$/', self::TABLE_I18N_SUFFIX, $tableName);
        }

        if (\strlen($tableName) > self::TABLE_NAME_SIZE) {
            throw new \LengthException(\sprintf('Table name will be truncated for "%s"', $className));
        }

        return $tableName;
    }

    public function propertyToColumnName(string $propertyName, string $className): string
    {
        return lcfirst($propertyName);
    }

    public function embeddedFieldToColumnName(string $propertyName, string $embeddedColumnName, string $className, string $embeddedClassName): string
    {
        return lcfirst($propertyName).'_'.lcfirst($embeddedColumnName);
    }

    public function referenceColumnName(): string
    {
        return 'id';
    }

    public function joinColumnName(string $propertyName, string $className): string
    {
        return lcfirst($propertyName).'_'.lcfirst($this->referenceColumnName());
    }

    public function joinTableName(string $sourceEntity, string $targetEntity, string $propertyName): string
    {
        return lcfirst($this->classToTableName($sourceEntity)).'_'.lcfirst($this->classToTableName($propertyName ?: $targetEntity));
    }

    public function joinKeyColumnName(string $entityName, ?string $referencedColumnName): string
    {
        return lcfirst($this->classToTableName($entityName)).'_'.lcfirst($referencedColumnName ?: $this->referenceColumnName());
    }

    public function generatedName(string $table, array $columns, string $prefix): string
    {
        $hash = implode('', array_map(static fn (string $name): string => dechex(crc32($name)), [$table, ...$columns]));

        return strtoupper(substr($prefix.'_'.$hash, 0, self::IDENTIFIER_SIZE));
    }

    /** Every prefix off, again and again. @param list<string> $prefixes */
    private static function lstrip(string $haystack, array $prefixes): string
    {
        do {
            $last = $haystack;
            foreach ($prefixes as $prefix) {
                while ('' !== $prefix && str_starts_with($haystack, $prefix)) {
                    $haystack = substr($haystack, \strlen($prefix));
                }
            }
        } while ($haystack !== $last);

        return $haystack;
    }

    private static function camel2snake(string $input, string $separator = '_'): string
    {
        return mb_strtolower(str_replace($separator.$separator, $separator, str_replace('.'.$separator, '.', preg_replace('/(?<!^)[A-Z]/', $separator.'$0', str_replace(' ', $separator, $input)))));
    }

    private static function snake2camel(string $input, string $separator = '_'): string
    {
        $words = array_map(static fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)).mb_substr($word, 1), explode(' ', str_replace($separator, ' ', $input)));

        return lcfirst(implode('', $words));
    }
}
