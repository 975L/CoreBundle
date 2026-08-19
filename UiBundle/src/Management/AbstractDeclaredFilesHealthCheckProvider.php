<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckExhaustiveInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Checks that every file a bundle's rows name is actually on the server, one row per file. What it exists for is that a missing one says nothing anywhere: the database still declares it, the admin screens still list it, and only whoever loads the page it belongs to sees the hole - a watermark signature that never got deployed had every photo of a gallery signed with the other one for two days, silently (see ImageWatermarker::prepare, which falls back rather than refusing to sign).
//
// Read off the disk rather than over http, like SvgFontsHealthCheckProvider: these are this site's own files under public/, and one that is there but unreachable is a server's business, not a database's.
//
// One subclass per bundle, each naming its own rows (see MediaFilesHealthCheckProvider): the check is the same everywhere, only what declares a file changes.
abstract class AbstractDeclaredFilesHealthCheckProvider implements HealthCheckExhaustiveInterface
{
    public function __construct(
        protected readonly ConfigServiceInterface $configService,
        protected readonly TranslatorInterface $translator,
        protected readonly string $projectDir,
    ) {
    }

    /**
     * One entry per file the bundle's rows declare - an entity holding two of them (an image and a video, say) yields two.
     * "label" is what names the row on the dashboard, "editUrl" the admin screen the file is re-uploaded from.
     *
     * @return iterable<array{filename: string, label: string, editUrl: ?string}>
     */
    abstract protected function declaredFiles(): iterable;

    public function runChecks(): array
    {
        $siteUrl = rtrim((string) $this->configService->get('site-url'), '/');

        $rows = [];
        foreach ($this->declaredFiles() as $file) {
            $filename = $file['filename'];

            // A row that names no file declares nothing, and there is nothing to look for: a media created for its caption alone, a fixture, an entity whose upload never landed
            if ('' === $filename) {
                continue;
            }

            // The OK row is what lets a re-uploaded file go back to green where its filename is stable (the six singleton roles), and the exhaustive purge is what retires the old url everywhere else - re-uploading names the file anew (see UiMediaNamer), so the green row lands on a url of its own rather than replacing the red one (see HealthCheckExhaustiveInterface)
            $found = is_file($this->projectDir . '/public/' . $filename);

            $rows[] = [
                'url' => $siteUrl . '/' . $filename,
                'label' => '' === $file['label'] ? $filename : $file['label'],
                'status' => $found ? HealthCheckResult::STATUS_OK : HealthCheckResult::STATUS_ERROR,
                'summary' => $this->translator->trans(
                    $found ? 'label.health_check_declared_file_found' : 'label.health_check_declared_file_missing',
                    ['%file%' => $filename],
                    'ui'
                ),
                'editUrl' => $file['editUrl'],
            ];
        }

        return $rows;
    }
}
