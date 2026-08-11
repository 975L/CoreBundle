<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\CspNonceProvider;
use PHPUnit\Framework\TestCase;

// The listener itself is final, which is the whole reason this provider stands in front of it - so what can be tested here is the case it was made optional for
class CspNonceProviderTest extends TestCase
{
    // A site that configured no csp section gets no listener at all from NelmioSecurityBundle: it must come out as "no nonce", never as a container that refuses to compile
    public function testItAnswersAnEmptyNonceWithoutAListener(): void
    {
        $this->assertSame('', (new CspNonceProvider())->styleNonce());
    }
}
