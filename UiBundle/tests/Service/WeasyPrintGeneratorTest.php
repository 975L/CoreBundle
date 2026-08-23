<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\WeasyPrintGenerator;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

// The engine a server carries rather than one Composer installs: a binary shelled out to, the markup going in on its standard input and the file coming back on its standard output
class WeasyPrintGeneratorTest extends TestCase
{
    private string $binary;

    // A stand-in for the real command: it echoes back whatever it is given, which is what makes the document handed to the binary readable in a test
    protected function setUp(): void
    {
        $this->binary = sys_get_temp_dir() . '/weasyprint-stub-' . getmypid();
        file_put_contents($this->binary, "#!/bin/sh\ncat\n");
        chmod($this->binary, 0o755);
    }

    protected function tearDown(): void
    {
        if (is_file($this->binary)) {
            unlink($this->binary);
        }
    }

    public function testTheMarkupTravelsThroughTheBinary(): void
    {
        $this->assertStringContainsString('<p>Hello</p>', $this->generator()->renderHtml('<html><body><p>Hello</p></body></html>'));
    }

    // The page size is CSS for this engine, where the other takes it as an argument
    public function testThePaperAskedForIsWrittenIntoTheDocument(): void
    {
        $html = $this->generator()->renderHtml('<html><head></head><body></body></html>', ['paper' => 'a4']);

        $this->assertStringContainsString('@page { size: a4; }', $html);
    }

    public function testALandscapeOrientationIsWrittenBesideTheSize(): void
    {
        $html = $this->generator()->renderHtml('<html><head></head><body></body></html>', ['paper' => 'a4', 'orientation' => 'landscape']);

        $this->assertStringContainsString('@page { size: a4 landscape; }', $html);
    }

    // A document that is an object rather than a page states its size in millimetres
    public function testASizeStatedInMillimetresIsWrittenAsSuch(): void
    {
        $html = $this->generator()->renderHtml('<html><head></head><body></body></html>', ['paper' => [85, 55]]);

        $this->assertStringContainsString('@page { size: 85mm 55mm; }', $html);
    }

    // Appended to the head rather than replacing it: a print template states its own margins, and only the size is being settled
    public function testTheRuleIsAppendedToTheHeadTheTemplateWrote(): void
    {
        $html = $this->generator()->renderHtml('<html><head><style>@page { margin: 20mm; }</style></head><body></body></html>', ['paper' => 'a4']);

        $this->assertStringContainsString('margin: 20mm', $html);
        $this->assertStringContainsString('@page { size: a4; }', $html);
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, '@page { size: a4; }'));
    }

    // A fragment with no head at all still carries the size, the rule going in front of it
    public function testAMarkupWithNoHeadStillCarriesTheRule(): void
    {
        $html = $this->generator()->renderHtml('<p>x</p>', ['paper' => 'a4']);

        $this->assertStringStartsWith('<style>@page { size: a4; }</style>', $html);
    }

    public function testADocumentAskingForNoPaperIsHandedOverUntouched(): void
    {
        $this->assertStringNotContainsString('@page', $this->generator()->renderHtml('<p>x</p>'));
    }

    // Whether the binary answers at all is what decides between the two engines
    public function testAvailabilityFollowsWhetherTheBinaryAnswers(): void
    {
        $this->assertTrue($this->generator()->isAvailable());
        $this->assertFalse($this->generator('/does/not/exist/weasyprint')->isAvailable());
    }

    // A setting and not a container parameter: on a managed host the command commonly sits in a virtual environment of its own, and an admin typing its path is entitled to a stray space around it
    public function testThePathReadFromTheSettingIsTrimmed(): void
    {
        $this->assertTrue($this->generator('  ' . $this->binary . '  ')->isAvailable());
    }

    private function generator(?string $binary = null): WeasyPrintGenerator
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($binary ?? $this->binary);

        return new WeasyPrintGenerator($this->createStub(Environment::class), $configService, sys_get_temp_dir());
    }
}
