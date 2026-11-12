<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Ai\AnthropicProvider;
use c975L\UiBundle\Ai\EuriaProvider;
use c975L\UiBundle\Ai\OpenAiProvider;
use c975L\UiBundle\Contract\AiProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\Attribute\Required;

// Calls an LLM with the site's own key, for every feature billed to it: the rephrase and the site search differ by their config prefix and their prompts alone. The API is a connector (see AiProviderInterface), the one "<prefix>-provider" names. Nothing is defaulted in code: an empty entry keeps the feature off rather than calling an address or a model nobody chose
abstract class AbstractAiProviderClient
{
    use ConfigChosenProviderTrait;

    // The providers shipped, built on the client given, so a feature constructed by hand (a subclass passing four arguments, a test) calls them all the same; the container then hands every tagged one through setProviders()
    public function __construct(
        HttpClientInterface $httpClient,
        private readonly ConfigServiceInterface $configService,
        private readonly LoggerInterface $logger,
        private readonly AiUsageTracker $aiUsageTracker,
    ) {
        $this->setProviders([new AnthropicProvider($httpClient), new OpenAiProvider($httpClient), new EuriaProvider($httpClient)]);
    }

    // Every provider registered, a tagged one replacing a shipped one of the same slug
    /** @param iterable<AiProviderInterface> $providers */
    #[Required]
    public function setProviders(#[AutowireIterator('c975l.ai_provider')] iterable $providers): void
    {
        $this->indexProviders($providers);
    }

    // Prefix of the four config entries ("<prefix>-provider", "-api-key", "-base-uri", "-model")
    abstract protected function configPrefix(): string;

    // AiUsage::FEATURE_* the spend and the failures are recorded under
    abstract protected function feature(): string;

    public function isEnabled(): bool
    {
        return array_all(
            ['provider', 'api-key', 'base-uri', 'model'],
            fn (string $entry): bool => (bool) $this->config($entry),
        );
    }

    // The one place a provider is called. Null when disabled or on failure, which is logged and recorded so AiAlertProvider can raise it. $images are data URIs ("data:image/jpeg;base64,...") read along with the prompt, for a model that sees - protected like the rest, so only a feature written for it ever sends one
    /** @param list<string> $images */
    protected function send(string $prompt, ?string $system = null, int $maxTokens = 2048, array $images = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $provider = $this->namedProvider($this->configPrefix() . '-provider');
        if (!$provider instanceof AiProviderInterface) {
            return null;
        }

        try {
            $answer = $provider->complete($prompt, $system, $maxTokens, $images, [
                'api-key' => $this->config('api-key'),
                'base-uri' => $this->config('base-uri'),
                'model' => $this->config('model'),
            ]);
            $this->aiUsageTracker->record($answer['inputTokens'], $answer['outputTokens'], $this->feature());

            // An answer the ceiling cut is billed all the same, but a half JSON or a half text is worth nothing to the feature
            if ($answer['truncated'] ?? false) {
                $this->fail('the answer reached its ceiling of ' . $maxTokens . ' tokens and was cut');

                return null;
            }

            return $answer['text'];
        } catch (ExceptionInterface $e) {
            // The response body carries the provider's own detail, far more actionable than "HTTP 400"
            $message = $e->getMessage();
            if ($e instanceof HttpExceptionInterface) {
                $message .= ' ' . $e->getResponse()->getContent(false);
            }

            $this->fail($message);

            return null;
        }
    }

    // Logged and recorded, so AiAlertProvider can raise it
    private function fail(string $message): void
    {
        $this->logger->error('AI request failed ({feature}): {message}', ['feature' => $this->feature(), 'message' => $message]);
        $this->aiUsageTracker->recordFailure($message, $this->feature());
    }

    private function config(string $entry): string
    {
        return (string) $this->configService->get($this->configPrefix() . '-' . $entry);
    }
}
