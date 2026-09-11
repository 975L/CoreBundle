<?php

namespace c975L\UiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\Routing\Attribute\Route;

// Serves a file that already lives under public/, twice over: "download_file" forces the browser to save it, "asset_file" opens it in place. Both exist for what the web server alone cannot do - name the disposition, and answer behind the firewall, a site putting either path under an access_control rule to reserve its own files. Deliberately NOT merged with PrivateFileResponseFactory, which serves the digital items bought through ShopBundle/CrowdfundingBundle from outside public/ and must keep its own access checks
class DownloadController extends AbstractController
{
    // What "asset_file" opens in place, matched on the start of the detected type: a scanned deed, a photograph, a pdf book, a film or a recording
    private const array VIEWABLE_TYPES = ['image/', 'video/', 'audio/', 'application/pdf'];

    #[Route(
        path: '/download/{file}',
        requirements: ['file' => '[\p{L}0-9\-\_\/]+.[a-z]{1,5}.[a-z]*'],
        name: 'download_file',
        methods: ['GET']
    )]
    public function downloadFile(string $file): Response
    {
        return $this->serve($file, ResponseHeaderBag::DISPOSITION_ATTACHMENT, false);
    }

    // The same file, opened in the browser rather than saved - what a scanned deed, a photograph or a pdf book is looked at through. Its requirement takes anything, unlike the route above: the files a site displays this way are named by whoever scanned them, spaces, accents and parentheses included, where a download is offered on a media the back office named. Which is why this one, and only this one, checks the path and the type of what it serves itself
    #[Route(
        path: '/asset/{file}',
        requirements: ['file' => '^.*$'],
        name: 'asset_file',
        methods: ['GET']
    )]
    public function assetFile(string $file): Response
    {
        return $this->serve($file, ResponseHeaderBag::DISPOSITION_INLINE, true);
    }

    // $private is what the inline route asks for and the download one does not: a file opened in place is normally one a firewall reserves to its members, where a download is offered on a page anybody reads, and marking it private would only stop a shared cache from doing its work
    private function serve(string $file, string $disposition, bool $private): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($file, '/');

        // The path asked for has to stay under public/: without this a "../.env" would climb out of the served tree, which the download route's own requirement forbids but the asset one's does not. Read off the path as it was asked for, rather than off realpath(): a site is free to mount its medias directory elsewhere and symlink it under public/, and resolving the link would turn every one of its files into a 404
        if (\in_array('..', explode('/', str_replace('\\', '/', $file)), true)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas.');
        }

        if (!is_file($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas.');
        }

        // Opened in place, only what the route exists to show: an ".htaccess", an "index.php" or a ".user.ini" the web server itself refuses to serve would otherwise be read out in clear by anyone
        if ($private && !$this->isViewable($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition($disposition, basename($filePath));

        // Private, and kept private: a file served from behind a firewall is the visitor's own, never an intermediary's to hold (same reading as PrivateFileResponseFactory). BinaryFileResponse marks itself public, which is what AbstractSessionListener reacts to on a request carrying a session - it would take the hour back down to zero, hence the header that tells it to leave these headers alone
        if ($private) {
            $response->setPrivate();
            $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
        }

        $response->setMaxAge(3600);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }

    // Read off the content rather than the extension, which a file named by whoever scanned it may lack or misstate - a whitelist rather than a list of what to refuse, so nothing a site drops in public/ tomorrow slips through
    private function isViewable(string $filePath): bool
    {
        $mimeType = (string) new File($filePath)->getMimeType();

        return array_any(self::VIEWABLE_TYPES, static fn (string $type): bool => str_starts_with($mimeType, $type));
    }
}
