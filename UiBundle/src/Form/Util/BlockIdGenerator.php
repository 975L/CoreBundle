<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Util;

// Shared by any block form type needing a unique, auto-generated HTML id (used for the section id/data-*-id attribute, not user-editable) - see SliderType/ImageCompareType's PRE_SET_DATA listeners
final class BlockIdGenerator
{
    public function __construct()
    {
    }

    public static function generate(string $prefix): string
    {
        return $prefix . '-' . bin2hex(random_bytes(4));
    }
}
