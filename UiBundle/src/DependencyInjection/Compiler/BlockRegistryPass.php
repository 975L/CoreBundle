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
                $registry->addMethodCall('register', $this->registrationArguments($tag));
            }
        }
    }

    // The tag read out into the arguments BlockRegistry::register() expects, in that very order - what the kind is, then how it behaves
    private function registrationArguments(array $tag): array
    {
        return [...$this->declaredKind($tag), ...$this->declaredBehaviour($tag)];
    }

    // What the kind is and how it is presented in the picker, each optional attribute falling back on what a bundle declaring nothing means
    private function declaredKind(array $tag): array
    {
        return [
            $tag['kind'],
            $tag['label'],
            $tag['form'],
            $tag['template'],
            $tag['category'] ?? 'label.category_general',
            $this->list($tag['media_types'] ?? null),
            $tag['translation_domain'] ?? 'ui',
            $tag['description'] ?? '',
        ];
    }

    // Where the kind may be picked, what it does with medias, whether it holds other blocks, and which of its own fields another language may cover
    private function declaredBehaviour(array $tag): array
    {
        return [
            $this->flag($tag, 'pickable', true),
            (int) ($tag['priority'] ?? 0),
            $this->flag($tag, 'cacheable', true),
            $this->list($tag['contexts'] ?? null),
            $this->flag($tag, 'media_required', false),
            $this->flag($tag, 'media_multi_upload', false),
            $this->bundleFromTemplate($tag['template']),
            $this->flag($tag, 'container', false),
            $tag['slot_context'] ?? BlockRegistry::SLOT_CONTEXT,
            $tag['media_help'] ?? '',
            $this->list($tag['translatable'] ?? null),
        ];
    }

    // A comma-separated attribute read as the list it stands for, an absent one standing for none
    private function list(?string $value): array
    {
        return empty($value) ? [] : array_map(trim(...), explode(',', $value));
    }

    // A boolean attribute, an absent one taking the value a bundle declaring nothing means
    private function flag(array $tag, string $name, bool $default): bool
    {
        return isset($tag[$name]) ? filter_var($tag[$name], FILTER_VALIDATE_BOOLEAN) : $default;
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
