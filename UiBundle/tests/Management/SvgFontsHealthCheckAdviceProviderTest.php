<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckAdviceBuilder;
use c975L\UiBundle\Management\SvgFontsHealthCheckAdviceProvider;
use c975L\UiBundle\Management\SvgFontsHealthCheckProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SvgFontsHealthCheckAdviceProviderTest extends TestCase
{
    // The two lines say what the row cannot: the menu entry that vectorizes, and why a font this site loads changes nothing
    public function testAFileStillDrawingItsTextWithAFontGetsTheWayOut(): void
    {
        $result = $this->createResult(HealthCheckResult::STATUS_WARNING);

        $advice = $this->createProvider()->buildAdvice([$result]);

        $this->assertSame([HealthCheckAdviceBuilder::key($result)], array_keys($advice));
        $this->assertSame([
            'label.health_check_advice_svg_fonts_vectorize',
            'label.health_check_advice_svg_fonts_served_as_image',
        ], array_column($advice[HealthCheckAdviceBuilder::key($result)], 'text'));
    }

    // The OK row exists to let a corrected file go back to green, and a vectorized file has nothing left to do
    public function testAVectorizedFileIsAdvisedNothing(): void
    {
        $this->assertSame([], $this->createProvider()->buildAdvice([$this->createResult(HealthCheckResult::STATUS_OK)]));
    }

    // Every provider sees every row of the run, so picking its own kind out is the first thing it does
    public function testARowOfAnotherKindIsLeftAlone(): void
    {
        $this->assertSame([], $this->createProvider()->buildAdvice([$this->createResult(HealthCheckResult::STATUS_WARNING, 'database-load')]));
    }

    private function createResult(string $status, string $kind = SvgFontsHealthCheckProvider::KIND): HealthCheckResult
    {
        return new HealthCheckResult()
            ->setKind($kind)
            ->setUrl('https://example.com/media/logo.svg')
            ->setStatus($status)
            ->setSummary('summary')
            ->setCheckedAt(new \DateTime());
    }

    private function createProvider(): SvgFontsHealthCheckAdviceProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return new SvgFontsHealthCheckAdviceProvider($translator);
    }
}
