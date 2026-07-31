<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\PlaceholderMediaProviderInterface;

// Collects whatever placeholder media the app declares for its own block showcase - empty as long as nothing is declared, BlockFixtureMediaAttacher then attaching no media at all, the bundle shipping none of those files itself
class PlaceholderMediaRegistry
{
    /**
     * @var array<string, mixed>
     */
    private array $media = [];

    // Called once per provider by PlaceholderMediaProviderPass - a later provider overrides an earlier one key by key, an empty value never overriding anything (see the interface: a partial declaration is legitimate)
    public function addProvider(PlaceholderMediaProviderInterface $provider): void
    {
        $this->media = array_merge($this->media, array_filter($provider->getPlaceholderMedia()));
    }

    /**
     * @return list<string>
     */
    public function getImages(): array
    {
        return $this->media['images'] ?? [];
    }

    public function getVideo(): ?string
    {
        return $this->media['video'] ?? null;
    }

    public function getVideoEmbed(): ?string
    {
        return $this->media['video_embed'] ?? null;
    }

    public function getAudio(): ?string
    {
        return $this->media['audio'] ?? null;
    }

    public function getDocument(): ?string
    {
        return $this->media['document'] ?? null;
    }
}
