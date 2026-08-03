<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\UiBundle\Controller\Management\MediaCrudController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Registry\MediaUsageRegistry;
use c975L\UiBundle\Service\ImageDimensionsReader;
use c975L\UiBundle\Service\MediaDimensionsFiller;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class MediaCrudControllerTest extends TestCase
{
    private function createController(string $projectDir = '/tmp'): MediaCrudController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new MediaCrudController(
            $this->createStub(MediaUsageRegistry::class),
            $translator,
            new MediaDimensionsFiller(new ImageDimensionsReader(), $projectDir),
            $projectDir
        );
    }

    // Same protection as MediaUploadType's: the media library's own form exposes width/height too, and saving a row whose inputs were rendered blank used to erase the size auto-detected on upload
    public function testUpdateEntityKeepsTheAutoDetectedDimensionsOfAMediaSavedWithBlankInputs(): void
    {
        $projectDir = sys_get_temp_dir() . '/media-crud-test-' . uniqid();
        (new Filesystem())->mkdir($projectDir . '/public/medias');
        imagepng(imagecreatetruecolor(640, 480), $projectDir . '/public/medias/photo.png');

        $media = new Media();
        $media->setFilename('medias/photo.png');

        $this->createController($projectDir)->updateEntity($this->createStub(EntityManagerInterface::class), $media);

        (new Filesystem())->remove($projectDir);
        $this->assertSame('640', $media->getWidth());
        $this->assertSame('480', $media->getHeight());
    }

    // Creating a Media with no Block (e.g. for a bundle showcase) is reserved to super admins - regular admins keep adding media the normal way, through a Block's own form
    public function testConfigureActionsRestrictsNewToSuperAdmin(): void
    {
        $controller = $this->createController();

        // A real EasyAdmin runtime pre-populates default actions (EDIT, DELETE...) before calling configureActions() - update() below assumes EDIT/DELETE already exist on PAGE_INDEX (DETAIL is added by the controller itself, not pre-populated here)
        $actions = $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $permissions = $actions->getAsDto(null)->getActionPermissions();
        $this->assertSame('ROLE_SUPER_ADMIN', $permissions[Action::NEW]);
    }

    // Lets the admin back out of a create/edit without saving - unlike the other CRUDs, Detail stays enabled here (see configureActions' own comment), only Cancel is new
    public function testConfigureActionsAddsCancelOnNewAndEdit(): void
    {
        $controller = $this->createController();

        $actions = $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $this->assertNotNull($actions->getAsDto(Crud::PAGE_NEW)->getAction(Crud::PAGE_NEW, 'cancel'));
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, 'cancel'));
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
            $imageUri((new Media())->setMimeType('image/jpeg'), 'photo.jpg')
        );
    }

    public function testFileFieldImageUriReturnsNullWhenOriginalUriIsNull(): void
    {
        $imageUri = $this->fileFieldImageUri($this->createController());

        $this->assertNull($imageUri((new Media())->setMimeType('application/pdf'), null));
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
                $imageUri((new Media())->setMimeType('application/pdf'), 'documents/report.pdf')
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
            $imageUri((new Media())->setMimeType('application/pdf'), 'documents/report.pdf')
        );
    }
}
