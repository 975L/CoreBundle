<?= "<?php\n" ?>
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace <?= $namespace ?>;

use <?= $llm_client_full_name ?>;
use c975L\ConfigBundle\Controller\Management\ConfigCrudController;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// Feeds this backend's status into the overridden ai_assistant.html.twig's "extra_backends" block
class <?= $class_name ?> extends AbstractExtension
{
    // Every config slug the setup guide links to individually
    private const LINKED_SLUGS = [
        'donovan-qa-llm-enabled',
        'donovan-qa-llm-provider',
        'donovan-qa-llm-api-key',
        'donovan-qa-llm-model',
        'donovan-qa-llm-base-uri',
        'donovan-qa-authorized-tokens',
    ];

    public function __construct(
        private readonly <?= $llm_client_short_name ?> $llmClient,
        private readonly ConfigServiceInterface $configService,
        private readonly ConfigRepository $configRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('donovan_qa_enabled', [$this->llmClient, 'isEnabled']),
            new TwigFunction('donovan_qa_missing_slugs', [$this, 'missingSlugs']),
            new TwigFunction('donovan_qa_config_links', [$this, 'configLinks']),
            new TwigFunction('donovan_qa_authorized_tokens', [$this, 'authorizedTokens']),
        ];
    }

    // Mirrors isEnabled() exactly, so this guide never disagrees with what actually gates the feature
    public function missingSlugs(): array
    {
        $blockingSlugs = self::LINKED_SLUGS;
        if ('euria' !== $this->configService->get('donovan-qa-llm-provider')) {
            $blockingSlugs = array_diff($blockingSlugs, ['donovan-qa-llm-base-uri', 'donovan-qa-llm-model']);
        }

        return array_values(array_filter(
            $blockingSlugs,
            fn (string $slug): bool => empty($this->configService->get($slug)),
        ));
    }

    // {slug: edit url}; a slug not yet loaded falls back to the plain Config list
    public function configLinks(): array
    {
        $links = [];
        foreach (self::LINKED_SLUGS as $slug) {
            $config = $this->configRepository->findOneBy(['slug' => $slug]);
            $urlGenerator = $this->adminUrlGenerator->unsetAll()->setController(ConfigCrudController::class);
            $links[$slug] = $config
                ? $urlGenerator->setAction(Action::EDIT)->setEntityId($config->getId())->generateUrl()
                : $urlGenerator->setAction(Action::INDEX)->generateUrl();
        }

        return $links;
    }

    // {site-key: token}; ConfigService already returns it decoded and decrypted
    public function authorizedTokens(): array
    {
        $tokens = $this->configService->get('donovan-qa-authorized-tokens');

        return \is_array($tokens) ? $tokens : [];
    }
}
