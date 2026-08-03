<?php

namespace c975L\UiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

// Forces the download of a file already served publicly from public/ (a Content-Disposition: attachment on top of what the web server hands out anyway) - deliberately NOT merged with PrivateFileResponseFactory, which serves the digital items bought through ShopBundle/CrowdfundingBundle from outside public/ and must keep its own access checks
class DownloadController extends AbstractController
{
    #[Route(
        path: '/download/{file}',
        requirements: ['file' => '[\p{L}0-9\-\_\/]+.[a-z]{1,5}.[a-z]*'],
        name: 'download_file',
        methods: ['GET']
    )]
    public function downloadFile(string $file): Response
    {
        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $file;

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($file)
        );
        $response->setMaxAge(3600);
        $response->headers->addCacheControlDirective('must-revalidate', true);

        return $response;
    }
}
