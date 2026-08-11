<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\ImageDimensionsReader;
use c975L\UiBundle\Service\MediaDimensionsFiller;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class MediaCrudControllerTest extends TestCase
{
    private function createController(string $projectDir = '/tmp', bool $mayEditSiteGraphics = true): MediaCrudController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/site-graphic/edit');

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_SITE_GRAPHIC_EDITOR');

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($mayEditSiteGraphics);

        return new MediaCrudController(
            $translator,
            new MediaDimensionsFiller(new ImageDimensionsReader(), $projectDir),
            $adminUrlGenerator,
            $configService,
            $security,
            $projectDir
        );
    }

    // Same protection as MediaUploadType's: the media library's own form exposes width/height too, and saving a row whose inputs were rendered blank used to erase the size auto-detected on upload
    public function testUpdateEntityKeepsTheAutoDetectedDimensionsOfAMediaSavedWithBlankInputs(): void
    {
        $projectDir = sys_get_temp_dir() . '/media-crud-test-' . uniqid();
        new Filesystem()->mkdir($projectDir . '/public/medias');
        imagepng(imagecreatetruecolor(640, 480), $projectDir . '/public/medias/photo.png');

        $media = new Media();
        $media->setFilename('medias/photo.png');

        $this->createController($projectDir)->updateEntity($this->createStub(EntityManagerInterface::class), $media);

        new Filesystem()->remove($projectDir);
        $this->assertSame('640', $media->getWidth());
        $this->assertSame('480', $media->getHeight());
    }

    // Creating a Media with no Block (e.g. for a bundle showcase) is reserved to super admins - regular admins keep adding media the normal way, through a Block's own form
    public function testConfigureActionsRestrictsNewToSuperAdmin(): void
    {
        $controller = $this->createController();

        $permissions = $this->configureActions($controller)->getAsDto(null)->getActionPermissions();
        $this->assertSame('ROLE_SUPER_ADMIN', $permissions[Action::NEW]);
    }

    // Lets the admin back out of a create/edit without saving
    public function testConfigureActionsAddsCancelOnNewAndEdit(): void
    {
        $actions = $this->configureActions($this->createController());

        $this->assertNotNull($actions->getAsDto(Crud::PAGE_NEW)->getAction(Crud::PAGE_NEW, 'cancel'));
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, 'cancel'));
    }

    // Detail showed neither the file (only forms do) nor a single action, and every gallery thumbnail now opens a form instead - see siteGraphicUrls() for the role-carrying rows, whose Edit is hidden here
    public function testConfigureActionsDisablesDetail(): void
    {
        $disabled = $this->configureActions($this->createController())->getAsDto(null)->getDisabledActions();

        $this->assertContains(Action::DETAIL, $disabled);
    }

    // A real EasyAdmin runtime pre-populates the default actions before calling configureActions() - update() there assumes EDIT/DELETE already exist on PAGE_INDEX
    private function configureActions(MediaCrudController $controller): Actions
    {
        return $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );
    }

    private function mediaWithId(int $id, ?string $role = null): Media
    {
        $media = new Media()->setRole($role);
        new \ReflectionProperty(Media::class, 'id')->setValue($media, $id);

        return $media;
    }

    // Reaches the map the gallery template reads to link a read-only site graphic to the screen that does edit it
    private function siteGraphicUrls(MediaCrudController $controller, Media ...$medias): array
    {
        $entities = array_map(
            static fn (Media $media): EntityDto => new EntityDto(Media::class, new ClassMetadata(Media::class), null, $media),
            $medias
        );

        return new \ReflectionMethod($controller, 'siteGraphicUrls')->invoke($controller, new EntityCollection($entities));
    }

    // Site-wide graphics are read-only in the library (their Edit action is hidden), so their thumbnail must open SiteGraphicCrudController rather than fall back on EasyAdmin's next default row action
    public function testSiteGraphicUrlsCoversTheRoleCarryingMediasOnly(): void
    {
        $urls = $this->siteGraphicUrls(
            $this->createController(),
            $this->mediaWithId(1, Media::ROLE_LOGO),
            $this->mediaWithId(2)
        );

        $this->assertSame([1 => '/management/site-graphic/edit'], $urls);
    }

    // SiteGraphicCrudController gates itself with the "site-role-editor" config: an admin without it would only get a 403 out of the link, so no url is handed over at all and the thumbnail stops being a link (see media_index.html.twig)
    public function testSiteGraphicUrlsAreWithheldFromAnAdminWhoMayNotEditThem(): void
    {
        $urls = $this->siteGraphicUrls(
            $this->createController(mayEditSiteGraphics: false),
            $this->mediaWithId(1, Media::ROLE_LOGO)
        );

        $this->assertSame([], $urls);
    }

    private function fileFieldImageUri(MediaCrudController $controller): \Closure
    {
        foreach ($controller->configureFields('new') as $field) {
            if ('file' === $field->getAsDto()->getProperty()) {
                return $field->getAsDto()->getFormTypeOptions()['image_uri'];
            }
        }

        throw new \LogicException('file field not found');
    }

    public function testFileFieldImageUriKeepsOriginalUriForNonPdf(): void
    {
        $imageUri = $this->fileFieldImageUri($this->createController());

        $this->assertSame(
            'photo.jpg',
            $imageUri(new Media()->setMimeType('image/jpeg'), 'photo.jpg')
        );
    }

    public function testFileFieldImageUriReturnsNullWhenOriginalUriIsNull(): void
    {
        $imageUri = $this->fileFieldImageUri($this->createController());

        $this->assertNull($imageUri(new Media()->setMimeType('application/pdf'), null));
    }

    public function testFileFieldImageUriFallsBackToWebpThumbnailWhenItExists(): void
    {
        $projectDir = sys_get_temp_dir() . '/' . uniqid('ui-media-crud-test-');
        mkdir($projectDir . '/public/documents', 0777, true);
        file_put_contents($projectDir . '/public/documents/report.webp', '');

        try {
            $imageUri = $this->fileFieldImageUri($this->createController($projectDir));

            $this->assertSame(
                'documents/report.webp',
                $imageUri(new Media()->setMimeType('application/pdf'), 'documents/report.pdf')
            );
        } finally {
            unlink($projectDir . '/public/documents/report.webp');
            rmdir($projectDir . '/public/documents');
            rmdir($projectDir . '/public');
            rmdir($projectDir);
        }
    }

    public function testFileFieldImageUriKeepsOriginalUriWhenNoWebpThumbnailExists(): void
    {
        $imageUri = $this->fileFieldImageUri($this->createController(sys_get_temp_dir() . '/' . uniqid('ui-media-crud-test-')));

        $this->assertSame(
            'documents/report.pdf',
            $imageUri(new Media()->setMimeType('application/pdf'), 'documents/report.pdf')
        );
    }
}
