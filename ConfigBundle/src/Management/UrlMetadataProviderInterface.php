<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Implement this to have your bundle's own urls listed in the "Descriptions d'urls" screen, ready to be described, without anyone having to type a path by hand - collected by UrlMetadataSynchronizer, run by the c975l:url-metadata:sync command. Only the urls no entity carries belong here: a book, a product, a photo, a page each says its own from its columns, and a row would never be read for them (see UrlMetadata).
// What is declared is which urls exist, never what they say: the paths are structure and live in the code, the sentences are content and live in the base
interface UrlMetadataProviderInterface
{
    /**
     * Paths, absolute from the site root and without the domain ("/animaux", "/caste/guerrier"). One entry per page and not per route: a route serving several listings has several things to say, and each of them gets its own row.
     * Return [] when there is nothing to declare - a bundle installed but not used yet, or one whose every url is carried by an entity.
     *
     * @return list<string>
     */
    public function getUrlMetadataPaths(): array;
}
