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
 * Exposes the app's own @font-face names so a "font" kind config renders as a <select>, not free text
 */
interface FontProviderInterface
{
    /**
     * @return string[] font family names, as used in CSS
     */
    public function getFonts(): array;
}
