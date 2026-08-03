<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Service\ContactSnippetBuilder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ContactExtension extends AbstractExtension
{
    public function __construct(private readonly ContactSnippetBuilder $snippetBuilder)
    {
    }

    public function getFunctions(): array
    {
        return [
            // Already encoded here, hence "is_safe": the builder escapes every tag-opening character itself, which Twig's own json_encode filter would then escape a second time
            new TwigFunction('contact_json_ld', [$this, 'jsonLd'], ['is_safe' => ['html']]),
            new TwigFunction('contact_day_runs', [$this, 'dayRuns']),
        ];
    }

    // Splits the days of one opening range into runs of consecutive days, so a template can print "Monday - Friday"
    // rather than the five of them; a lone day comes back as a one-entry run, and the week order is the stored one
    public function dayRuns(array $days): array
    {
        $ordered = array_values(array_intersect(ContactSnippetBuilder::DAYS, $days));
        $runs = [];

        foreach ($ordered as $day) {
            $previous = end($runs) ?: null;
            $isNext = null !== $previous
                && array_search($day, ContactSnippetBuilder::DAYS, true) === array_search(end($previous), ContactSnippetBuilder::DAYS, true) + 1;

            if ($isNext) {
                $runs[\count($runs) - 1][] = $day;

                continue;
            }

            $runs[] = [$day];
        }

        return $runs;
    }

    // Returns the <script type="application/ld+json"> payload for a "contact_details" block, empty when there is nothing to publish
    public function jsonLd(array $data, ?string $imageUrl = null): string
    {
        return $this->snippetBuilder->buildJson($data, $imageUrl);
    }
}
