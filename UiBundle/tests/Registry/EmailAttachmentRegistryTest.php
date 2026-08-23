<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\EmailAttachmentProviderInterface;
use c975L\UiBundle\Model\EmailAttachment;
use c975L\UiBundle\Registry\EmailAttachmentRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// What an email carries: the kinds an admin ticked in the builder, drawn by whichever bundle owns each of them
class EmailAttachmentRegistryTest extends TestCase
{
    public function testEveryInstalledBundlesDocumentsAreOffered(): void
    {
        $registry = $this->registry(['invoice' => 'A'], ['legal:france/terms-of-sales' => 'B']);

        $this->assertSame(['invoice', 'legal:france/terms-of-sales'], array_keys($registry->getKinds()));
    }

    public function testEachKindIsDrawnByTheBundleOwningIt(): void
    {
        $registry = $this->registry(['invoice' => 'the invoice'], ['legal:france/terms-of-sales' => 'the terms']);

        $attachments = $registry->resolve(['legal:france/terms-of-sales', 'invoice']);

        $this->assertSame(['the terms', 'the invoice'], array_map(static fn (EmailAttachment $a): string => $a->content, $attachments));
    }

    // A site that removed the bundle drawing it keeps the row naming it, and the order confirmation still has to go out
    public function testAKindNobodyOwnsIsSkipped(): void
    {
        $this->assertSame([], $this->registry(['invoice' => 'A'])->resolve(['gone']));
    }

    // An order holding no gift card, an invoice not drawn yet: the ordinary nothing-to-attach, which is not an error
    public function testAProviderWithNothingToAttachIsSkipped(): void
    {
        $registry = $this->registry(['invoice' => null], ['legal:france/terms-of-sales' => 'the terms']);

        $this->assertCount(1, $registry->resolve(['invoice', 'legal:france/terms-of-sales']));
    }

    // What the caller was sending about reaches whoever draws the document - the order, and the language it was placed in
    public function testTheContextReachesTheProvider(): void
    {
        $seen = [];
        $registry = new EmailAttachmentRegistry();
        $registry->addProvider(new class ($seen) implements EmailAttachmentProviderInterface {
            public function __construct(private array &$seen)
            {
            }

            public function getAttachmentKinds(): array
            {
                return ['invoice' => new class implements TranslatableInterface {
                    public function trans(TranslatorInterface $translator, ?string $locale = null): string
                    {
                        return 'Invoice';
                    }
                }];
            }

            public function createAttachment(string $kind, array $context): ?EmailAttachment
            {
                $this->seen = $context;

                return null;
            }
        });

        $registry->resolve(['invoice'], ['locale' => 'fr']);

        $this->assertSame(['locale' => 'fr'], $seen);
    }

    /** @param array<string, ?string> ...$providers kind => what it draws, or null for nothing to attach */
    private function registry(array ...$providers): EmailAttachmentRegistry
    {
        $registry = new EmailAttachmentRegistry();

        foreach ($providers as $kinds) {
            $registry->addProvider(new readonly class ($kinds) implements EmailAttachmentProviderInterface {
                public function __construct(private array $kinds)
                {
                }

                public function getAttachmentKinds(): array
                {
                    return array_map(static fn (): TranslatableInterface => new class implements TranslatableInterface {
                        public function trans(TranslatorInterface $translator, ?string $locale = null): string
                        {
                            return 'Label';
                        }
                    }, $this->kinds);
                }

                public function createAttachment(string $kind, array $context): ?EmailAttachment
                {
                    $content = $this->kinds[$kind] ?? null;

                    return null === $content ? null : new EmailAttachment('file.pdf', $content);
                }
            });
        }

        return $registry;
    }
}
