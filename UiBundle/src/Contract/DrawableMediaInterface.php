<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

/**
 * What a component reads off a media to draw it - the counterpart, for the drawing, of the Vich* contracts above, which say how a file is stored, named and resized.
 *
 * It is for an application handing its own entity to a component rather than this bundle's Media: `<twig:c975LUi:Slider:Slider media="{{ book.images }}"/>` reads every getter below, `ImageCompare` reads the alternative text and the two sizes, `Text:Section` the alternative text. Without it those reads are guesswork against a template, and what an entity is missing is found out by a visitor opening the page rather than by PHP refusing to load the class.
 *
 * **Every value is optional.** A media answering null, an empty array or false to all of them still draws: the alternative text falls back on what the caller named, the sizes are left to the stylesheet, the caption and the classes are simply not written. Implementing this says the entity can be drawn, not that it carries anything in particular - so an entity with nothing but a file to show declares the getters and returns null from them.
 */
interface DrawableMediaInterface
{
    // What the image is described as to whoever cannot see it; a component naming a fallback of its own uses that one instead when this is null
    public function getAlt(): ?string;

    // The file's own type, which is what tells an image from a video on one list of media - a null one is drawn as an image
    public function getMimeType(): ?string;

    // The caption drawn under the media, where the alternative text above is never shown
    public function getLabel(): ?string;

    // The size written into the tag's own attributes, so the page reserves the room before the file arrives; a null one leaves it to the stylesheet
    public function getWidth(): ?string;

    public function getHeight(): ?string;

    /**
     * The classes the site's own stylesheet styles this media with, empty for a media styled by nothing but the component.
     *
     * @return string[]
     */
    public function getCssClasses(): array;

    // Whether the media sits above the fold, which is what has it loaded eagerly and fetched first rather than lazily like every other
    public function isAbove(): bool;

    // Who the media is to be credited to, drawn over it - a license asking for attribution is honoured by this getter and nothing else
    public function getCredits(): ?string;

    // Whether the media carries an "all rights reserved" mention, drawn over it the same way. Nullable, an entity leaving the checkbox untouched answering null rather than false
    public function isRightsReserved(): ?bool;
}
