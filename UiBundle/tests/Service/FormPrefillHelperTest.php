<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\FormPrefillHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class FormPrefillHelperTest extends TestCase
{
    private function createRequest(): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        return $request;
    }

    public function testConsumeReturnsEmptyArrayWhenNothingPrefilled(): void
    {
        $helper = new FormPrefillHelper();

        $this->assertSame([], $helper->consume($this->createRequest(), 'contact'));
    }

    public function testConsumeReturnsPrefilledValues(): void
    {
        $helper = new FormPrefillHelper();
        $request = $this->createRequest();

        $helper->prefill($request, 'contact', ['subject' => 'About listing #42']);

        $this->assertSame(['subject' => 'About listing #42'], $helper->consume($request, 'contact'));
    }

    public function testConsumeDoesNotClearTheValues(): void
    {
        $helper = new FormPrefillHelper();
        $request = $this->createRequest();

        $helper->prefill($request, 'contact', ['subject' => 'About listing #42']);
        $helper->consume($request, 'contact');

        $this->assertSame(['subject' => 'About listing #42'], $helper->consume($request, 'contact'));
    }

    public function testClearRemovesTheValues(): void
    {
        $helper = new FormPrefillHelper();
        $request = $this->createRequest();

        $helper->prefill($request, 'contact', ['subject' => 'About listing #42']);
        $helper->clear($request, 'contact');

        $this->assertSame([], $helper->consume($request, 'contact'));
    }

    public function testPrefillIsIsolatedPerFormName(): void
    {
        $helper = new FormPrefillHelper();
        $request = $this->createRequest();

        $helper->prefill($request, 'contact', ['subject' => 'About listing #42']);

        $this->assertSame([], $helper->consume($request, 'newsletter'));
    }
}
