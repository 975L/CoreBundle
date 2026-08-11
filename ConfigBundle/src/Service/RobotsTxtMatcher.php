<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

// Decides whether a robots.txt allows a given path, following the matching rules Google documents: the group naming the crawler wins over the wildcard one, then the longest matching rule decides, an Allow beating a Disallow of the same length. Built for one crawler at a time and immutable, so cross-checking a sitemap's whole url list parses the file once instead of once per url (see SitemapRobotsHealthCheckProvider)
// #[Exclude] because services.yaml registers all of src/ as autowired services, and this is a value object built through for() rather than one
#[Exclude]
final class RobotsTxtMatcher
{
    // The fields that carry a rule - every other line (Sitemap, Crawl-delay, Host...) says nothing about whether a path may be crawled
    private const array RULE_FIELDS = ['allow', 'disallow'];

    // @param list<array{allow: bool, pattern: string}> $rules
    private function __construct(
        private readonly array $rules,
    ) {
    }

    // The rules that apply to one crawler: its own group when the file names it, the wildcard group otherwise, and no rule at all when the file names neither - an unmatched crawler being allowed everywhere. Matched on the exact name rather than by substring, which is enough for the two this bundle ever asks about ('*' and 'Googlebot') and never guesses a group the file didn't mean
    public static function for(string $content, string $userAgent = '*'): self
    {
        $groups = self::parseGroups($content);
        $agent = strtolower($userAgent);

        return new self($groups[$agent] ?? $groups['*'] ?? []);
    }

    // True unless a Disallow matches the path more specifically than any Allow - the longest matching pattern decides, so a "Disallow: /pages/" and an "Allow: /pages/contact" coexist as the file intends. No matching rule at all means allowed, which is robots.txt's default
    public function allows(string $path): bool
    {
        $path = '' === $path ? '/' : $path;
        $best = null;

        foreach ($this->rules as $rule) {
            if (!self::matches($rule['pattern'], $path)) {
                continue;
            }

            $length = \strlen($rule['pattern']);
            if (null === $best || $length > $best['length'] || ($length === $best['length'] && $rule['allow'])) {
                $best = ['length' => $length, 'allow' => $rule['allow']];
            }
        }

        return null === $best || $best['allow'];
    }

    // The rules of every group in the file, keyed by the crawler each one names. Consecutive User-agent lines share the rules that follow them, and a User-agent line coming after a rule opens a new group - the grouping robots.txt defines, and what tells a file scoping "Disallow: /" to one bot apart from one applying it to all
    // @return array<string, list<array{allow: bool, pattern: string}>>
    private static function parseGroups(string $content): array
    {
        $groups = [];
        $agents = [];
        $expectingAgents = true;

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if ('' === $line || !str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = explode(':', $line, 2);
            $field = strtolower(trim($field));
            $value = trim($value);

            if ('user-agent' === $field) {
                if (!$expectingAgents) {
                    $agents = [];
                    $expectingAgents = true;
                }
                if ('' !== $value) {
                    $agents[] = strtolower($value);
                }

                continue;
            }

            if (!\in_array($field, self::RULE_FIELDS, true) || [] === $agents) {
                continue;
            }

            $expectingAgents = false;

            // A group naming a crawler exists as soon as it carries a rule field, empty or not - registered before the empty value is dropped below, otherwise "User-agent: Googlebot / Disallow:" would leave no group at all and for() would fall back on the wildcard one, applying to Googlebot the very rules the file wrote it out of
            foreach ($agents as $agent) {
                $groups[$agent] ??= [];
            }

            // An empty value carries no rule: "Disallow:" is how a file allows everything, and an "Allow:" with nothing to allow says as little
            if ('' === $value) {
                continue;
            }

            foreach ($agents as $agent) {
                $groups[$agent][] = ['allow' => 'allow' === $field, 'pattern' => self::normalizePattern($value)];
            }
        }

        return $groups;
    }

    // A pattern is matched against a path, so it starts at the root whether or not the file spelled the leading slash - a "Disallow: pages/" means the same thing as "/pages/", and treating it as a path that can never match would silently ignore the rule. A wildcard opening the pattern is left alone, it already matches from anywhere
    private static function normalizePattern(string $pattern): string
    {
        return str_starts_with($pattern, '/') || str_starts_with($pattern, '*') ? $pattern : '/' . $pattern;
    }

    // Prefix match, with the two wildcards robots.txt allows: "*" for any run of characters, and a trailing "$" anchoring the pattern to the end of the path
    private static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        if ($anchored) {
            $pattern = substr($pattern, 0, -1);
        }

        $regex = str_replace('\*', '.*', preg_quote($pattern, '#'));

        return 1 === preg_match('#^' . $regex . ($anchored ? '$' : '') . '#', $path);
    }
}
