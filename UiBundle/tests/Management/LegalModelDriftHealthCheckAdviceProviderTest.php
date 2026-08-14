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
use c975L\UiBundle\Management\LegalModelDriftHealthCheckAdviceProvider;
use c975L\UiBundle\Management\LegalModelDriftHealthCheckProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class LegalModelDriftHealthCheckAdviceProviderTest extends TestCase
{
    // Every row of this kind is a drifted document, reported STATUS_OK because drift is news and not a fault - the advice is what says a decision is waiting, and that nobody takes it in the reader's place
    public function testADriftedDocumentIsToldThereIsADecisionToTake(): void
    {
        $result = $this->createResult();

        $advice = $this->createProvider()->buildAdvice([$result]);

        $this->assertSame([HealthCheckAdviceBuilder::key($result)], array_keys($advice));
        $this->assertSame(['label.health_check_advice_legal_model_drift'], array_column($advice[HealthCheckAdviceBuilder::key($result)], 'text'));
    }

    // The row's own edit link already opens the customization screen, so the line carries no url of its own
    public function testTheLineCarriesNoUrlOfItsOwn(): void
    {
        $advice = $this->createProvider()->buildAdvice([$result = $this->createResult()]);

        $this->assertNull($advice[HealthCheckAdviceBuilder::key($result)][0]['url']);
    }

    // Every provider sees every row of the run, so picking its own kind out is the first thing it does
    public function testARowOfAnotherKindIsLeftAlone(): void
    {
        $this->assertSame([], $this->createProvider()->buildAdvice([$this->createResult('svg-fonts')]));
    }

    private function createResult(string $kind = LegalModelDriftHealthCheckProvider::KIND): HealthCheckResult
    {
        return new HealthCheckResult()
            ->setKind($kind)
            ->setUrl('https://example.com/legal-notice')
            ->setStatus(HealthCheckResult::STATUS_OK)
            ->setSummary('summary')
            ->setCheckedAt(new \DateTime());
    }

    private function createProvider(): LegalModelDriftHealthCheckAdviceProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => $id);

        return new LegalModelDriftHealthCheckAdviceProvider($translator);
    }
}
