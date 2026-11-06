<?php

namespace Omnibridge\Tests\Schema;

use Omnibridge\Schema\Column;
use Omnibridge\Schema\Database\Cli;
use Omnibridge\Schema\Database\ColumnTypes;
use Omnibridge\Schema\Database\SqliteSchema;
use Omnibridge\Schema\Description;
use Omnibridge\Schema\Table;
use PHPUnit\Framework\TestCase;

/** omnibridge:schema:create in plain PHP (bin/omnibridge-schema-create, Schema\Database\Cli): a schema file, a connection, the tables. */
final class CliTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir().'/omnibridge-cli-'.bin2hex(random_bytes(4));
        mkdir($this->folder);
        $description = new Description([], ['page' => new Table('page', [
            'id' => new Column('id', 'integer', autoincrement: true),
            'state' => new Column('state', 'page_state'),
        ], ['id'])]);
        $types = new ColumnTypes([], ['page_state' => ['draft', 'published']]);
        file_put_contents($this->folder.'/description.json', json_encode($description->toArray() + ['types' => $types->toArray()]));
        file_put_contents($this->folder.'/schema.php', '<?php return ["description" => Omnibridge\Schema\Description::fromJson(file_get_contents(__DIR__."/description.json")), "types" => '.var_export($types->toArray(), true).'];');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->folder.'/*') ?: []);
        rmdir($this->folder);
    }

    /** @return array{int, string, string} */
    private static function cli(string ...$arguments): array
    {
        $output = fopen('php://memory', 'w+');
        $errors = fopen('php://memory', 'w+');
        $status = Cli::run($arguments, $output, $errors);
        rewind($output);
        rewind($errors);

        return [$status, (string) stream_get_contents($output), (string) stream_get_contents($errors)];
    }

    public function testTheStatementsPrintedNothingRun(): void
    {
        [$status, $output] = self::cli('--schema='.$this->folder.'/schema.php', '--dsn=sqlite:'.$this->folder.'/app.db', '--dump-sql');

        self::assertSame(0, $status);
        self::assertSame("CREATE TABLE \"page\" (\"id\" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, \"state\" CLOB NOT NULL);\n", $output);
        self::assertSame([], SqliteSchema::read(new \PDO('sqlite:'.$this->folder.'/app.db'))->tables, 'nothing created');
    }

    public function testTheTablesCreatedFromAPhpOrAJsonSchemaThenRefusedOnceTheyExist(): void
    {
        foreach (['schema.php' => 'php.db', 'description.json' => 'json.db'] as $schema => $database) {
            [$status, $output, $errors] = self::cli('--schema='.$this->folder.'/'.$schema, '--url=sqlite:///'.ltrim($this->folder, '/').'/'.$database);
            self::assertSame([0, "1 tables created (1 statements).\n", ''], [$status, $output, $errors], $schema);
            self::assertSame(['page'], array_keys(SqliteSchema::read(new \PDO('sqlite:'.$this->folder.'/'.$database))->tables));
        }

        [$status, , $errors] = self::cli('--schema='.$this->folder.'/schema.php', '--dsn=sqlite:'.$this->folder.'/php.db');
        self::assertSame(1, $status);
        self::assertStringContainsString('already holds 1 table of the description (page)', $errors);
    }

    public function testAWrongCommandLine(): void
    {
        self::assertSame(2, self::cli('--dsn=sqlite::memory:')[0], '--schema is required');
        self::assertSame(2, self::cli('--schema=x', '--force')[0], 'an unknown argument');
        self::assertSame(1, self::cli('--schema='.$this->folder.'/missing.php', '--dsn=sqlite::memory:')[0]);
        [$status, $output] = self::cli('--help');
        self::assertSame(0, $status);
        self::assertStringStartsWith('Usage: omnibridge-schema-create --schema=', $output);
    }

    public function testADatabaseUrlReadAsPdosConnection(): void
    {
        self::assertSame(['mysql:host=db;port=3306;dbname=app;charset=utf8mb4', 'user', 'p@ss'], Cli::fromUrl('mysql://user:p%40ss@db:3306/app?serverVersion=8.4'));
        self::assertSame(['mysql:host=db;dbname=other;charset=latin1', 'user', null], Cli::fromUrl('mariadb://user@db/app?charset=latin1', 'other'), 'another database of the same server');
        self::assertSame(['sqlite:/srv/app/var/data.db', null, null], Cli::fromUrl('sqlite:////srv/app/var/data.db'));
        self::assertSame(['sqlite::memory:', null, null], Cli::fromUrl('sqlite:///:memory:'));
        $this->expectException(\DomainException::class);
        Cli::fromUrl('pgsql://user@db/app');
    }

    public function testTheScriptOnTheCommandLine(): void
    {
        $process = proc_open([\PHP_BINARY, __DIR__.'/../../bin/omnibridge-schema-create', '--schema='.$this->folder.'/description.json', '--dsn=sqlite:'.$this->folder.'/bin.db'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);

        self::assertSame(0, proc_close($process), $errors);
        self::assertSame("1 tables created (1 statements).\n", $output);
        self::assertSame([], SqliteSchema::read(new \PDO('sqlite:'.$this->folder.'/bin.db'), [], new ColumnTypes([], ['page_state' => ['draft', 'published']]))->differences(Description::fromJson((string) file_get_contents($this->folder.'/description.json'))));
    }
}
