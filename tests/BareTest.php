<?php

namespace Omnibridge\Tests;

use Omnibridge\Runtime;
use PHPUnit\Framework\TestCase;

/**
 * omniscient/omnibridge loads without any framework or ORM: a PHP process of
 * its own (this one has loaded PHPUnit) loads every class of src/ with
 * Composer's autoloader and reports every class and file loaded on the way.
 * Nothing may come from outside src/ but the PSR interfaces it declares.
 */
final class BareTest extends TestCase
{
    public function testEveryClassLoadsWithThePsrInterfacesAlone(): void
    {
        $report = self::bare();

        self::assertContains(Runtime::class, $report['loaded']);
        self::assertCount(\count(self::sources()), $report['loaded'], 'every file of src/ declares its class');

        $src = realpath(__DIR__.'/../src').'/';
        $outside = array_values(array_filter($report['files'], fn (string $file) => !str_starts_with($file, $src)));
        self::assertSame([], array_values(preg_grep('~/vendor/(?!psr/(?:clock|container|event-dispatcher)/)~', $outside)), 'no file of another package than the PSR interfaces');
        self::assertSame([], array_values(preg_grep('~^(?:Symfony|Illuminate|Laravel|Doctrine|Twig|Base|App)\\\\~', $report['symbols'])), 'no class of a framework, an ORM, Twig or an application');
    }

    /** The check is not blind: what the process loaded is seen, the PSR interfaces among them. */
    public function testTheReportSeesWhatIsLoaded(): void
    {
        $report = self::bare();

        self::assertContains('Psr\Clock\ClockInterface', $report['symbols'], 'SystemClock\'s interface');
        self::assertNotEmpty(preg_grep('~/vendor/psr/clock/~', $report['files']));
    }

    /** @return list<string> */
    private static function sources(): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../src', \FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }

    /** @return array{loaded: list<string>, symbols: list<string>, files: list<string>} */
    private static function bare(): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, __DIR__.'/Fixtures/bare.php', $autoload], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertSame(0, $status, $err.$out);
        self::assertIsArray($report, $err.$out);

        return $report;
    }
}
