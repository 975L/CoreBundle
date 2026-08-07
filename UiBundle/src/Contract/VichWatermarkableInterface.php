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
 * Opts an uploaded image into a logo stamped in one of its corners, on every derivative the resize pipeline writes (see VichImageResizeListener::processImage).
 * Which of the two site-wide logos is used gets decided per image, on the average luminance of the very corner it is about to occupy (see ImageWatermarker::prepare): a dark signature disappears into a night sky and a white one into a snowfield, and a gallery holds both shots.
 * The logos themselves are site graphics, uploaded once for the whole site (see Media::ROLE_WATERMARK_ON_LIGHT) - an entity only says whether it wants one and where.
 */
interface VichWatermarkableInterface extends VichImageResizableInterface
{
    public const POSITION_TOP_LEFT = 'top-left';
    public const POSITION_TOP_RIGHT = 'top-right';
    public const POSITION_BOTTOM_RIGHT = 'bottom-right';
    public const POSITION_BOTTOM_LEFT = 'bottom-left';

    public const POSITIONS = [
        self::POSITION_TOP_LEFT,
        self::POSITION_TOP_RIGHT,
        self::POSITION_BOTTOM_RIGHT,
        self::POSITION_BOTTOM_LEFT,
    ];

    /**
     * @return bool false leaves every derivative untouched, whatever the site has uploaded as a logo - a screenshot or a press photo is nobody's to sign
     */
    public function wantsWatermark(): bool;

    /**
     * @return string|null one of POSITIONS, or null to take the corner set site-wide
     */
    public function getWatermarkPosition(): ?string;
}
