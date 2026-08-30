<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

// Generates a self-hosted backend for the dashboard assistant; configs.json entries are printed as a snippet, never guess-merged
class MakeDonovanQaCommand extends AbstractMaker
{
    private const string SKELETON_DIR = __DIR__ . '/../Resources/skeleton/donovan_qa';

    // Set by interact(), read by generate() - both run within the same command invocation
    private bool $withSemanticCache = false;

    public static function getCommandName(): string
    {
        return 'c975l:ui:donovan-qa:create';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates the skeleton of a self-hosted Donovan (Q&A) backend (controller, LLM client, context builder, setup guide)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        // No argument needed: every generated class and slug is fixed, nothing varies per app
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        // Opt-in, not a default: it pulls in a new entity, migration and embedding-model dependency
        $this->withSemanticCache = $io->confirm(
            'Add a semantic cache (question embeddings, recognizing a rephrasing of an already-answered '
                . 'question) on top of the exact-hash cache? Needs MariaDB 11.7+/MySQL 9+ and an '
                . 'embedding-capable model separate from your chat one.',
            false,
        );
    }

    // The generated files' own text, written out as they will be read: its length is that of what it writes
    // #lizard forgives
    /** @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $llmClientClass = $generator->createClassNameDetails('DonovanQaLlm', 'Service\\', 'Client');
        $contextBuilderClass = $generator->createClassNameDetails('DonovanQaContext', 'Service\\', 'Builder');
        $controllerClass = $generator->createClassNameDetails('DonovanQa', 'Controller\\Api\\', 'Controller');
        $twigExtensionClass = $generator->createClassNameDetails('DonovanQa', 'Twig\\', 'Extension');
        $testClass = $generator->createClassNameDetails('DonovanQaLlm', 'Tests\\Service\\', 'ClientTest');

        $generator->generateClass($llmClientClass->getFullName(), self::SKELETON_DIR . '/LlmClient.tpl.php');

        $generator->generateClass($contextBuilderClass->getFullName(), self::SKELETON_DIR . '/ContextBuilder.tpl.php');

        if ($this->withSemanticCache) {
            $entityClass = $generator->createClassNameDetails('DonovanQaAnswer', 'Entity\\');
            $repositoryClass = $generator->createClassNameDetails('DonovanQaAnswer', 'Repository\\', 'Repository');
            $embeddingClientClass = $generator->createClassNameDetails('DonovanQaEmbedding', 'Service\\', 'Client');
            $serviceClass = $generator->createClassNameDetails('DonovanQa', 'Service\\', 'Service');
            $embeddingClientTestClass = $generator->createClassNameDetails('DonovanQaEmbedding', 'Tests\\Service\\', 'ClientTest');
            $serviceTestClass = $generator->createClassNameDetails('DonovanQa', 'Tests\\Service\\', 'ServiceTest');

            $generator->generateClass($entityClass->getFullName(), self::SKELETON_DIR . '/Answer.tpl.php', [
                'repository_full_name' => $repositoryClass->getFullName(),
                'repository_short_name' => $repositoryClass->getShortName(),
            ]);

            $generator->generateClass($repositoryClass->getFullName(), self::SKELETON_DIR . '/AnswerRepository.tpl.php', [
                'entity_full_name' => $entityClass->getFullName(),
                'entity_short_name' => $entityClass->getShortName(),
            ]);

            $generator->generateClass($embeddingClientClass->getFullName(), self::SKELETON_DIR . '/EmbeddingClient.tpl.php');

            $generator->generateClass($embeddingClientTestClass->getFullName(), self::SKELETON_DIR . '/EmbeddingClientTest.tpl.php', [
                'embedding_client_full_name' => $embeddingClientClass->getFullName(),
                'embedding_client_short_name' => $embeddingClientClass->getShortName(),
            ]);

            $serviceTemplateVars = [
                'entity_full_name' => $entityClass->getFullName(),
                'entity_short_name' => $entityClass->getShortName(),
                'repository_full_name' => $repositoryClass->getFullName(),
                'repository_short_name' => $repositoryClass->getShortName(),
                'context_builder_full_name' => $contextBuilderClass->getFullName(),
                'context_builder_short_name' => $contextBuilderClass->getShortName(),
                'llm_client_full_name' => $llmClientClass->getFullName(),
                'llm_client_short_name' => $llmClientClass->getShortName(),
                'embedding_client_full_name' => $embeddingClientClass->getFullName(),
                'embedding_client_short_name' => $embeddingClientClass->getShortName(),
            ];

            $generator->generateClass($serviceClass->getFullName(), self::SKELETON_DIR . '/Service.tpl.php', $serviceTemplateVars);

            $generator->generateClass($serviceTestClass->getFullName(), self::SKELETON_DIR . '/ServiceTest.tpl.php', [
                ...$serviceTemplateVars,
                'service_full_name' => $serviceClass->getFullName(),
            ]);

            $generator->generateClass($controllerClass->getFullName(), self::SKELETON_DIR . '/ControllerWithCache.tpl.php', [
                'service_full_name' => $serviceClass->getFullName(),
                'service_short_name' => $serviceClass->getShortName(),
            ]);
        } else {
            $generator->generateClass($controllerClass->getFullName(), self::SKELETON_DIR . '/Controller.tpl.php', [
                'llm_client_full_name' => $llmClientClass->getFullName(),
                'llm_client_short_name' => $llmClientClass->getShortName(),
                'context_builder_full_name' => $contextBuilderClass->getFullName(),
                'context_builder_short_name' => $contextBuilderClass->getShortName(),
            ]);
        }

        $generator->generateClass($twigExtensionClass->getFullName(), self::SKELETON_DIR . '/TwigExtension.tpl.php', [
            'llm_client_full_name' => $llmClientClass->getFullName(),
            'llm_client_short_name' => $llmClientClass->getShortName(),
        ]);

        $generator->generateClass($testClass->getFullName(), self::SKELETON_DIR . '/LlmClientTest.tpl.php', [
            'llm_client_full_name' => $llmClientClass->getFullName(),
            'llm_client_short_name' => $llmClientClass->getShortName(),
        ]);

        // Fixed physical path, how Symfony resolves the bundle override once it exists
        $generator->generateTemplate(
            'bundles/c975LUiBundle/management/ai_assistant.html.twig',
            self::SKELETON_DIR . '/ai_assistant_override.tpl.php',
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->writeln([
            'Still to do:',
            '',
            '1. Add these 6 entries to your app\'s config/configs.json (create it, plus a small command',
            '   loading it via ConfigServiceInterface::loadDefaultConfig() at boot/deploy time, if you don\'t',
            '   have one yet - see 975l.com\'s own AppConfigLoadCommand for a 15-line model):',
            '',
            '    [',
            '        {',
            '            "label": "label.donovan_qa_llm_enabled",',
            '            "slug": "donovan-qa-llm-enabled",',
            '            "sensitive": false,',
            '            "restricted": false,',
            '            "value": "false",',
            '            "kind": "bool",',
            '            "group": "ai",',
            '            "severity": null,',
            '            "description": "description.donovan_qa_llm_enabled"',
            '        },',
            '        {',
            '            "label": "label.donovan_qa_llm_provider",',
            '            "slug": "donovan-qa-llm-provider",',
            '            "sensitive": false,',
            '            "restricted": false,',
            '            "value": "anthropic",',
            '            "kind": "text",',
            '            "group": "ai",',
            '            "severity": null,',
            '            "description": "description.donovan_qa_llm_provider"',
            '        },',
            '        {',
            '            "label": "label.donovan_qa_llm_api_key",',
            '            "slug": "donovan-qa-llm-api-key",',
            '            "sensitive": true,',
            '            "restricted": false,',
            '            "value": null,',
            '            "kind": "text",',
            '            "group": "ai",',
            '            "severity": null,',
            '            "description": "description.donovan_qa_llm_api_key"',
            '        },',
            '        {',
            '            "label": "label.donovan_qa_llm_model",',
            '            "slug": "donovan-qa-llm-model",',
            '            "sensitive": false,',
            '            "restricted": false,',
            '            "value": null,',
            '            "kind": "text",',
            '            "group": "ai",',
            '            "severity": null,',
            '            "description": "description.donovan_qa_llm_model"',
            '        },',
            '        {',
            '            "label": "label.donovan_qa_llm_base_uri",',
            '            "slug": "donovan-qa-llm-base-uri",',
            '            "sensitive": false,',
            '            "restricted": false,',
            '            "value": null,',
            '            "kind": "text",',
            '            "group": "ai",',
            '            "severity": null,',
            '            "description": "description.donovan_qa_llm_base_uri"',
            '        },',
            '        {',
            '            "label": "label.donovan_qa_authorized_tokens",',
            '            "slug": "donovan-qa-authorized-tokens",',
            '            "sensitive": true,',
            '            "restricted": false,',
            '            "value": null,',
            '            "kind": "json",',
            '            "group": "ai",',
            '            "severity": null,',
            '            "description": "description.donovan_qa_authorized_tokens"',
            '        }',
            '    ]',
            '',
            '2. Point every consuming site\'s "ui-ai-assistant-dashboard-endpoint" at this app\'s',
            '   /api/donovan-qa/ask, and its "ui-ai-assistant-dashboard-token" at its own token above.',
            '',
        ]);

        if ($this->withSemanticCache) {
            $io->writeln([
                'Semantic cache also needs:',
                '',
                '3. These 3 additional config/configs.json entries:',
                '',
                '    {',
                '        "label": "label.donovan_qa_embedding_model",',
                '        "slug": "donovan-qa-embedding-model",',
                '        "sensitive": false,',
                '        "restricted": false,',
                '        "value": null,',
                '        "kind": "text",',
                '        "group": "ai",',
                '        "severity": null,',
                '        "description": "description.donovan_qa_embedding_model"',
                '    },',
                '    {',
                '        "label": "label.donovan_qa_semantic_cache_enabled",',
                '        "slug": "donovan-qa-semantic-cache-enabled",',
                '        "sensitive": false,',
                '        "restricted": false,',
                '        "value": "false",',
                '        "kind": "bool",',
                '        "group": "ai",',
                '        "severity": null,',
                '        "description": "description.donovan_qa_semantic_cache_enabled"',
                '    },',
                '    {',
                '        "label": "label.donovan_qa_semantic_cache_threshold",',
                '        "slug": "donovan-qa-semantic-cache-threshold",',
                '        "sensitive": false,',
                '        "restricted": false,',
                '        "value": "0.90",',
                '        "kind": "text",',
                '        "group": "ai",',
                '        "severity": null,',
                '        "description": "description.donovan_qa_semantic_cache_threshold"',
                '    }',
                '',
                '4. In config/packages/doctrine.yaml, under doctrine.dbal:',
                '',
                '    types:',
                '        vector: c975L\\UiBundle\\Doctrine\\VectorType',
                '    mapping_types:',
                '        vector: vector',
                '',
                '5. php bin/console make:migration - requires MariaDB 11.7+/MySQL 9+ for the native VECTOR',
                '   column type the generated Answer entity uses. On an older version, see this bundle\'s',
                '   Readme "AI Assistant" > "Self-hosting your own backend" for the JSON+cosine-similarity',
                '   fallback shape instead (a different Answer/AnswerRepository, not generated here).',
                '   Review the generated migration, then run it.',
                '',
                '6. Set "donovan-qa-embedding-model" to an embedding-capable model id (e.g.',
                '   Qwen/Qwen3-Embedding-8B on Euria/Infomaniak) - a chat model is never usable as one -',
                '   and flip "donovan-qa-semantic-cache-enabled" to true once you\'ve verified it responds.',
                '',
            ]);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
