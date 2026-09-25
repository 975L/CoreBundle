<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\MediaTranslationFileListener;
use c975L\UiBundle\Repository\TranslationRepository;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\MediaTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

// The file a media shows in another language lives on the disk beside the media's own, and Vich knows nothing of it: this is what writes it and what takes it away
class MediaTranslationFileListenerTest extends TestCase
{
    private const int MEDIA_ID = 12;

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-translated-file-' . uniqid();
        mkdir($this->projectDir . '/public/medias/site', 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->projectDir . '/public/medias/site/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->projectDir . '/public/medias/site');
        rmdir($this->projectDir . '/public/medias');
        rmdir($this->projectDir . '/public');
        rmdir($this->projectDir);
    }

    // Written once the block is saved, beside the original, and the file it was copied from left in place for the next demo reload
    public function testAStagedFileIsWrittenOnFlush(): void
    {
        $source = $this->createSource();
        $translator = $this->createTranslator([]);
        $translator->stageFile($this->createMedia(), 'en', new File($source));

        $this->createListener($translator, false)->postFlush($this->createStub(PostFlushEventArgs::class));

        $this->assertFileExists($this->projectDir . '/public/medias/site/block-hero-12-en-' . substr((string) md5_file($source), 0, 8) . '.png');
        $this->assertFileExists($source);
        unlink($source);
    }

    // Another file given for a language gets another name, so the one no longer shown is taken away
    public function testTheFileItReplacesIsTakenAway(): void
    {
        $previous = $this->createPublicFile('block-hero-12-en-1a2b3c4d.webp');
        $source = $this->createSource();
        $translator = $this->createTranslator(['filename' => 'medias/site/block-hero-12-en-1a2b3c4d.webp']);
        $translator->stageFile($this->createMedia(), 'en', new File($source));

        $this->createListener($translator, false)->postFlush($this->createStub(PostFlushEventArgs::class));

        $this->assertFileDoesNotExist($previous);
        unlink($source);
    }

    // The same file given again lands under the same name, which is kept rather than taken away
    public function testTheSameFileGivenAgainIsKept(): void
    {
        $source = $this->createSource();
        $name = 'block-hero-12-en-' . substr((string) md5_file($source), 0, 8) . '.png';
        $previous = $this->createPublicFile($name);
        $translator = $this->createTranslator(['filename' => 'medias/site/' . $name]);
        $translator->stageFile($this->createMedia(), 'en', new File($source));

        $this->createListener($translator, false)->postFlush($this->createStub(PostFlushEventArgs::class));

        $this->assertFileExists($previous);
        unlink($source);
    }

    // A duplicated page carries its media's translations, paths included: the copy still shows the file the original is giving up
    public function testAFileAnotherMediaStillShowsIsLeftInPlace(): void
    {
        $previous = $this->createPublicFile('block-hero-12-en-1a2b3c4d.webp');
        $translator = $this->createTranslator(['filename' => 'medias/site/block-hero-12-en-1a2b3c4d.webp']);
        $translator->stageFile($this->createMedia(), 'en', null, true);

        $this->createListener($translator, true)->postFlush($this->createStub(PostFlushEventArgs::class));

        $this->assertFileExists($previous);
    }

    // Removed with its block, a media takes its other languages' files along, which Vich's own cleanup never sees
    public function testARemovedMediaTakesItsTranslatedFilesAlong(): void
    {
        $file = $this->createPublicFile('block-hero-12-es-1a2b3c4d.webp');
        $repository = $this->createStub(TranslationRepository::class);
        $repository->method('findByOwner')->willReturn(['es' => ['label' => 'La tienda', 'filename' => 'medias/site/block-hero-12-es-1a2b3c4d.webp']]);
        $repository->method('isValueUsedElsewhere')->willReturn(false);
        $listener = new MediaTranslationFileListener($this->createTranslator([]), $repository, $this->projectDir);

        $listener->preRemove(new PreRemoveEventArgs($this->createMedia(), $this->createStub(EntityManagerInterface::class)));
        $listener->postFlush($this->createStub(PostFlushEventArgs::class));

        $this->assertFileDoesNotExist($file);
    }

    // A path read back from a row an import wrote is never trusted to stay under public/
    public function testAPathLeavingPublicIsNeverRemoved(): void
    {
        $outside = $this->projectDir . '/outside.txt';
        file_put_contents($outside, 'kept');
        $translator = $this->createTranslator(['filename' => '../outside.txt']);
        $translator->stageFile($this->createMedia(), 'en', null, true);

        $this->createListener($translator, false)->postFlush($this->createStub(PostFlushEventArgs::class));

        $this->assertFileExists($outside);
        unlink($outside);
    }

    // A row an import wrote may name any file under public/: one not named as a language's file is never taken away
    public function testAFileNotNamedAsALanguagesFileIsNeverRemoved(): void
    {
        $index = $this->createPublicFile('index.php');
        $translator = $this->createTranslator(['filename' => 'medias/site/index.php']);
        $translator->stageFile($this->createMedia(), 'en', null, true);

        $this->createListener($translator, false)->postFlush($this->createStub(PostFlushEventArgs::class));

        $this->assertFileExists($index);
    }

    private function createListener(MediaTranslator $translator, bool $usedElsewhere): MediaTranslationFileListener
    {
        $repository = $this->createStub(TranslationRepository::class);
        $repository->method('isValueUsedElsewhere')->willReturn($usedElsewhere);

        return new MediaTranslationFileListener($translator, $repository, $this->projectDir);
    }

    /** @param array<string, string|null> $values what the language already says */
    private function createTranslator(array $values): MediaTranslator
    {
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('values')->willReturn($values);

        return new MediaTranslator($contentTranslator);
    }

    private function createMedia(): Media
    {
        $media = new Media()->setFilename('medias/site/block-hero-12.webp');
        new \ReflectionProperty(Media::class, 'id')->setValue($media, self::MEDIA_ID);

        return $media;
    }

    private function createSource(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'c975l-translated-source-');
        file_put_contents($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        return $path;
    }

    private function createPublicFile(string $name): string
    {
        $path = $this->projectDir . '/public/medias/site/' . $name;
        file_put_contents($path, 'picture');

        return $path;
    }
}
