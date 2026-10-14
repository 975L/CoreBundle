<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// The updates of the site's own mobile app, served by the site itself rather than by a paid service: the Expo Updates protocol (https://docs.expo.dev/technical-specs/expo-updates-1/) its expo-updates asks at each start. Nothing is computed here: an update is published from the developer's machine into "public/app-updates/{runtime version}/{platform}/", its manifest stored as the very bytes that were signed (re-encoding them would break the signature) and its files next to it, served by the web server like any file. This route only says which update is the latest: "latest" names it, written last so no app hears of an update whose files are not all there yet, or reads "rollback" to send the apps back to the code they were built with. A site holding no update for that runtime version answers 404
class AppUpdateController extends AbstractController
{
    // What a runtime version may look like ("1.0.0"): it names a folder
    private const string RUNTIME_PATTERN = '/^[A-Za-z0-9._-]{1,64}$/';

    // What an update id looks like: it names a folder too
    private const string UPDATE_ID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    // The value of "latest" sending the apps back to the code they were built with
    private const string ROLLBACK = 'rollback';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/app-updates')]
        private readonly string $updatesDirectory,
    ) {
    }

    // MANIFEST. The latest update for the app's runtime version and platform, or the directive standing for it, as one part of a multipart answer
    #[Route('/app/updates', name: 'config_app_updates', methods: ['GET'])]
    public function manifest(Request $request): Response
    {
        $platform = (string) $request->headers->get('expo-platform');
        $runtime = (string) $request->headers->get('expo-runtime-version');
        if (!\in_array($platform, ['android', 'ios'], true) || 1 !== preg_match(self::RUNTIME_PATTERN, $runtime)) {
            throw $this->createNotFoundException();
        }

        // The only version of the protocol written here
        if ('1' !== $request->headers->get('expo-protocol-version')) {
            return new Response(null, Response::HTTP_NOT_ACCEPTABLE);
        }

        $directory = $this->updatesDirectory . '/' . $runtime . '/' . $platform;
        $latest = is_file($directory . '/latest') ? trim((string) file_get_contents($directory . '/latest')) : '';
        $current = $request->headers->get('expo-current-update-id');

        if (self::ROLLBACK === $latest) {
            // Already running the code it was built with: nothing to roll back
            return $current === $request->headers->get('expo-embedded-update-id')
                ? $this->part($directory . '/no-update', 'directive')
                : $this->part($directory . '/rollback', 'directive');
        }

        if (1 !== preg_match(self::UPDATE_ID_PATTERN, $latest)) {
            throw $this->createNotFoundException();
        }

        return null !== $current && strtolower($current) === $latest
            ? $this->part($directory . '/no-update', 'directive')
            : $this->part($directory . '/' . $latest . '/manifest', 'manifest');
    }

    // One part, its signature alongside when the update was signed: "{name}.json" holds the signed bytes, "{name}.sig" the value of its "expo-signature" header
    private function part(string $file, string $name): Response
    {
        if (!is_file($file . '.json')) {
            throw $this->createNotFoundException();
        }

        $headers = "Content-Disposition: form-data; name=\"{$name}\"\r\nContent-Type: application/json; charset=utf-8\r\n";
        if (is_file($file . '.sig')) {
            $headers .= 'expo-signature: ' . trim((string) file_get_contents($file . '.sig')) . "\r\n";
        }

        $boundary = bin2hex(random_bytes(16));

        return new Response(
            "--{$boundary}\r\n{$headers}\r\n" . file_get_contents($file . '.json') . "\r\n--{$boundary}--\r\n",
            Response::HTTP_OK,
            [
                'Content-Type' => 'multipart/mixed; boundary=' . $boundary,
                'expo-protocol-version' => '1',
                'expo-sfv-version' => '0',
                'Cache-Control' => 'private, max-age=0',
            ]
        );
    }
}
