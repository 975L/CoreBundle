<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\JsCase;
use PHPUnit\Framework\Attributes\Group;

// assets/js/image-zoom.js over the very markup templates/components/Image/Zoom.html.twig writes
// Everything worth checking here is something a browser does rather than a value: a native <dialog> really opening, a heavy file really not being fetched before it is asked for, and a link really being kept from navigating. And the arrangement itself is what a reading misses - a dialog left as the link's sibling is outside the controller's own element, where a target is never found and the first click throws
#[Group('browser')]
class ImageZoomBehaviourTest extends JsCase
{
    // The picture the page shows, the file the zoom opens, and the arrangement Zoom.html.twig lays them out in
    private const string MARKUP = '<span class="image-zoom" data-controller="imageZoom">
            <a href="/medias/photo-highres.webp" class="image-zoom__link" data-action="imageZoom#open" aria-label="Voir en haute résolution">
                <img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="Une photo">
            </a>
            <dialog class="image-zoom__dialog" data-imageZoom-target="dialog" data-action="click->imageZoom#close">
                <img class="image-zoom__image" data-imageZoom-target="image" alt="Une photo">
            </dialog>
        </span>';

    // What a browser makes of the component written inside a <p>: <dialog> closes the paragraph implicitly, and both the dialog and everything after it are lifted out of the element carrying the controller
    private const string MARKUP_HOISTED = '<span class="image-zoom" data-controller="imageZoom">
            <a href="/medias/photo-highres.webp" class="image-zoom__link" data-action="imageZoom#open" aria-label="Voir en haute résolution">
                <img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="Une photo">
            </a>
        </span>
        <dialog class="image-zoom__dialog" data-imageZoom-target="dialog" data-action="click->imageZoom#close">
            <img class="image-zoom__image" data-imageZoom-target="image" alt="Une photo">
        </dialog>';

    // A page showing a dozen pictures would otherwise fetch a dozen heavy files nobody asked for
    public function testTheHighResolutionIsOnlyFetchedWhenItIsAskedFor(): void
    {
        $opened = $this->zoom(
            'const before = image().getAttribute("src");
             link().click();

             return { before, after: image().getAttribute("src"), open: dialog().open };'
        );

        $this->assertNull($opened['before'], 'The high resolution is fetched for a picture nobody has asked to enlarge, over a page already carrying its medium file.');
        $this->assertStringEndsWith('/photo-highres.webp', (string) $opened['after'], 'Opening the zoom does not put the high resolution in it.');
        $this->assertTrue($opened['open'], 'The dialog was never opened.');
    }

    // The link is a real one, pointing at the file itself, so it still opens the high resolution when this script does not run
    public function testTheLinkIsOpenedByTheDialogRatherThanFollowedByTheBrowser(): void
    {
        $this->assertTrue(
            (bool) $this->zoom(
                'let prevented = false;
                 link().addEventListener("click", (event) => { prevented = event.defaultPrevented; });
                 link().click();

                 return prevented;'
            ),
            'The click is left to the browser, which leaves the page for the file instead of opening it over the page.'
        );
    }

    // Assigned on the first opening only, the browser cache serving the next ones - a src rewritten at each opening re-requests the file on a cold cache
    // Read from the address the link carries rather than by counting the writes: the link is given a second address between the two openings, and an image that followed it was assigned twice
    public function testTheFileIsAssignedOnceForAllTheOpenings(): void
    {
        $this->assertStringEndsWith(
            '/photo-highres.webp',
            (string) $this->zoom(
                'link().click();
                 dialog().close();
                 link().href = "/medias/une-autre-photo.webp";
                 link().click();

                 return image().getAttribute("src");'
            ),
            'The high resolution is assigned again at each opening rather than once.'
        );
    }

    // A click anywhere inside closes, which is why the dialog carries no close button of its own
    public function testClickingInsideClosesIt(): void
    {
        $this->assertFalse(
            (bool) $this->zoom(
                'link().click();
                 dialog().querySelector(".image-zoom__image").click();

                 return dialog().open;'
            ),
            'Clicking inside the dialog leaves it open, and nothing else can close it.'
        );
    }

    // Cmd, Ctrl, Maj and Alt are how a new tab or a new window is asked for, and a lightbox opened instead answers a question nobody put
    public function testAModifiedClickIsLeftToTheBrowser(): void
    {
        $answered = $this->zoom(
            'let prevented = false;
             let opened = false;
             link().addEventListener("click", (event) => { prevented = event.defaultPrevented; opened = dialog().open; });
             // Registered last, so it reads nothing and only keeps the browser from leaving the page for the file, which would hang every scenario running after this one
             link().addEventListener("click", (event) => event.preventDefault());
             link().dispatchEvent(new MouseEvent("click", { bubbles: true, cancelable: true, ctrlKey: true }));

             return { prevented, open: opened };'
        );

        $this->assertFalse($answered['prevented'], 'A Ctrl+click is prevented, so the file never opens in the new tab it was asked for.');
        $this->assertFalse($answered['open'], 'A Ctrl+click opens the lightbox over the page instead of leaving the link alone.');
    }

    // Placed inside a <p>, the component loses its dialog to the paragraph's implicit close: the link must then do what it already promises rather than die on a target that is not there
    public function testAHoistedDialogLeavesTheLinkWorking(): void
    {
        $answered = $this->zoom(
            'let prevented = false;
             link().addEventListener("click", (event) => { prevented = event.defaultPrevented; });
             // Registered last, so it reads what the controller left and still keeps the browser from leaving the page for the file, which would hang the scenario
             link().addEventListener("click", (event) => event.preventDefault());
             link().click();

             return { prevented, open: dialog().open };',
            self::MARKUP_HOISTED
        );

        $this->assertFalse($answered['prevented'], 'The click is prevented although the dialog sits outside the controller element, which leaves the visitor with a link that does nothing at all.');
        $this->assertFalse($answered['open'], 'A dialog outside the controller element is opened, so the target was found where it cannot be.');
    }

    private function zoom(string $probe, string $markup = self::MARKUP): mixed
    {
        return $this->observe(
            $markup,
            ['imageZoom' => 'image-zoom'],
            'const link = () => root.querySelector("a.image-zoom__link");
             const dialog = () => root.querySelector("[data-imageZoom-target=dialog]");
             const image = () => root.querySelector("[data-imageZoom-target=image]");
             ' . $probe
        );
    }
}
