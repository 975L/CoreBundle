<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use PHPUnit\Framework\TestCase;

// Every rich-text field of the dashboard offers the rephrase toolbar, whichever of the two editors renders it: UiBundle's own TrixEditorType inside a block, EasyAdmin's TextEditorField in every other bundle's CRUD
class AiRephraseThemeTest extends TestCase
{
    private function theme(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/templates/form/block_theme.html.twig');
    }

    public function testBothRichEditorsIncludeTheToolbar(): void
    {
        $theme = $this->theme();

        foreach (['trix_editor_widget', 'ea_text_editor_widget'] as $block) {
            $this->assertMatchesRegularExpression(
                '/{% block ' . $block . ' %}.*?_ai_rephrase\.html\.twig.*?{% endblock/s',
                $theme,
                sprintf('"%s" no longer includes the rephrase toolbar, so its fields silently lose it.', $block)
            );
        }
    }

    // EasyAdmin renders the textarea before the editor wrapper, so a toolbar included from textarea_widget would land above the editor instead of under it, as it does on a block
    public function testTheEasyAdminToolbarComesAfterTheEditor(): void
    {
        $theme = $this->theme();

        $this->assertMatchesRegularExpression(
            '/{% block ea_text_editor_widget %}.*?<trix-editor.*?_ai_rephrase\.html\.twig/s',
            $theme
        );
    }

    // A plain textarea also carries raw JSON and config values dashboard-wide, hence the opt-in rather than the unconditional include of the two above
    public function testThePlainTextareaStaysOptIn(): void
    {
        $this->assertMatchesRegularExpression(
            "/{% block textarea_widget %}.*?attr\['data-ai-rephrase'\] is defined.*?{% endblock/s",
            $this->theme()
        );
    }
}
