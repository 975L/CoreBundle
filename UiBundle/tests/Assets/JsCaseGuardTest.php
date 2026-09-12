<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\JsCase;
use HeadlessChromium\Browser;
use HeadlessChromium\Page;
use PHPUnit\Framework\Attributes\Group;

// What an attempt that fails halfway leaves behind: the docroot and the page are each shared for the whole run, so one published before it works condemns every scenario that comes after it rather than the one that met the failure
// The two failures are provoked through the seams JsCase opens for exactly that - serve() and openPage() are protected - and the run's own state is put back before a single assertion is made, so a failing expectation never takes the suite with it
#[Group('browser')]
class JsCaseGuardTest extends JsCase
{
    // The statics the whole run shares, named here rather than reached one by one
    private const array SHARED = ['browser', 'page', 'server', 'docroot', 'port', 'published', 'copies'];

    private bool $refuseToServe = false;

    private bool $refuseToOpen = false;

    // A server that never answers, standing in for the twenty ports serve() tries in vain
    protected function serve(string $docroot): int
    {
        if ($this->refuseToServe) {
            throw new \RuntimeException('No port could be found.');
        }

        return parent::serve($docroot);
    }

    // A page that never finishes being mounted, standing in for a navigate() or an evaluate() that throws
    protected function openPage(): Page
    {
        if ($this->refuseToOpen) {
            throw new \RuntimeException('The page never came up.');
        }

        return parent::openPage();
    }

    // The run starts over from nothing, so the attempt below is the very first one of a suite and the failure is the only thing that could have published anything
    public function testADocrootIsNotPublishedWhenItsServerNeverAnswered(): void
    {
        $this->tab();
        $shared = $this->sharedState();

        // Everything the run holds is moved aside, so the attempt below starts exactly where the very first test of a suite starts
        $this->putBack(['browser' => null, 'page' => null, 'server' => null, 'docroot' => null, 'port' => null, 'published' => [], 'copies' => 0]);
        $this->refuseToServe = true;
        $failure = $this->provoke();
        $docroot = $this->readShared('docroot');
        $this->refuseToServe = false;
        $this->putBack($shared);

        $this->assertNotNull($failure, 'A serve() that finds no port is swallowed, so the failure below says nothing about the port.');
        $this->assertNull($docroot, 'A docroot whose server never answered is published all the same, so the guard closes and every later test navigates to port 0.');
    }

    // Only the page is moved aside, the docroot and its server being what a run keeps across a page made again
    public function testAPageIsNotSharedWhenItNeverFinishedBeingMounted(): void
    {
        $this->tab();
        $shared = $this->sharedState();

        // The docroot and its server are left standing: what is being described here is the page alone, made again on a run that already has everything else
        $this->putBack(['page' => null]);
        $this->refuseToOpen = true;
        $failure = $this->provoke();
        $page = $this->readShared('page');
        $this->refuseToOpen = false;
        $this->closeLaunched($shared['browser']);
        $this->putBack($shared);

        $this->assertNotNull($failure, 'An openPage() that throws is swallowed, so the failure below says nothing about the page.');
        $this->assertNull($page, 'A page that never finished being mounted is shared all the same, so every scenario left in the run is handed that half-built one.');
    }

    // The attempt itself, its failure answered rather than thrown: the run's state has to be put back before anything is asserted
    private function provoke(): ?\Throwable
    {
        try {
            $this->tab();
        } catch (\Throwable $failure) {
            return $failure;
        }

        return null;
    }

    // Everything the run shares, taken together so it goes back exactly as it was
    /** @return array<string, mixed> */
    private function sharedState(): array
    {
        $state = [];

        foreach (self::SHARED as $name) {
            $state[$name] = $this->readShared($name);
        }

        return $state;
    }

    // The saved statics written back, name by name
    /** @param array<string, mixed> $state */
    private function putBack(array $state): void
    {
        foreach ($state as $name => $value) {
            $property = new \ReflectionProperty(JsCase::class, $name);
            $property->setValue(null, $value);
        }
    }

    // One of the run's statics, read from the base class rather than from the subclass it is not declared on
    private function readShared(string $name): mixed
    {
        $property = new \ReflectionProperty(JsCase::class, $name);

        return $property->getValue();
    }

    // The browser the failed attempt launched before failing, closed rather than left running for the rest of the suite
    private function closeLaunched(?Browser $kept): void
    {
        $launched = $this->readShared('browser');

        if ($launched instanceof Browser && $launched !== $kept) {
            $launched->close();
        }
    }
}
