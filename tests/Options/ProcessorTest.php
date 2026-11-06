<?php

namespace Omnibridge\Tests\Options;

use Omnibridge\Options\InvalidOption;
use Omnibridge\Options\Placeholders;
use Omnibridge\Options\Processor;
use PHPUnit\Framework\TestCase;

/**
 * Options\Processor: a configuration checked against a schema as data, as Symfony's Config component checks it
 * (omnibridge/symfony compiles the same schema into its tree: its SchemaTreeTest compares both), and the defaults'
 * placeholders resolved by a host (Options\Placeholders).
 */
final class ProcessorTest extends TestCase
{
    private const SCHEMA = [
        'enabled' => ['type' => 'bool', 'default' => true, 'info' => 'On or off'],
        'mode' => ['type' => 'bool', 'default' => null],
        'size' => ['type' => 'int', 'default' => 3, 'min' => 0],
        'level' => ['type' => 'enum', 'values' => ['low', 'high'], 'default' => 'low'],
        'old' => ['type' => 'scalar', 'deprecated' => ['package' => 'acme/options', 'since' => '1.0', 'message' => 'The "%node%" option at "%path%" is gone.']],
        'section' => ['type' => 'array', 'defaults' => true, 'children' => [
            'path' => ['type' => 'scalar', 'default' => '%kernel.project_dir%/var'],
            'roles' => ['type' => 'array', 'prototype' => ['type' => 'scalar'], 'default' => [], 'deep_merging' => false],
            'tags' => ['type' => 'array', 'prototype' => ['type' => 'scalar'], 'default' => []],
        ]],
        'named' => ['type' => 'array', 'key' => 'name', 'prototype' => ['type' => 'array', 'children' => ['icon' => ['type' => 'scalar', 'default' => null]]]],
        'firewalls' => ['type' => 'array', 'default_children' => true, 'prototype' => ['type' => 'scalar', 'default' => 'main']],
    ];

    public function testTheDefaults(): void
    {
        self::assertSame([
            'enabled' => true, 'mode' => null, 'size' => 3, 'level' => 'low',
            'section' => ['path' => '%kernel.project_dir%/var', 'roles' => [], 'tags' => []],
            'named' => [], 'firewalls' => ['main'],
        ], Processor::defaults(self::SCHEMA, 'acme'));
    }

    public function testNormalisedAndMerged(): void
    {
        $config = Processor::process(self::SCHEMA, 'acme', [
            ['enabled' => null, 'section' => ['roles' => ['A'], 'tags' => ['a']], 'named' => [['name' => 'x', 'icon' => 'i']]],
            ['section' => ['roles' => ['B'], 'tags' => ['b']], 'named' => ['y' => []]],
        ]);

        self::assertTrue($config['enabled'], 'null is true for a boolean with a default');
        self::assertSame(['B'], $config['section']['roles'], 'replaced: no deep merging');
        self::assertSame(['a', 'b'], $config['section']['tags'], 'a list is appended');
        self::assertSame(['x' => ['icon' => 'i'], 'y' => ['icon' => null]], $config['named']);
    }

    public function testRefusedAsSymfonyWordsIt(): void
    {
        foreach ([
            [['enabld' => 1], 'Unrecognized option "enabld" under "acme". Did you mean "enabled"?'],
            [['enabled' => 'yes'], "Invalid type for path \"acme.enabled\". Expected \"bool\", but got \"string\".\nHint: On or off"],
            [['mode' => 1], 'Invalid type for path "acme.mode". Expected "bool" or "null", but got "int".'],
            [['size' => -1], 'The value -1 is too small for path "acme.size". Should be greater than or equal to 0'],
            [['level' => 'mid'], 'The value "mid" is not allowed for path "acme.level". Permissible values: "low", "high".'],
            [['section' => 'x'], 'Invalid type for path "acme.section". Expected "array", but got "string"'],
            [['named' => [['icon' => 'i']]], 'The attribute "name" must be set for path "acme.named".'],
        ] as [$config, $message]) {
            try {
                Processor::process(self::SCHEMA, 'acme', [$config]);
                self::fail('Refused: '.$message);
            } catch (InvalidOption $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
    }

    public function testADeprecatedOptionSetIsSaid(): void
    {
        $said = [];
        set_error_handler(static function (int $type, string $message) use (&$said): bool {
            $said[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            Processor::process(self::SCHEMA, 'acme', [['old' => 1]]);
            Processor::process(self::SCHEMA, 'acme', [[]]);
        } finally {
            restore_error_handler();
        }

        self::assertSame(['Since acme/options 1.0: The "old" option at "acme" is gone.'], $said);
    }

    public function testThePlaceholdersAreTheHosts(): void
    {
        $parameters = ['kernel.project_dir' => '/app', 'list' => [1], 'env(DEBUG)' => true];
        $resolve = static fn (string $name): mixed => $parameters[$name];

        self::assertSame(['/app/var', [1], true, '100%'], Placeholders::resolve(['%kernel.project_dir%/var', '%list%', '%env(DEBUG)%', '100%%'], $resolve));
    }
}
