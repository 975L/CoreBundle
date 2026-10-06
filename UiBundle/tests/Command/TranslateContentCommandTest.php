<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\TranslateContentCommand;
use c975L\UiBundle\Contract\TranslatableTextProviderInterface;
use c975L\UiBundle\Service\AiRephraseClient;
use PHPUnit\Framework\TestCase;

// What the command has of its own: which texts it keeps, and what it does with a call that comes back empty
class TranslateContentCommandTest extends TestCase
{
    /** @param list<TranslatableTextProviderInterface> $providers */
    private function command(array $providers = [], ?AiRephraseClient $client = null): TranslateContentCommand
    {
        $command = new \ReflectionClass(TranslateContentCommand::class)->newInstanceWithoutConstructor();
        new \ReflectionProperty(TranslateContentCommand::class, 'providers')->setValue($command, $providers);

        if (null !== $client) {
            new \ReflectionProperty(TranslateContentCommand::class, 'aiRephraseClient')->setValue($command, $client);
        }

        return $command;
    }

    /** @param list<array{owner: string, ownerId: int, field: string, source: string, label: string}> $rows */
    private function provider(array $rows): TranslatableTextProviderInterface
    {
        $provider = $this->createStub(TranslatableTextProviderInterface::class);
        $provider->method('getTranslatableTexts')->willReturn($rows);

        return $provider;
    }

    // Every bundle's texts are gathered, and "--owner" keeps those of one kind alone
    public function testItGathersEveryProviderAndNarrowsToOneOwner(): void
    {
        $page = ['owner' => 'site_page', 'ownerId' => 1, 'field' => 'title', 'source' => 'Accueil', 'label' => 'Page home'];
        $form = ['owner' => 'ui_form_field', 'ownerId' => 3, 'field' => 'label', 'source' => 'Nom', 'label' => 'Form contact'];
        $command = $this->command([$this->provider([$page]), $this->provider([$form])]);
        $rows = new \ReflectionMethod(TranslateContentCommand::class, 'rows');

        $this->assertSame([$page, $form], $rows->invoke($command, null));
        $this->assertSame([$form], $rows->invoke($command, 'ui_form_field'));
    }

    // A first call refused - the rate limit of a provider selling its api by the minute - is asked again rather than lost
    public function testItAsksASecondTimeWhenTheFirstCallComesBackEmpty(): void
    {
        $client = $this->createMock(AiRephraseClient::class);
        $client->expects($this->exactly(2))->method('translate')->willReturn(null, 'Our bundles');

        $this->assertSame('Our bundles', $this->translate($client, 'Nos bundles', 'en'));
    }

    // Asked to put "Nom" into English, the provider answered "EN" - the language it was told to translate into rather than the word - and that answer was stored and shown as the label of the contact form's first field
    public function testAnAnswerThatIsJustTheLanguageCodeIsNoTranslationAtAll(): void
    {
        $client = $this->createStub(AiRephraseClient::class);
        $client->method('translate')->willReturn('EN');

        $this->assertNull($this->translate($client, 'Nom', 'en'));
    }

    // The no-regression contract: a real translation that happens to be short is stored like any other
    public function testARealTranslationIsStoredWhateverItsLength(): void
    {
        $client = $this->createStub(AiRephraseClient::class);
        $client->method('translate')->willReturn('Name');

        $this->assertSame('Name', $this->translate($client, 'Nom', 'en'));
    }

    // "✓ | ✗" came back as "Yes", "run.as" as "run.es": a text reading the same in every language is kept without a call
    public function testANeutralTextIsKeptWithoutACall(): void
    {
        $client = $this->createMock(AiRephraseClient::class);
        $client->expects($this->never())->method('translate');

        $this->assertSame('✓ | ✗', $this->translate($client, '✓ | ✗', 'en'));
        $this->assertSame('Run.as', $this->translate($client, 'Run.as', 'es'));
    }

    // "✓ | rare" came back as "rare": only the cell holding words is asked for, the others staying where they were
    public function testOnlyTheCellsHoldingWordsAreAskedFor(): void
    {
        $client = $this->createMock(AiRephraseClient::class);
        $client->expects($this->once())->method('translate')->with('rare', 'es')->willReturn('raro');

        $this->assertSame('✓ | raro | ✗', $this->translate($client, '✓ | rare | ✗', 'es'));
    }

    // A sentence is still a sentence: neither its letters nor its dot make it neutral
    public function testASentenceIsNotNeutral(): void
    {
        $this->assertFalse(TranslateContentCommand::isNeutral('Nom'));
        $this->assertFalse(TranslateContentCommand::isNeutral('Créez votre lien sur run.as.'));
        $this->assertTrue(TranslateContentCommand::isNeutral('12 €'));
    }

    private function translate(AiRephraseClient $client, string $source, string $locale): ?string
    {
        return new \ReflectionMethod(TranslateContentCommand::class, 'translate')
            ->invoke($this->command(client: $client), $source, $locale, 0);
    }
}
