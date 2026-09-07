<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use Symfony\Contracts\Translation\TranslatorInterface;

// Builds the "the check itself blew up" row (network/API failure, not a check result) shared by every HealthCheckProviderInterface implementation wrapping a client call in a try/catch. Lives here rather than in SiteBundle, where it started as a trait: any bundle contributing a health check that calls something over the network needs the same shape, and a trait shared across bundles is only ever analysed against the users living in the same package. The row is a warning and never an error, which is the whole point of the class: an API answering 500 says the verdict is missing, not that the page is broken, and ranking the two alike had a site whose PageSpeed quota ran out announce five broken pages it had never looked at - StatusReportBuilder mails every error out, where a warning stays on this site's own dashboard
class HealthCheckErrorRow
{
    // Takes the exception message rather than the \Throwable itself - a provider may defer row-building past its catch block (see SiteBundle's ContentQualityHealthCheckProvider, which only keeps the message and turns it into a row later). $domain is the calling bundle's own translation domain, the summary being its wording, not this bundle's
    public static function build(TranslatorInterface $translator, string $domain, string $url, ?string $label, string $translationId, string $message, ?string $editUrl = null): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'status' => HealthCheckResult::STATUS_WARNING,
            'summary' => $translator->trans($translationId, ['%message%' => $message], $domain),
            'details' => ['error' => $message],
            'editUrl' => $editUrl,
        ];
    }
}
