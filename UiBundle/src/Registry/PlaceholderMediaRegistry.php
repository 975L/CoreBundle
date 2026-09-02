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
        $declared = array_filter($provider->getPlaceholderMedia());

        // "keyed_images" is merged one named thing at a time rather than wholesale: a provider declaring a single product's photographs would otherwise take away every other provider's - filtered again inside, a named thing left empty being no more an instruction to blank one out than a top-level key left empty is
        $declaredKeyed = array_filter($declared['keyed_images'] ?? []);
        $keyed = array_merge($this->media['keyed_images'] ?? [], $declaredKeyed);
        unset($declared['keyed_images']);

        $this->media = array_merge($this->media, $declared);

        if ([] !== $keyed) {
            $this->media['keyed_images'] = $keyed;
        }
    }

    /**
     * @return list<string>
     */
    public function getImages(): array
    {
        return $this->media['images'] ?? [];
    }

    /**
     * The pictures of one named thing, in the order they were declared - empty for anything the site has none of,
     * which is what every site starts as and what each caller falls back from on its own.
     *
     * @return list<string>
     */
    public function getImagesFor(string $key): array
    {
        return $this->media['keyed_images'][$key] ?? [];
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

    // The font file the demo dataset imports - null on a site declaring none, which then simply seeds no font
    public function getFont(): ?string
    {
        return $this->media['font'] ?? null;
    }
}
