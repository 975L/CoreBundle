<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The "france/terms-of-sales" model in each locale: the sections the satellite bundles add by name, the consumer mediator the site names, and digital items delivered on an account as well as for download (a card back, a credit), which the withdrawal waiver has to cover too
class TermsOfSalesModelTest extends TestCase
{
    /** @return iterable<array{string, string}> */
    public static function locales(): iterable
    {
        yield ['fr', 'compte'];
        yield ['en', 'account'];
        yield ['es', 'cuenta'];
    }

    #[DataProvider('locales')]
    public function testSatelliteSectionsAndAccountDelivery(string $locale, string $account): void
    {
        $template = (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/models/france/terms-of-sales.' . $locale . '.html.twig');

        $this->assertStringContainsString("{% include '@c975LPurchaseCredits/legal/terms-of-sales." . $locale . ".html.twig' ignore missing %}", $template);
        $this->assertStringContainsString("{% include '@c975LPayment/legal/terms-of-sales." . $locale . ".html.twig' ignore missing %}", $template);

        // The mediator the site names, the ministry's list kept for a site that names none
        $this->assertStringContainsString("{% if config('site-consumer-mediator') %}", $template);
        $this->assertStringContainsString("{{ legal_var('site-consumer-mediator') }}", $template);
        $this->assertStringContainsString('economie.gouv.fr/mediation-conso', $template);

        $delivery = substr($template, (int) strpos($template, 'data-legal-id="delivery"'), 600);
        $this->assertStringContainsString($account, $delivery);
    }

    // The shop ships by country zones, so the terms never narrow delivery to one territory
    #[DataProvider('languages')]
    public function testDeliveryFollowsTheDestinationsOfferedAtCheckout(string $locale): void
    {
        $template = $this->model('terms-of-sales', $locale);

        $this->assertDoesNotMatchRegularExpression('/métropolitaine|mainland France|Francia metropolitana/', $template);
    }

    // A consumer may always bring a dispute before the court of their own domicile, which an exclusive clause cannot take away
    #[DataProvider('jurisdictions')]
    public function testTheJurisdictionClauseKeepsTheConsumersOwnCourt(string $model, string $locale): void
    {
        $template = $this->model($model, $locale);

        $this->assertStringContainsString('R.631-3', $template);
        $this->assertDoesNotMatchRegularExpression('/professionnel\/les ou particuliers|professional or private users|profesionales (como|o) particulares/', $template);
    }

    /** @return iterable<array{string}> */
    public static function languages(): iterable
    {
        yield ['fr'];
        yield ['en'];
        yield ['es'];
    }

    /** @return iterable<array{string, string}> */
    public static function jurisdictions(): iterable
    {
        foreach (['terms-of-sales', 'terms-of-use'] as $model) {
            foreach (['fr', 'en', 'es'] as $locale) {
                yield $model . '.' . $locale => [$model, $locale];
            }
        }
    }

    private function model(string $model, string $locale): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/models/france/' . $model . '.' . $locale . '.html.twig');
    }
}
