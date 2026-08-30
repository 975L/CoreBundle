<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use HeadlessChromium\BrowserFactory;

/**
 * Measures a rendered page against a handful of layout invariants - the defects a stylesheet can carry
 * without any rule looking wrong on its own, which ComponentCenteringTest and the other sass tests cannot
 * see because they never lay anything out.
 *
 * Deliberately NOT a HealthCheckProviderInterface: those run from c975l:health-check:run, in cron, on the
 * production server, which has no browser and no way to install one. This drives a local Chrome instead,
 * from a command run by hand or by ComposerUpdate.sh, and reports rather than gates - a browser is never
 * deterministic enough to block a push on.
 *
 * chrome-php/chrome is a require-dev of this bundle: nothing here ever runs on a deployed site.
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
class LayoutAuditor
{
    // Widths a page is measured at: one below the first breakpoint, one above the last
    public const DEFAULT_WIDTHS = [390, 1280];

    // Two CSS pixels: what a sub-pixel layout rounds to either side, below the asymmetry a lost centering leaves. Read twice - as that asymmetry, and as the room a box needs to spare before being centred means anything at all
    private const int CENTERING_TOLERANCE = 2;

    // Long enough for the reveals to land once their duration has been zeroed
    private const int SETTLE_MICROSECONDS = 400000;

    public function __construct(
        private readonly string $chromeBinary = 'google-chrome',
    ) {
    }

    // Whether the optional browser dependency is installed at all
    public static function isAvailable(): bool
    {
        return class_exists(BrowserFactory::class);
    }

    /**
     * @return list<array{width: int, check: string, selector: string, summary: string}>
     */
    public function audit(string $url, array $widths = self::DEFAULT_WIDTHS): array
    {
        $browser = new BrowserFactory($this->chromeBinary)->createBrowser([
            'headless' => true,
            // The managed server and most CI images run as a user Chrome's sandbox refuses to start for
            'noSandbox' => true,
            'windowSize' => [max($widths), 900],
        ]);

        $findings = [];

        try {
            foreach ($widths as $width) {
                foreach ($this->measure($browser, $url, (int) $width) as $finding) {
                    $findings[] = $finding + ['width' => (int) $width];
                }
            }
        } finally {
            $browser->close();
        }

        return $findings;
    }

    // One page load per width, the layout being read only once it has settled
    private function measure(object $browser, string $url, int $width): array
    {
        $page = $browser->createPage();
        $page->setViewport($width, 900);
        $page->navigate($url)->waitForNavigation();

        $page->evaluate($this->settleScript());
        // Lets the reveals the scroll above triggered land, and any lazy image take its slot
        usleep(self::SETTLE_MICROSECONDS);

        $measured = json_decode((string) $page->evaluate($this->script())->getReturnValue(), true);
        $page->close();

        // Never read as "nothing found": a script that failed to run would otherwise report a clean page
        if (!is_array($measured)) {
            throw new \RuntimeException(sprintf('The measuring script returned nothing usable at %dpx.', $width));
        }

        return $measured;
    }

    // Brings the page to the state a visitor ends up looking at, which is not the state it loads in: the block animations park their blocks off-screen until animate-scroll.js reveals them, so a page measured on load reports half its blocks as overflowing, every one of them a false positive.
    private function settleScript(): string
    {
        return <<<'JS'
            (() => {
                const style = document.createElement('style');
                style.textContent = '*, *::before, *::after { animation-duration: 0s !important;'
                    + ' animation-delay: 0s !important; transition-duration: 0s !important; }';
                document.head.appendChild(style);

                // Every reveal fires on its own intersection, so the whole page has to be walked past
                for (let y = 0; y < document.body.scrollHeight; y += window.innerHeight / 2) {
                    window.scrollTo(0, y);
                }

                window.scrollTo(0, 0);

                return true;
            })()
            JS;
    }

    // Returned as findings rather than judged in JS, so the rules stay readable and the severities stay in PHP
    // #lizard forgives - the length is a heredoc's, one JavaScript program held as a string
    private function script(): string
    {
        $tolerance = self::CENTERING_TOLERANCE;

        return <<<JS
            (() => {
                const viewport = document.documentElement.clientWidth;
                const viewportHeight = window.innerHeight;

                // A vertical scrollbar takes its width off the viewport but not off "100vw", so every full-bleed section hangs half of that over each edge - the frame it is laid out on, not a defect
                const scrollbar = window.innerWidth - viewport;
                const findings = [];

                const name = el => el.tagName.toLowerCase()
                    + (el.id ? '#' + el.id : '')
                    + (typeof el.className === 'string' && el.className.trim()
                        ? '.' + el.className.trim().split(/\\s+/).join('.') : '');

                // A scrolling or clipping ancestor is placing its children on purpose - the freeflow slider lays every slide out past the right edge and lets the user scroll to them
                const isReleased = el => {
                    for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
                        const s = getComputedStyle(p);
                        if (s.overflowX !== 'visible' || s.position === 'fixed') return true;
                    }
                    return false;
                };

                // Its real layout parent, the "display: contents" wrappers the block system nests being no box at all
                const isLaidOutByParent = el => {
                    for (let p = el.parentElement; p; p = p.parentElement) {
                        const display = getComputedStyle(p).display;
                        if (display === 'contents') continue;

                        return display.includes('flex') || display.includes('grid');
                    }
                    return false;
                };

                document.querySelectorAll('body *').forEach(el => {
                    const box = el.getBoundingClientRect();
                    const style = getComputedStyle(el);

                    if (box.width === 0 || style.position === 'fixed' || style.visibility === 'hidden') return;

                    // The page is wider than the window: something breaks out of the frame and adds a scrollbar
                    if (!isReleased(el) && (box.right > viewport + scrollbar + 1 || box.left < -scrollbar - 1)) {
                        findings.push({
                            check: 'overflow',
                            selector: name(el),
                            summary: 'renders from ' + Math.round(box.left) + 'px to ' + Math.round(box.right)
                                + 'px, past the ' + (viewport + scrollbar) + 'px frame',
                            element: el,
                        });
                    }

                });

                // Centering has to be read from the stylesheet, never from the element: once a stronger rule has written the margin shorthand over it the computed value is "0px", and nothing left on the element says it was ever meant to be centred. The rule that declared the intent still says so, which is what makes the loss detectable at all.
                const centeredSelectors = new Set();

                for (const sheet of document.styleSheets) {
                    let rules;
                    // A cross-origin stylesheet exposes no rules at all, and must not take the audit down
                    try { rules = sheet.cssRules; } catch (e) { continue; }

                    for (const rule of rules) {
                        if (rule.style && rule.style.marginLeft === 'auto' && rule.style.marginRight === 'auto') {
                            centeredSelectors.add(rule.selectorText);
                        }
                    }
                }

                centeredSelectors.forEach(selector => {
                    let elements;
                    try { elements = document.querySelectorAll(selector); } catch (e) { return; }

                    elements.forEach(el => {
                        const box = el.getBoundingClientRect();
                        const style = getComputedStyle(el);

                        // An auto margin only centres a block-level box left in the flow - "relative" stays in it
                        if (box.width === 0 || style.display.startsWith('inline')) return;
                        if (style.position === 'absolute' || style.position === 'fixed') return;

                        // Only a box holding its own measure: without a max-width it fills its container, and any narrower width it ends up with was decided by something else - a grid track, a shrink-to-fit. Read from max-width alone, "width" being resolved to pixels by then
                        if (style.maxWidth === 'none') return;

                        // Laid out by its container rather than by its own margins, as ".cards > .card" is
                        if (isLaidOutByParent(el)) return;

                        const parent = el.offsetParent || document.documentElement;
                        const p = parent.getBoundingClientRect();
                        const left = box.left - p.left;
                        const right = p.right - box.right;

                        if (box.width >= p.width - $tolerance || Math.abs(left - right) <= $tolerance) return;

                        findings.push({
                            check: 'centering',
                            selector: name(el),
                            summary: '"' + selector + '" centres it, but it sits ' + Math.round(left)
                                + 'px from the left and ' + Math.round(right) + 'px from the right',
                            element: el,
                        });
                    });
                });

                // An intrinsic "height" attribute beating a CSS aspect-ratio blows the box up to the pixel count of the source file - which is what happened to every hero in v1.10.1
                document.querySelectorAll('img').forEach(img => {
                    const box = img.getBoundingClientRect();

                    if (box.height > viewportHeight && img.naturalHeight && box.height > img.naturalHeight * 0.9) {
                        findings.push({
                            check: 'image-size',
                            selector: name(img),
                            summary: 'renders ' + Math.round(box.height) + 'px tall, past the '
                                + viewportHeight + 'px window, for a source of ' + img.naturalHeight + 'px',
                            element: img,
                        });
                    }
                });

                // One finding per element and kind: two rules can centre the same box, and reporting the loss twice says nothing more than once
                const unique = findings.filter((finding, index) => index === findings.findIndex(other =>
                    other.check === finding.check && other.element === finding.element
                ));

                // Then only the outermost offender of each kind: a block breaking out of the frame drags every one of its descendants past the edge too, and naming all forty says nothing more than one.
                // Compared on the elements, "contains" holding for the node itself
                const outermost = unique.filter(finding => !unique.some(other =>
                    other.element !== finding.element
                        && other.check === finding.check
                        && other.element.contains(finding.element)
                ));

                return JSON.stringify(outermost.map(({element, ...finding}) => finding));
            })()
            JS;
    }
}
