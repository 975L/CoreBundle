<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Component\HttpFoundation\Response;

// Widens the "form-action" of a response's policy, checked by the browser over the whole redirection chain: a form answered by a redirection elsewhere - a payment provider, an app's own url scheme - is otherwise stopped without a word once the policy is limited to 'self'
final class CspFormAction
{
    // The enforced policy and the reported one, each under the name NelmioSecurityBundle also emits for older browsers. A site setting its policy some other way is served the same way, and one setting none at all is left alone
    private const array HEADERS = [
        'Content-Security-Policy',
        'Content-Security-Policy-Report-Only',
        'X-Content-Security-Policy',
        'X-Content-Security-Policy-Report-Only',
    ];

    // Whether the response carries a policy at all, asked first so a site without any CSP never pays for a config lookup on every response
    public static function hasPolicy(Response $response): bool
    {
        return array_any(self::HEADERS, static fn (string $header): bool => $response->headers->has($header));
    }

    // Adds the sources (hosts, or schemes written "scheme:") to every policy header of the response
    /** @param string[] $sources */
    public static function widen(Response $response, array $sources): void
    {
        if ([] === $sources) {
            return;
        }

        foreach (self::HEADERS as $header) {
            if (!$response->headers->has($header)) {
                continue;
            }

            $values = [];
            foreach ($response->headers->all($header) as $value) {
                $values[] = null === $value ? $value : self::addSources($value, $sources);
            }

            $response->headers->set($header, $values);
        }
    }

    // Adds the sources to the policy's "form-action", a policy without it returned as it stands since that directive falls back on nothing, not even "default-src"
    /** @param string[] $sources */
    private static function addSources(string $policy, array $sources): string
    {
        $updated = preg_replace_callback(
            '/(^|;)\s*form-action((?:\s+[^;\s]+)*)/i',
            static function (array $matches) use ($sources): string {
                $current = preg_split('/\s+/', trim($matches[2]), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                // 'none' is only valid on its own, so a site forbidding every form has it dropped rather than being served a policy the browser throws away whole - which would lift the restriction on every other form of the site
                $current = array_values(array_filter($current, static fn (string $source): bool => "'none'" !== strtolower($source)));

                foreach ($sources as $source) {
                    if (!\in_array($source, $current, true)) {
                        $current[] = $source;
                    }
                }

                return ('' === $matches[1] ? '' : $matches[1] . ' ') . 'form-action ' . implode(' ', $current);
            },
            $policy,
            1
        );

        return $updated ?? $policy;
    }
}
