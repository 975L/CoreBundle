<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Service\CspFormAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class CspFormActionTest extends TestCase
{
    // A response without any policy header is not one to widen
    public function testHasNoPolicyWithoutHeader(): void
    {
        $this->assertFalse(CspFormAction::hasPolicy(new Response()));
    }

    // The report-only header alone is a policy too
    public function testHasPolicyWithReportOnlyHeader(): void
    {
        $response = new Response();
        $response->headers->set('Content-Security-Policy-Report-Only', "form-action 'self'");

        $this->assertTrue(CspFormAction::hasPolicy($response));
    }

    // A directive leading the policy is widened, a source already there not repeated
    public function testWidensALeadingDirectiveWithoutDuplicate(): void
    {
        $response = new Response();
        $response->headers->set('Content-Security-Policy-Report-Only', "form-action 'self' papacalin:; default-src 'self'");

        CspFormAction::widen($response, ['papacalin:', 'checkout.stripe.com']);

        $this->assertSame("form-action 'self' papacalin: checkout.stripe.com; default-src 'self'", $response->headers->get('Content-Security-Policy-Report-Only'));
    }

    // The directive is matched whatever its case
    public function testWidensACaseInsensitiveDirective(): void
    {
        $response = new Response();
        $response->headers->set('Content-Security-Policy', "default-src 'self'; Form-Action 'self'");

        CspFormAction::widen($response, ['papacalin:']);

        $this->assertSame("default-src 'self'; form-action 'self' papacalin:", $response->headers->get('Content-Security-Policy'));
    }

    // No source to add leaves the policy as it stands
    public function testLeavesThePolicyWithoutSource(): void
    {
        $response = new Response();
        $response->headers->set('Content-Security-Policy', "form-action 'none'");

        CspFormAction::widen($response, []);

        $this->assertSame("form-action 'none'", $response->headers->get('Content-Security-Policy'));
    }
}
