<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use c975L\ConfigBundle\Management\StatusReportBuilder;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Hands this site's status report to whoever holds its key, so a console can ask rather than wait.
//
// A site that pushes can only ever say what was true when it last spoke, and cannot say it is down - the receiver has to infer that from a silence, which takes days to become certain and reads as stale data in the meantime. Asked instead, the answer is true at the moment it is read, and no answer at all is itself the answer. Nothing is sent on its own anymore: this route is the whole of what leaves the site.
//
// Read-only and side-effect free, StatusReportBuilder being built that way - it can be asked as often as wanted, and asking it twice costs twice nothing.
class StatusController extends AbstractController
{
    // The key travels in a header, never in the query string: an url ends up in the access log and in the Referer of anything the site serves, a header does not
    public const KEY_HEADER = 'X-Status-Key';

    // Signing what left the site let a short key be merely weak; authenticating who may read it makes it guessable online, against a route that can be asked as often as wanted. Below this length a key is treated as no key at all, the site answering nobody rather than answering weakly
    private const int MIN_KEY_LENGTH = 32;

    // The logger is optional: an app without Monolog leaves it null and everything else works the same, only the refusals go unrecorded
    public function __construct(
        private readonly StatusReportBuilder $statusReportBuilder,
        private readonly ConfigServiceInterface $configService,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    // No "methods" on the route: a GET-only route answers 405 to a POST, which tells whoever asked that the url is real. Refusing here costs one condition and says nothing
    #[Route('/status/report', name: 'config_status_report')]
    public function report(Request $request): Response
    {
        $key = trim((string) $this->configService->get('site-status-key'));
        $given = trim((string) $request->headers->get(self::KEY_HEADER));

        // No usable key configured, no key given, wrong key - all the same answer, and none of them says which. An empty configured key is the default state and a legitimate one: installing the bundle must never make a site answer to a stranger, so it opts out by having nothing to compare rather than by a flag
        if (\strlen($key) < self::MIN_KEY_LENGTH || '' === $given || !hash_equals($key, $given)) {
            // The caller is told nothing, but the site's own log is where a campaign of attempts becomes visible to a fail2ban or a supervision - and where an admin finds out their key is simply too short to ever work.
            // Only a presented key is logged: a bare request is any scanner walking urls, and logging those would bury the attempts that mean something under the noise
            if ('' !== $given) {
                $this->logger?->warning('Status report refused', [
                    'reason' => \strlen($key) < self::MIN_KEY_LENGTH ? 'no usable key configured' : 'wrong key',
                    'ip' => $request->getClientIp(),
                ]);
            }

            throw $this->createNotFoundException();
        }

        $response = new JsonResponse($this->statusReportBuilder->build());

        // The body depends on a header, so a shared cache holding it would serve one caller's report to the next. Kept out of every store rather than varying on the key, which would put a secret in a cache index
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
