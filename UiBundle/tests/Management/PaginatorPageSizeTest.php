<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\UiBundle\Management\PaginatorPageSize;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

// "pageSize" reaches this straight from the url, so what it accepts is what ends up in a LIMIT clause
class PaginatorPageSizeTest extends TestCase
{
    private function resolve(?array $query, ?SessionInterface $session = null): int
    {
        $requestStack = new RequestStack();
        if (null !== $query) {
            $request = new Request($query);
            if (null !== $session) {
                $request->setSession($session);
            }

            $requestStack->push($request);
        }

        return new PaginatorPageSize($requestStack)->resolve();
    }

    private function session(): SessionInterface
    {
        return new Session(new MockArraySessionStorage());
    }

    public function testAnOfferedSizeIsUsed(): void
    {
        $this->assertSame(100, $this->resolve(['pageSize' => '100']));
    }

    public function testTheDefaultAppliesWithoutTheParameter(): void
    {
        $this->assertSame(PaginatorPageSize::DEFAULT_SIZE, $this->resolve([]));
    }

    // Outside the CRUD pages (the dashboard itself, a console command...) there is no request to read
    public function testTheDefaultAppliesWithoutARequest(): void
    {
        $this->assertSame(PaginatorPageSize::DEFAULT_SIZE, $this->resolve(null));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function rejectedValueProvider(): array
    {
        return [
            // The whole point of the whitelist: a size of its own choosing would let anyone ask the database for every row at once
            'a size that is not offered' => ['500'],
            'zero' => ['0'],
            'a negative size' => ['-20'],
            'a word' => ['all'],
            // Would make InputBag::get() throw a 400 if it were read through query->getInt()
            'an array' => [['20']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedValueProvider')]
    public function testAnythingElseFallsBackToTheDefault(mixed $value): void
    {
        $this->assertSame(PaginatorPageSize::DEFAULT_SIZE, $this->resolve(['pageSize' => $value]));
    }

    // The paginator offers the sizes as links and this validates them: a size offered but rejected would send the admin back to 20 rows on every click
    public function testTheDefaultIsOneOfTheOfferedSizes(): void
    {
        $this->assertContains(PaginatorPageSize::DEFAULT_SIZE, PaginatorPageSize::SIZES);
    }

    // The whole point of remembering it: EasyAdmin drops "pageSize" from the edit link, so the request coming back from a saved record carries nothing
    public function testTheLastChosenSizeAppliesWithoutTheParameter(): void
    {
        $session = $this->session();

        $this->resolve(['pageSize' => '100'], $session);

        $this->assertSame(100, $this->resolve([], $session));
    }

    public function testTheUrlWinsOverTheRememberedSize(): void
    {
        $session = $this->session();

        $this->resolve(['pageSize' => '100'], $session);

        $this->assertSame(50, $this->resolve(['pageSize' => '50'], $session));
        $this->assertSame(50, $this->resolve([], $session));
    }

    // A forged parameter must not outlive its own request, or a rejected size would settle in as the admin's choice - it is read as no choice at all, which is the size already remembered
    public function testARejectedSizeIsNotRemembered(): void
    {
        $session = $this->session();

        $this->resolve(['pageSize' => '100'], $session);

        $this->assertSame(100, $this->resolve(['pageSize' => '500'], $session));
        $this->assertSame(100, $this->resolve([], $session));
    }

    // Nothing but the whitelist comes out, whatever a tampered session holds
    public function testATamperedRememberedSizeFallsBackToTheDefault(): void
    {
        $session = $this->session();
        $session->set('c975l_ui.paginator_page_size', 5000);

        $this->assertSame(PaginatorPageSize::DEFAULT_SIZE, $this->resolve([], $session));
    }
}
