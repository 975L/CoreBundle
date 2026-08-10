<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\BlockFixtureProviderInterface;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;

// Sample data for UiBundle's own built-in block kinds, shown in a block showcase (see BlockFixtureRegistry).
class BlockFixtureProvider implements BlockFixtureProviderInterface
{
    // Optional for the same reason as BlockFixtureMediaAttacher's own - only "video_iframe" needs it, every other fixture here carrying no media path at all
    public function __construct(private readonly ?PlaceholderMediaRegistry $placeholderMedia = null)
    {
    }

    public function getFixtures(): array
    {
        return [
            // Every choice of AlertType::$choices, so an editor can compare all four at a glance
            'alert' => [
                'Info' => [
                    'type' => 'info',
                    'content' => '<p>Ceci est un exemple de message d\'information.</p>',
                ],
                'Succès' => [
                    'type' => 'success',
                    'content' => '<p>Ceci est un exemple de message de succès.</p>',
                ],
                'Avertissement' => [
                    'type' => 'warning',
                    'content' => '<p>Ceci est un exemple de message d\'avertissement.</p>',
                ],
                'Danger' => [
                    'type' => 'danger',
                    'content' => '<p>Ceci est un exemple de message de danger.</p>',
                ],
            ],
            // Its file is auto-attached by BlockFixtureMediaAttacher (any "audio/*" mediaType), format included, so the fixture only needs the player's own display fields
            'audio' => [
                '' => [
                    'title' => 'Ambient loop',
                    'description' => 'A short instrumental excerpt.',
                    'class' => [],
                ],
            ],
            'article' => [
                '' => [
                    'title' => 'Titre de l\'article',
                    'hook' => '<p>Chapô d\'accroche de l\'article.</p>',
                    'content' => '<p>Contenu de l\'article, avec un peu de texte pour illustrer le rendu.</p>',
                    'slug' => 'titre-de-larticle',
                ],
            ],
            'banner_title' => [
                '' => [
                    'title' => 'Titre de la bannière',
                    'level' => 'h1',
                    'maxHeight' => 400,
                ],
            ],
            // Every choice of ButtonType::$choices, so an editor can compare all five styles at a glance
            'button' => [
                'Primaire' => ['label' => 'Primaire', 'url' => 'https://example.com', 'type' => 'primary', 'target' => '', 'icon' => '', 'download' => false, 'inline' => false],
                'Secondaire' => ['label' => 'Secondaire', 'url' => 'https://example.com', 'type' => 'secondary', 'target' => '', 'icon' => '', 'download' => false, 'inline' => false],
                'Succès' => ['label' => 'Succès', 'url' => 'https://example.com', 'type' => 'success', 'target' => '', 'icon' => '', 'download' => false, 'inline' => false],
                'Danger' => ['label' => 'Danger', 'url' => 'https://example.com', 'type' => 'danger', 'target' => '', 'icon' => '', 'download' => false, 'inline' => false],
                'Lien' => ['label' => 'Lien', 'url' => 'https://example.com', 'type' => 'link', 'target' => '', 'icon' => '', 'download' => false, 'inline' => false],
            ],
            'card' => [
                '' => [
                    'id' => '',
                    'title' => 'Titre de la carte',
                    'level' => 'h3',
                    'content' => '<p>Description courte de la carte.</p>',
                    'url' => 'https://example.com',
                    'target' => '',
                    'buttonLabel' => 'Découvrir',
                    'class' => [],
                ],
            ],
            'document_download' => [
                '' => [
                    'label' => 'Mon CV',
                    'buttonLabel' => '',
                ],
            ],
            // Its two images - front face then back face - are auto-attached by BlockFixtureMediaAttacher (two, its kind declaring media_multi_upload), so the fixture only carries the text of each face
            'flip_card' => [
                '' => [
                    'id' => '',
                    'title' => 'Titre du recto',
                    'level' => 'h3',
                    'content' => '<p>Ce que la carte montre en premier, en une phrase.</p>',
                    'backTitle' => 'Titre du verso',
                    'backContent' => '<p>Ce que la carte révèle une fois retournée, en deux ou trois lignes.</p>',
                    // Not "free": the showcase renders one card on its own, where a shape held open shows what the field does
                    'ratio' => '3-2',
                    'class' => [],
                ],
            ],
            // Unlike most fixtures, this renders a real sub-request looking up a Form named "contact" in DB (see FormController::fragment()) - throws if it doesn't exist, acceptable here since the block showcase only ever runs on a site that has seeded its default pages with "c975l:site:pages:import-defaults", which creates that form
            'form' => [
                '' => [
                    'name' => 'contact',
                ],
            ],
            'image' => [
                '' => [],
            ],
            'image_compare' => [
                '' => [
                    'id' => 'image-compare-preview',
                    'startPosition' => 50,
                    'beforeLabel' => 'Avant',
                    'afterLabel' => 'Après',
                    'class' => [],
                ],
            ],
            'progress_bar' => [
                '' => [
                    'label' => 'Symfony',
                    'progressPercent' => 65,
                    'text' => true,
                ],
            ],
            // Two hour rows over the same days, the shape a business closing for lunch needs (see ContactHoursType)
            'contact_details' => [
                '' => [
                    'schemaType' => 'LocalBusiness',
                    'name' => 'Mon Entreprise',
                    'description' => '<p>Une entreprise fictive utilisée comme exemple.</p>',
                    'addressStreetAddress' => '1 rue de l\'Exemple',
                    'addressComplement' => 'Bâtiment B',
                    'addressPostalCode' => '74000',
                    'addressLocality' => 'Annecy',
                    'addressRegion' => 'Auvergne-Rhône-Alpes',
                    'addressCountryName' => 'France',
                    'addressCountryCode' => 'FR',
                    'telephone' => '+33 1 23 45 67 89',
                    'mobile' => '+33 6 12 34 56 78',
                    'email' => 'contact@example.com',
                    'url' => 'https://example.com',
                    'hours' => [
                        ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '09:00', 'closes' => '12:00'],
                        ['days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '14:00', 'closes' => '18:00'],
                        ['days' => ['Saturday'], 'opens' => '09:00', 'closes' => '12:00'],
                    ],
                    'priceRange' => '€€',
                    'mapUrl' => 'https://www.openstreetmap.org/',
                    'latitude' => '48.8566',
                    'longitude' => '2.3522',
                ],
            ],
            'slider' => [
                '' => [
                    'id' => 'gallery-slider-preview',
                    'duration' => 5000,
                    'ratio' => 'free',
                    'layout' => 'default',
                    'class' => [],
                ],
                'freeflow' => [
                    'id' => 'gallery-slider-freeflow-preview',
                    'duration' => 5000,
                    'ratio' => 'free',
                    'layout' => 'freeflow',
                    'class' => [],
                ],
            ],
            'text_hook' => [
                '' => [
                    'text' => '<div>Une phrase d\'accroche, plus grande et plus aérée que le texte qu\'elle introduit, sur une mesure plus courte.</div>',
                ],
            ],
            'text_readmore' => [
                '' => [
                    'id' => 'readmore-exemple',
                    'text' => '<p>Texte replié, cliquez pour en savoir plus...</p><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p><p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p><p>Curabitur pretium tincidunt lacus, ut interdum tellus elit sed risus. Maecenas eget condimentum velit, sit amet feugiat lectus. Class aptent taciti sociosqu ad litora torquent per conubia nostra, per inceptos himenaeos.</p><p>Praesent auctor purus luctus enim egestas, ac scelerisque ante pulvinar. Donec ut rhoncus ex. Suspendisse ac rhoncus nisl, eu tempor urna. Curabitur vel bibendum lorem. Morbi convallis convallis diam sit amet lacinia.</p><p>Aliquam in hendrerit urna. Pellentesque sit amet sapien fringilla, mattis ligula consectetur, ultrices mauris. Maecenas vitae mattis tellus. Nullam quis imperdiet augue. Vestibulum auctor ornare leo, non suscipit magna interdum eu.</p>',
                ],
            ],
            'text_section' => [
                '' => [
                    'eyebrow' => 'Surtitre de section',
                    'title' => 'Titre de section',
                    'slug' => 'titre-de-section',
                    'content' => '<p>Contenu de la section.</p>',
                ],
            ],
            // Its video file and cover image are auto-attached generically by BlockFixtureMediaAttacher (any "video/*" then "image/*" mediaType), so the fixture only needs the player's own options
            'video' => [
                '' => [
                    'options' => ['muted'],
                    'title' => 'Product demo',
                    'description' => 'A short walkthrough of the main features.',
                    'width' => '',
                    'height' => '',
                    'class' => [],
                ],
            ],
            // Its "src" is any URL rendered directly in an <iframe> (see Video/Iframe.html.twig) - not limited to a YouTube/Vimeo-style embed, so the same self-hosted placeholder as "video" works fine here too (browsers show their native player for a media file in an iframe)
            'video_iframe' => [
                '' => [
                    // Not the declared video file directly - a raw video navigated to in an <iframe> plays with sound via the browser's own native player; "video_embed" wraps it in a muted <video>. Leading "/" for the same reason as 'video' above - see its comment. Empty when the app declares none, the preview then showing the block's own consent placeholder with nothing behind it.
                    'src' => $this->videoEmbedSrc(),
                    'title' => 'Product demo',
                    'description' => 'A short walkthrough of the main features.',
                    'width' => '560',
                    'height' => '315',
                    'class' => [],
                ],
            ],
            'hero' => [
                '' => [
                    'badge' => 'Exemple de badge · texte court',
                    'title' => 'Un titre de hero, avec <em>un mot mis en avant.</em>',
                    'subtitle' => 'Le sous-titre du hero, deux lignes pour poser le sujet de la page et donner envie de lire la suite.',
                    'primaryLabel' => 'Bouton principal',
                    'primaryUrl' => 'https://example.com/contact',
                    'secondaryLabel' => 'Bouton secondaire',
                    'secondaryUrl' => 'https://example.com/realisations',
                    'statValue' => '00',
                    'statLabel' => 'le chiffre que ce hero met en avant',
                ],
                // A whole other look rather than one more media: an attached video fills the section by itself and drops everything the default variant lays out beside the text, so both are shown side by side in the gallery. The video is attached by BlockFixtureMediaAttacher, which reads this variant's name; "hasBackgroundImage" is left out on purpose, the video not needing it
                'video' => [
                    'badge' => 'Exemple de badge · texte court',
                    'title' => 'Un titre de hero sur <em>une vidéo de fond.</em>',
                    'subtitle' => 'La vidéo remplit toute la section, muette et en boucle, et le texte se lit par-dessus.',
                    'primaryLabel' => 'Bouton principal',
                    'primaryUrl' => 'https://example.com/contact',
                    'secondaryLabel' => 'Bouton secondaire',
                    'secondaryUrl' => 'https://example.com/realisations',
                    'statValue' => '00',
                    'statLabel' => 'le chiffre que ce hero met en avant',
                ],
            ],
            'feature_bar' => [
                '' => [
                    // Both optional on this kind, shown here because a band typed without them is the plainer look
                    'eyebrow' => 'Surtitre de la bande',
                    'title' => 'Le titre de la bande, sur une ligne.',
                    'items' => [
                        ['title' => 'Premier point', 'text' => 'une précision en une ligne'],
                        ['title' => 'Deuxième point', 'text' => 'une précision en une ligne'],
                        ['title' => 'Troisième point', 'text' => 'une précision en une ligne'],
                        ['title' => 'Quatrième point', 'text' => 'une précision en une ligne'],
                        ['title' => 'Cinquième point', 'text' => 'une précision en une ligne'],
                    ],
                ],
            ],
            'section_features' => [
                '' => [
                    'eyebrow' => 'Surtitre de la section',
                    'title' => 'Le titre de la section, sur une ou deux lignes.',
                    'cards' => [
                        ['icon' => 'bundles/c975lui/icons/pen-ruler.svg', 'title' => 'Première carte', 'text' => '<p>Deux lignes décrivant ce que cette carte présente.</p>'],
                        ['icon' => 'bundles/c975lui/icons/layer-group.svg', 'title' => 'Deuxième carte', 'text' => '<p>Deux lignes décrivant ce que cette carte présente.</p>'],
                        ['icon' => 'bundles/c975lui/icons/code.svg', 'title' => 'Troisième carte', 'text' => '<p>Deux lignes décrivant ce que cette carte présente.</p>'],
                    ],
                ],
            ],
            // No container kind here: their "slots" are a Block relation, not part of this plain data array
            'expertise_banner' => [
                '' => [
                    'eyebrow' => 'Surtitre du bandeau',
                    'title' => 'Le titre du bandeau, sur une ligne ou deux.',
                    'text' => '<p>Le paragraphe du bandeau : deux ou trois phrases qui développent le titre au-dessus.</p>',
                    'tags' => ['Première étiquette', 'Deuxième', 'Troisième', 'Quatrième', 'Cinquième', 'Sixième'],
                ],
            ],
            'process_steps' => [
                '' => [
                    'eyebrow' => 'Surtitre des étapes',
                    'title' => 'Le titre présentant la suite d\'étapes ci-dessous.',
                    'steps' => [
                        ['title' => 'Première étape', 'text' => '<p>Ce qui se passe à cette étape, en une phrase.</p>'],
                        ['title' => 'Deuxième étape', 'text' => '<p>Ce qui se passe à cette étape, en une phrase.</p>'],
                        ['title' => 'Troisième étape', 'text' => '<p>Ce qui se passe à cette étape, en une phrase.</p>'],
                        ['title' => 'Quatrième étape', 'text' => '<p>Ce qui se passe à cette étape, en une phrase.</p>'],
                    ],
                ],
            ],
            'portfolio_grid' => [
                '' => [
                    'eyebrow' => 'Surtitre de la grille',
                    'title' => 'Le titre de la grille de projets.',
                    'linkLabel' => 'Tout voir',
                    'linkUrl' => 'https://example.com/realisations',
                ],
            ],
            'cta_band' => [
                '' => [
                    'title' => 'Le titre du bandeau d\'appel à action.',
                    'text' => '<p>Une ou deux phrases qui donnent envie de cliquer sur le bouton à côté.</p>',
                    'ctaLabel' => 'Libellé du bouton',
                    'ctaUrl' => 'https://example.com/contact',
                ],
            ],
            'legal_model' => [
                '' => [
                    'model' => 'france/legal-notice',
                    'latestUpdate' => '2026-01-01',
                ],
            ],
        ];
    }

    // Leading "/" so the src is a site-root path whatever page the showcase is rendered on, the registry holding web paths without one
    private function videoEmbedSrc(): string
    {
        $embed = $this->placeholderMedia?->getVideoEmbed();

        return null !== $embed && '' !== $embed ? '/' . $embed : '';
    }
}
