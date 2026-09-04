<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\ConfigGroupLabelResolver;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// The "pick a group" screen reads in the admin's language, so it is ordered in it too
class ConfigGroupLabelResolverTest extends TestCase
{
    /**
     * The French labels of the groups that used to come out as "ai", "backup", "credits".
     *
     * @var array<string, string>
     */
    private const array LABELS = [
        'ai' => 'IA',
        'backup' => 'Sauvegarde',
        'credits' => 'Crédits',
        'email' => 'Email',
        'security' => 'Sécurité',
        'shop' => 'Boutique',
        'system' => 'Système',
    ];

    public function testAGroupIsNamedByItsTranslation(): void
    {
        $this->assertSame('Boutique', $this->resolver()->label('shop'));
    }

    // A config carrying no group has no name to show, and the screens display the empty string rather than "label.group_"
    public function testNoGroupIsNamedAtAll(): void
    {
        $this->assertSame('', $this->resolver()->label(null));
    }

    // "Sécurité" before "Système" is what a reader expects and what Collator gives; the fallback the resolver keeps for an install without ext-intl cannot tell, so the expectation is only asserted where the extension is there
    #[RequiresPhpExtension('intl')]
    public function testTheRowsAreOrderedOnWhatTheAdminReads(): void
    {
        $counts = ['ai' => 2, 'backup' => 6, 'credits' => 3, 'email' => 9, 'security' => 4, 'shop' => 5, 'system' => 7];

        $this->assertSame(
            ['shop', 'credits', 'email', 'ai', 'backup', 'security', 'system'],
            array_keys($this->resolver()->sortByLabel($counts)),
        );
    }

    // The count belongs to its group, and the reordering carries it along
    public function testTheCountsFollowTheirGroup(): void
    {
        $sorted = $this->resolver()->sortByLabel(['system' => 7, 'shop' => 5]);

        $this->assertSame(['shop' => 5, 'system' => 7], $sorted);
    }

    public function testNothingToSortComesBackEmpty(): void
    {
        $this->assertSame([], $this->resolver()->sortByLabel([]));
    }

    // The order the screen is read in: what the site says of itself, then what its bundles brought, then what is touched once
    public function testTheBandsComeInTheOrderTheScreenDrawsThem(): void
    {
        $bands = $this->resolver()->bands(['system' => 7, 'shop' => 5, 'email' => 9, 'backup' => 6]);

        $this->assertSame(['site', 'features', 'technical'], array_keys($bands));
        $this->assertSame(['email' => 9], $bands['site']);
        $this->assertSame(['shop' => 5], $bands['features']);
        $this->assertSame(['backup' => 6, 'system' => 7], $bands['technical']);
    }

    // A drawer a bundle named and this class knows nothing of - which is what every satellite's is - belongs to what that bundle brought
    public function testADrawerNamedByABundleFallsUnderTheFeatures(): void
    {
        $bands = $this->resolver()->bands(['book' => 18, 'gallery' => 24]);

        $this->assertSame(['features'], array_keys($bands));
        $this->assertSame(['book' => 18, 'gallery' => 24], $bands['features']);
    }

    // A site running no satellite has no feature drawer to show, and the band is left out rather than drawn with a heading over nothing
    public function testABandHoldingNothingIsLeftOut(): void
    {
        $bands = $this->resolver()->bands(['system' => 7]);

        $this->assertSame(['technical' => ['system' => 7]], $bands);
        $this->assertSame([], $this->resolver()->bands([]));
    }

    private function resolver(): ConfigGroupLabelResolver
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $key): string => self::LABELS[str_replace('label.group_', '', $key)] ?? $key
        );

        return new ConfigGroupLabelResolver($translator);
    }
}
