<?php

namespace Omnibridge\Tests\Form;

use Omnibridge\Form\Coverage;
use Omnibridge\Form\Endpoint;
use Omnibridge\Form\FieldAssets;
use Omnibridge\Form\UnsupportedKind;
use PHPUnit\Framework\TestCase;

final class CoverageTest extends TestCase
{
    public function testAWholeDeclarationHasNoFault(): void
    {
        $declared = ['text' => Coverage::IMPLEMENTED, 'cropper' => Coverage::unsupported('no cropping script on this host')];

        self::assertSame([], Coverage::verify(['text', 'cropper'], $declared));
        self::assertSame(['text'], Coverage::implemented($declared));
        self::assertSame('no cropping script on this host', Coverage::reason($declared['cropper']));
        self::assertTrue(Coverage::isUnsupported($declared['cropper']));
        self::assertNull(Coverage::reason(Coverage::IMPLEMENTED));
    }

    public function testAnUndeclaredKindAStrayOneAndAVagueOneAreFaults(): void
    {
        $faults = Coverage::verify(['text', 'slug', 'icon'], ['text' => Coverage::IMPLEMENTED, 'icon' => 'maybe', 'gone' => Coverage::IMPLEMENTED]);

        self::assertCount(3, $faults);
        self::assertStringContainsString('"slug" is not declared', $faults[0]);
        self::assertStringContainsString('"icon" is declared "maybe"', $faults[1]);
        self::assertStringContainsString('"gone" is declared but does not exist', $faults[2]);
    }

    public function testAnUnsupportedKindSaysWhy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Coverage::unsupported(' ');
    }

    public function testTheValuesArePlain(): void
    {
        $endpoint = new Endpoint('ux_autocomplete', ['data' => 'x'], 'POST');
        self::assertSame(['ux_autocomplete', ['data' => 'x'], 'POST'], [$endpoint->route, $endpoint->parameters, $endpoint->method]);
        self::assertNull(FieldAssets::none()->entry);
        self::assertSame('form.datetime', (new FieldAssets('form.datetime', 'datetimepicker'))->entry);
        self::assertStringContainsString('"slug": none here', UnsupportedKind::of('slug', 'Laravel', 'none here')->getMessage());
        self::assertStringContainsString('does not declare the field kind "slug"', UnsupportedKind::of('slug', 'Laravel')->getMessage());
    }
}
