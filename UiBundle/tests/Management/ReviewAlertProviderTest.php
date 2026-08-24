<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Management\ReviewAlertProvider;
use c975L\UiBundle\Repository\ReviewRepository;
use c975L\UiBundle\Service\ReviewService;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// What the dashboard says about reviews waiting for a decision - nothing of them shows on the site until someone reads them
class ReviewAlertProviderTest extends TestCase
{
    public function testTheAlertCountsWhatIsWaitingAndLeadsToTheScreenItWaitsIn(): void
    {
        $alerts = $this->provider(pending: 3)->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.reviews_pending_alert', $alerts[0]['label']);
        $this->assertSame(Config::SEVERITY_WARNING, $alerts[0]['severity']);
        $this->assertSame('/management/review', $alerts[0]['url']);
        $this->assertSame('ROLE_EDITOR', $alerts[0]['role']);
    }

    // An empty queue is not news: the dashboard says what is left to do, not what is done
    public function testNothingIsSaidWhileNothingIsWaiting(): void
    {
        $this->assertSame([], $this->provider(pending: 0)->getAlerts());
    }

    // Same switch as the screen it links to: a site collecting no reviews has none waiting, and the repository is not even asked
    public function testNothingIsSaidNorCountedWhileTheFeatureIsOff(): void
    {
        $repository = $this->createMock(ReviewRepository::class);
        $repository->expects($this->never())->method('countPending');

        $this->assertSame([], $this->provider(pending: 0, enabled: false, repository: $repository)->getAlerts());
    }

    private function provider(int $pending, bool $enabled = true, ?ReviewRepository $repository = null): ReviewAlertProvider
    {
        // A caller passing its own repository is checking what is asked of it, and states that itself
        if (null === $repository) {
            $repository = $this->createStub(ReviewRepository::class);
            $repository->method('countPending')->willReturn($pending);
        }

        $reviewService = $this->createStub(ReviewService::class);
        $reviewService->method('isEnabled')->willReturn($enabled);

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/review');

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new ReviewAlertProvider($repository, $reviewService, $adminUrlGenerator, $configService, $translator);
    }
}
