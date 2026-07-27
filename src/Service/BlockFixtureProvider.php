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

// Sample data for UiBundle's own built-in block kinds, shown in a block showcase (see BlockFixtureRegistry).
class BlockFixtureProvider implements BlockFixtureProviderInterface
{
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
                'Primaire' => ['label' => 'Primaire', 'url' => 'https://975l.com', 'type' => 'primary', 'target' => '', 'icon' => '', 'download' => false, 'inline' => false],
                'Secondaire' => ['label' => 'Secondaire', 'url' => 'https://975l.com', 'type' => 'secondary', 'target' => '', 'icon' => '', 'download' => false, 'inline' => false],
                'Succès' => ['label' => 'Succès', 'url' => 'https://975l.com', 'type' => 'success', 'target' => '', 'icon' => '', 'download' => false, 'inline' => false],
                'Danger' => ['label' => 'Danger', 'url' => 'https://975l.com', 'type' => 'danger', 'target' => '', 'icon' => '', 'download' => false, 'inline' => false],
                'Lien' => ['label' => 'Lien', 'url' => 'https://975l.com', 'type' => 'link', 'target' => '', 'icon' => '', 'download' => false, 'inline' => false],
            ],
            'card' => [
                '' => [
                    'id' => '',
                    'title' => 'Titre de la carte',
                    'level' => 'h3',
                    'content' => '<p>Description courte de la carte.</p>',
                    'url' => 'https://975l.com',
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
            // Unlike most fixtures, this renders a real sub-request looking up a Form named "contact" in DB (see FormController::fragment()) - throws if it doesn't exist, acceptable here since the block showcase only ever runs on 975l.com, which seeds it via "c975l:site:pages:import-defaults"
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
            'rich_snippet' => [
                '' => [
                    'itemscope' => 'https://schema.org/LocalBusiness',
                    'name' => 'Mon Entreprise',
                    'description' => '<p>Une entreprise fictive utilisée comme exemple.</p>',
                    'telephone' => '+33 1 23 45 67 89',
                    'image' => '',
                    'openingHours' => 'Mo-Fr 09:00-18:00',
                    'priceRange' => '€€',
                    'addressStreetAddress' => '1 rue de l\'Exemple',
                    'addressPostalCode' => '75000',
                    'addressAddressLocality' => 'Paris',
                    'addressAddressCountryCode' => 'FR',
                    'addressAddressCountryName' => 'France',
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
                    // Not PLACEHOLDER_VIDEO directly - a raw video file navigated to in an <iframe> plays with sound via the browser's own native player; this wraps it in a muted <video>. Leading "/" for the same reason as 'video' above - see its comment.
                    'src' => '/' . BlockFixtureMediaAttacher::PLACEHOLDER_VIDEO_EMBED,
                    'title' => 'Product demo',
                    'description' => 'A short walkthrough of the main features.',
                    'width' => '560',
                    'height' => '315',
                    'class' => [],
                ],
            ],
            'hero' => [
                '' => [
                    'badge' => 'Agence web indépendante · depuis 2018',
                    'title' => 'Votre site web, fait main et <em>sur mesure.</em>',
                    'subtitle' => 'On ne pose pas de template. On code proprement, avec notre propre CMS et Symfony, un site pensé pour vous.',
                    'primaryLabel' => 'Parlons de votre projet',
                    'primaryUrl' => 'https://975l.com/contact',
                    'secondaryLabel' => 'Voir nos réalisations',
                    'secondaryUrl' => 'https://975l.com/sites',
                    'statValue' => '12+',
                    'statLabel' => 'sites en ligne, et le vôtre bientôt',
                ],
            ],
            'feature_bar' => [
                '' => [
                    'items' => [
                        ['title' => 'Code propre', 'text' => 'maintenable dans le temps'],
                        ['title' => 'Symfony', 'text' => 'socle robuste & éprouvé'],
                        ['title' => 'Notre CMS', 'text' => 'édition simple, à vous'],
                        ['title' => 'Suisse', 'text' => 'hébergé chez Infomaniak'],
                        ['title' => 'Multilingue', 'text' => "prêt pour l'international"],
                    ],
                ],
            ],
            'section_features' => [
                '' => [
                    'eyebrow' => 'Ce que nous faisons',
                    'title' => "Des services taillés autour de votre projet, pas l'inverse.",
                    'cards' => [
                        ['icon' => 'bundles/c975lui/icons/pen-ruler.svg', 'title' => 'Sites sur mesure', 'text' => '<p>Chaque site est conçu et développé pour vos besoins réels.</p>'],
                        ['icon' => 'bundles/c975lui/icons/layer-group.svg', 'title' => 'Notre CMS maison', 'text' => '<p>Vous gérez votre contenu vous-même, simplement.</p>'],
                        ['icon' => 'bundles/c975lui/icons/code.svg', 'title' => 'Développement Symfony', 'text' => '<p>Un socle technique solide et durable.</p>'],
                    ],
                ],
            ],
            // No 'flex_columns'/'section_cards' entry here on purpose: unlike every other kind, their
            // "slots" are a real Block relation, not part of this plain data array - showcasing them needs
            // the consuming app's own fixture-to-Block builder (e.g. 975l.com's
            // BlockShowcaseCollectionSourceProvider) to also call Block::addSlot(), which it doesn't yet.
            'expertise_banner' => [
                '' => [
                    'eyebrow' => 'Notre expertise',
                    'title' => 'Une technique que nous maîtrisons de bout en bout.',
                    'text' => "<p>Pas de sous-traitance, pas de boîte noire. Nous développons, hébergeons et faisons évoluer votre site.</p>",
                    'tags' => ['Symfony', 'PHP', 'CMS 975L', 'Multilingue', 'Infomaniak · CH', 'Open-source'],
                ],
            ],
            'process_steps' => [
                '' => [
                    'eyebrow' => 'Comment ça se passe',
                    'title' => "Une méthode claire, du premier échange à la mise en ligne.",
                    'steps' => [
                        ['title' => "On s'écoute", 'text' => '<p>On étudie votre problématique ensemble.</p>'],
                        ['title' => 'On conçoit', 'text' => '<p>Structure, design et contenu, validés avec vous.</p>'],
                        ['title' => 'On développe', 'text' => "<p>Code propre, testé, et votre CMS prêt à l'emploi.</p>"],
                        ['title' => 'On accompagne', 'text' => '<p>Mise en ligne, hébergement et suivi dans la durée.</p>'],
                    ],
                ],
            ],
            'portfolio_grid' => [
                '' => [
                    'eyebrow' => 'Réalisations',
                    'title' => 'Des projets en ligne, bien réels.',
                    'linkLabel' => 'Tout voir',
                    'linkUrl' => 'https://975l.com/sites',
                ],
            ],
            'cta_band' => [
                '' => [
                    'title' => 'Racontez-nous votre projet.',
                    'text' => '<p>On étudie votre problématique et on voit ensemble comment y répondre au mieux.</p>',
                    'ctaLabel' => 'Contactez-nous',
                    'ctaUrl' => 'https://975l.com/contact',
                ],
            ],
        ];
    }
}
