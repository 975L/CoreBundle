<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\FontCrudController;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Controller\Management\SiteGraphicCrudController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\FontRepository;
use c975L\UiBundle\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

// The files this bundle's own rows declare: the media library and the site graphics (favicon, logo, the two watermark signatures), plus the uploaded font files. The site graphics are the ones this check was written for - they are excluded from a site's repository, so a deployment never carries them and only an upload made on the very server puts them there
class MediaFilesHealthCheckProvider extends AbstractDeclaredFilesHealthCheckProvider
{
    // Named here rather than restated as a literal wherever a row of this kind is picked out
    public const string KIND = 'files-ui';

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly FontRepository $fontRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        ConfigServiceInterface $configService,
        TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
    ) {
        parent::__construct($configService, $translator, $projectDir);
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    protected function declaredFiles(): iterable
    {
        foreach ($this->mediaRepository->findWithFilename() as $media) {
            yield [
                'filename' => (string) $media->getFilename(),
                // The role is what names a site graphic on its own screen, an admin-typed name being asked for block medias only
                'label' => (string) ($media->getName() ?: $media->getRole()),
                'editUrl' => $this->editUrl($this->controllerFor($media), $media->getId()),
            ];
        }

        foreach ($this->fontRepository->findWithFilename() as $font) {
            yield [
                'filename' => (string) $font->getFilename(),
                'label' => (string) $font->getName(),
                'editUrl' => $this->editUrl(FontCrudController::class, $font->getId()),
            ];
        }
    }

    // A media carrying a role is managed from the site graphics screen, never from the media library - the two are the same entity behind two CRUDs (see SiteGraphicCrudController), and the link has to open the one the file is re-uploaded from
    private function controllerFor(Media $media): string
    {
        return null === $media->getRole() ? MediaCrudController::class : SiteGraphicCrudController::class;
    }

    private function editUrl(string $controller, ?int $id): ?string
    {
        return null === $id ? null : $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controller)
            ->setAction(Action::EDIT)
            ->setEntityId($id)
            ->generateUrl();
    }
}
