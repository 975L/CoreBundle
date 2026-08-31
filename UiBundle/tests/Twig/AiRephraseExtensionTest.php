<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Service\AiRephraseClient;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\TranslationFormContext;
use c975L\UiBundle\Twig\AiRephraseExtension;
use PHPUnit\Framework\TestCase;

class AiRephraseExtensionTest extends TestCase
{
    public function testGetFunctionsRegistersTheSixFunctions(): void
    {
        $extension = new AiRephraseExtension($this->createStub(AiRephraseClient::class), $this->createStub(ContentTranslator::class), new TranslationFormContext());

        $names = array_map(fn ($f) => $f->getName(), $extension->getFunctions());

        $this->assertSame(
            ['ai_rephrase_enabled', 'ai_rephrase_styles', 'ai_rephrase_lengths', 'ai_assistant_name', 'ai_translatable_locales', 'ai_translation_target'],
            $names,
        );
    }

    public function testAssistantNameIsHardcodedToDonovan(): void
    {
        $extension = new AiRephraseExtension($this->createStub(AiRephraseClient::class), $this->createStub(ContentTranslator::class), new TranslationFormContext());

        $this->assertSame('Donovan', $extension->assistantName());
    }
}
