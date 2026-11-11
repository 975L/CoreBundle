<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// The gate stands in place of its content, never next to it: what only an adult may see is not in the html of a page drawn for someone not verified
class AgeGateTest extends TestCase
{
    private bool $urlAsked = false;

    public function testAClosedGateHoldsTheGoCamFrameAndNotTheContent(): void
    {
        $html = $this->render(true);

        $this->assertStringContainsString('data-controller="age-gate"', $html);
        $this->assertStringContainsString('https://go.cam/fr/token/iframeCheck', $html);
        $this->assertStringNotContainsString('adult-only-content', $html);
    }

    // No nonce drawn when the gate is open: nothing written to the session for a visitor who has nothing to verify
    public function testAnOpenGateRendersTheContentAlone(): void
    {
        $html = $this->render(false);

        $this->assertStringContainsString('adult-only-content', $html);
        $this->assertStringNotContainsString('age-gate', $html);
        $this->assertFalse($this->urlAsked);
    }

    private function render(bool $closed): string
    {
        $this->urlAsked = false;
        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['page.html.twig' => "{% extends 'components/AgeGate.html.twig' %}{% block content %}adult-only-content{% endblock %}"]),
            new FilesystemLoader(dirname(__DIR__, 2) . '/templates'),
        ]));
        $twig->addFunction(new TwigFunction('age_gate_closed', static fn (mixed $age): bool => $closed && '18' === $age));
        $twig->addFunction(new TwigFunction('age_verification_url', function (): string {
            $this->urlAsked = true;

            return 'https://go.cam/fr/token/iframeCheck?p=1&d=x';
        }));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/age-verification'));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig->render('page.html.twig', ['age' => '18']);
    }
}
