<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\DependencyInjection\Compiler;

use c975L\UiBundle\Registry\BlockRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BlockRegistryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(BlockRegistry::class)) {
            return;
        }

        $registry = $container->getDefinition(BlockRegistry::class);

        // Gets all services tagged with "ui.block" and registers them in the BlockRegistry
        foreach ($container->findTaggedServiceIds('ui.block') as $id => $tags) {
            foreach ($tags as $tag) {
                $this->validateTag($tag, $id);

                $mediaTypes = [];
                if (!empty($tag['media_types'])) {
                    $mediaTypes = array_map('trim', explode(',', (string) $tag['media_types']));
                }

                $contexts = [];
                if (!empty($tag['contexts'])) {
                    $contexts = array_map('trim', explode(',', (string) $tag['contexts']));
                }

                $registry->addMethodCall('register', [
                    $tag['kind'],
                    $tag['label'],
                    $tag['form'],
                    $tag['template'],
                    $tag['category'] ?? 'label.category_general',
                    $mediaTypes,
                    $tag['translation_domain'] ?? 'ui',
                    $tag['description'] ?? '',
                    !isset($tag['pickable']) || filter_var($tag['pickable'], FILTER_VALIDATE_BOOLEAN),
                    (int) ($tag['priority'] ?? 0),
                    !isset($tag['cacheable']) || filter_var($tag['cacheable'], FILTER_VALIDATE_BOOLEAN),
                    $contexts,
                    isset($tag['media_required']) && filter_var($tag['media_required'], FILTER_VALIDATE_BOOLEAN),
                    isset($tag['media_multi_upload']) && filter_var($tag['media_multi_upload'], FILTER_VALIDATE_BOOLEAN),
                    $this->bundleFromTemplate($tag['template']),
                    isset($tag['container']) && filter_var($tag['container'], FILTER_VALIDATE_BOOLEAN),
                    $tag['slot_context'] ?? BlockRegistry::SLOT_CONTEXT,
                    $tag['media_help'] ?? '',
                ]);
            }
        }
    }

    // Every c975L bundle registers its block templates under its own "@c975LXxx/..." Twig namespace (see each bundle's src/c975LXxxBundle.php) - reused here instead of adding a new tag attribute every bundle would have to fill in, so a bundle gaining its first block kind needs zero extra wiring beyond the existing "ui.block" tag it already had to declare
    private function bundleFromTemplate(string $template): string
    {
        return 1 === preg_match('/^@c975L([A-Za-z0-9]+)\//', $template, $matches) ? $matches[1] : '';
    }

    private function validateTag(array $tag, string $serviceId): void
    {
        foreach (['kind', 'label', 'form', 'template'] as $required) {
            if (empty($tag[$required])) {
                throw new \InvalidArgumentException(sprintf('The tag "ui.block" of the service "%s" is incomplete. Missing attribute: "%s".', $serviceId, $required));
            }
        }
    }
}
