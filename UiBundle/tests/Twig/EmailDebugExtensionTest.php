<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Service\EmailService;
use c975L\UiBundle\Twig\EmailDebugExtension;
use PHPUnit\Framework\TestCase;

// What the Email:DebugPreview component calls: the component is an anonymous one, so this is the only place the service is reachable from a template
class EmailDebugExtensionTest extends TestCase
{
    // A page following a send in debug mode: one entry per email held back, in the order they were stashed
    public function testThePreviewsOfTheServiceAreHandedOverInOrder(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('consumeDebugPreviews')->willReturn(['<html>to</html>', '<html>copy</html>']);

        $this->assertSame(['<html>to</html>', '<html>copy</html>'], new EmailDebugExtension($emailService)->previews());
    }

    // Every other page: nothing was held back, and the component draws nothing
    public function testAnEmptyStashIsHandedOverAsAnEmptyArray(): void
    {
        $emailService = $this->createStub(EmailService::class);
        $emailService->method('consumeDebugPreviews')->willReturn([]);

        $this->assertSame([], new EmailDebugExtension($emailService)->previews());
    }
}
