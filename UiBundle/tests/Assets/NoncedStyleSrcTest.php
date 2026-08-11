<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// EasyAdmin's layout, and this bundle's own since it calls csp_nonce('style'), put a nonce on style-src - which from CSP2 on makes 'unsafe-inline' a no-op for that directive. A nonce authorizes an *element*, never an inline style *attribute*, so a rule a script writes onto an element is simply dropped by the browser: silently, on the sites having a CSP, and nowhere else. Nothing here can be proved from PHP; what is locked is that no script goes back to writing one, and that every class they toggle instead actually carries its rule.
class NoncedStyleSrcTest extends TestCase
{
    private const array CSS = ['/public/css/management.css', '/public/css/management.min.css'];

    // Each class a script toggles, with the declaration that has to be found in the compiled sheets for it to do anything at all
    private const array TOGGLED = [
        'ui-row-header-flex' => 'display:flex',
        'ui-row-toolbar' => 'margin-left:auto',
        'ui-toolbar-btn' => 'flex-shrink:0',
        'ui-sort-grabbable' => 'cursor:grab',
        'ui-dragging-visual' => 'box-shadow:0 0 0 2px',
        'ui-hidden' => 'display:none',
        'ui-media-preview' => 'max-width:200px',
        'ui-form-field-template-picker' => 'display:inline-block',
    ];

    // The assignment forms a nonce never covers - the ones every one of these scripts used before
    public function testNoScriptWritesAStyleOntoAnElement(): void
    {
        foreach ($this->scripts() as $file => $js) {
            $this->assertDoesNotMatchRegularExpression('/\.style\.[A-Za-z]+\s*=/', $js, $file . ' assigns an inline style, which a nonced style-src drops.');
            $this->assertStringNotContainsString('.style.cssText', $js, $file . ' writes a whole inline style declaration, which a nonced style-src drops.');
            $this->assertDoesNotMatchRegularExpression('/setAttribute\(\s*[\'"]style[\'"]/', $js, $file . ' sets the style attribute, which a nonced style-src drops.');
        }
    }

    // setProperty() is the one call that reads the same on an element and on a CSSOM rule, and only the second is authorized - a file making it has to be getting its sheet from the helper
    public function testAScriptSettingAPropertyGoesThroughTheNoncedElement(): void
    {
        foreach ($this->scripts() as $file => $js) {
            if (!str_contains($js, '.style.setProperty')) {
                continue;
            }

            $this->assertStringContainsString('createNoncedStyleElement', $js, $file . ' sets a property without taking its sheet from the nonced element, so it is writing onto an element.');
        }
    }

    // A class that nothing declares leaves the script doing nothing, and there is no error to show for it
    public function testEveryClassAScriptTogglesCarriesItsRule(): void
    {
        $scripts = implode('', $this->scripts());

        foreach (self::TOGGLED as $class => $declaration) {
            $this->assertStringContainsString($class, $scripts, 'No script names .' . $class . ' any more, this expectation is stale.');

            foreach (self::CSS as $file) {
                $this->assertMatchesRegularExpression(
                    '/\.' . preg_quote($class, '/') . '[^{]*\{[^}]*' . preg_quote($declaration, '/') . '/',
                    $this->css($file),
                    sprintf('%s declares nothing for .%s, so the script toggles a class with no effect.', $file, $class)
                );
            }
        }
    }

    // Measured coordinates can be carried by no class, so the overlay owns a sheet of its own - one element, its rule mutated through the CSSOM on every scroll rather than its text rewritten, which would re-parse the sheet each time
    public function testTheOverlayWritesItsCoordinatesIntoANoncedElement(): void
    {
        $js = $this->js('block-edit-overlay.js');

        $this->assertStringContainsString('createNoncedStyleElement()', $js, 'The overlay no longer builds a nonced style element, so its coordinates are dropped.');
        $this->assertStringContainsString('insertRule(".block-edit-overlay-btn{top:0;left:0}"', $js, 'The rule the coordinates are written into is gone.');
        $this->assertStringContainsString('this.positionStyle?.remove()', $js, 'The sheet outlives the controller, so a reconnect leaves a stale one in the head.');
        $this->assertStringContainsString('this.positionRule = null', $js, 'The rule is kept across a disconnect, so the next connect mutates a detached one.');
    }

    // ES modules never populate document.currentScript, the usual way to read one's own nonce, so it is copied off an element the page already carries
    public function testTheHelperCopiesTheNonceThePageAlreadyCarries(): void
    {
        $js = $this->js('nonced-style-element.js');

        $this->assertStringContainsString("querySelector('[nonce]')", $js, 'The helper no longer reads the page nonce, so its element is dropped by the browser.');
        $this->assertStringContainsString('style.nonce = nonce', $js, 'The nonce is read but never carried by the element.');
    }

    // NelmioSecurityBundle mints one nonce value per response and shares it between the directives (ContentSecurityPolicyListener::doGetNonce memoizes it); what getNonce('style') decides is whether that value is declared in style-src at all.
    // So the nonce read off importmap()'s <script> - the first [nonce] on one of these pages - is a value style-src may well not list, which is what got the <style> blocked. A style-src carrier has to be preferred: a stylesheet <link>, or an earlier <style>, both of which only exist because style-src was asked for its nonce
    public function testTheHelperPrefersACarrierStyleSrcItselfAuthorized(): void
    {
        $js = $this->js('nonced-style-element.js');

        $this->assertStringContainsString('link[rel~="stylesheet"][nonce], style[nonce]', $js, 'The helper copies the nonce off the first [nonce] element, which is a <script>: style-src does not carry that one.');
        $this->assertLessThan(
            strpos($js, "querySelector('[nonce]')"),
            strpos($js, 'link[rel~="stylesheet"][nonce]'),
            'The plain [nonce] lookup must stay the fallback, not the first choice.'
        );
    }

    // Turbo swaps the <body> and leaves the <meta> the layout wrote alone, so it is the one nonce still matching the CSP header the document was served with - every element the new body brings in carries the next response's, which the browser blocks. Reading one of those left an editor's hover button unplaced from the first navigation on, and would do the same to a slider or an image comparison
    public function testTheHelperReadsTheNonceOfTheDocumentRatherThanOfTheLastResponse(): void
    {
        $js = $this->js('nonced-style-element.js');

        $this->assertStringContainsString('meta[name="csp-nonce"]', $js, 'The helper no longer reads the document\'s own nonce, which is the only one its CSP authorizes after a Turbo navigation.');
        $this->assertLessThan(
            strpos($js, 'link[rel~="stylesheet"][nonce]'),
            strpos($js, 'meta[name="csp-nonce"]'),
            'The element lookup must stay the fallback: those carry the nonce of the response that brought them in, not the document\'s.'
        );
    }

    // The decoy field hid itself with a "style" attribute, which a nonce can never authorize: the rules were dropped and every visitor was shown a field asking for a "Department" - a bot trap turned into a form field
    public function testTheHoneypotIsTakenOffScreenByAClassRatherThanAStyleAttribute(): void
    {
        $php = (string) file_get_contents(\dirname(__DIR__, 2) . '/src/Service/FormBotProtection.php');

        $this->assertStringNotContainsString("'style' =>", $php, 'The honeypot hides itself with a style attribute, which a nonce-based style-src drops.');
        $this->assertStringContainsString("'class' => 'ui-field-aside'", $php);
        $this->assertMatchesRegularExpression('/\.ui-field-aside \{[^}]*position: absolute;/s', (string) file_get_contents(\dirname(__DIR__, 2) . '/sass/_forms.scss'));
    }

    // A rejected element exposes no sheet at all: reading through it throws out of a pointer handler and takes the page's other controllers down with it
    public function testTheOverlayGivesUpRatherThanThrowingOnARejectedElement(): void
    {
        $js = $this->js('block-edit-overlay.js');

        $this->assertStringContainsString('if (this.positionStyle.sheet)', $js, 'The overlay writes into the sheet of an element the CSP may have rejected, with no guard.');
        $this->assertStringContainsString('this.positionUnavailable = true', $js, 'Nothing remembers the rejection, so one dead <style> is appended per pointer move.');
    }

    // Comments stripped: they spell out the very forms being forbidden, and would answer for the code
    private function scripts(): array
    {
        $scripts = [];

        foreach (glob(\dirname(__DIR__, 2) . '/assets/js/*.js') ?: [] as $path) {
            $scripts[basename($path)] = (string) preg_replace(['#/\*.*?\*/#s', '#(^|\s)//.*$#m'], '', (string) file_get_contents($path));
        }

        $this->assertNotEmpty($scripts, 'No script was read at all, this test guards nothing.');

        return $scripts;
    }

    private function js(string $file): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/js/' . $file);
    }

    // Same shape whichever of the two stylesheets it comes from - only the space around the punctuation differs
    private function css(string $file): string
    {
        $css = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(\dirname(__DIR__, 2) . $file));

        return (string) preg_replace('#\s*([:;{},/])\s*#', '$1', $css);
    }
}
