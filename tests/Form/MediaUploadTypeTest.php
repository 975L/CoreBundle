<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Form\ImageClassChoiceType;
use c975L\UiBundle\Form\MediaUploadType;
use c975L\UiBundle\Service\ImageDimensionsReader;
use c975L\UiBundle\Service\MediaDimensionsFiller;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;
use Vich\UploaderBundle\Form\Type\VichImageType;

class MediaUploadTypeTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/media-upload-type-test-' . uniqid();
        (new Filesystem())->mkdir($this->projectDir . '/public/medias');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    private function createType(): MediaUploadType
    {
        return new MediaUploadType(new MediaDimensionsFiller(new ImageDimensionsReader(), $this->projectDir));
    }

    // Records every listener buildForm() registers, keyed by event name - the type registers more than one (PRE_SET_DATA for the "file" widget, POST_SUBMIT for the dimensions), so each helper below picks the one it actually exercises
    private function captureListeners(object $builder, ?string $accept, ?string $context): array
    {
        $listeners = [];
        $builder->method('addEventListener')->willReturnCallback(
            function (string $eventName, callable $callback) use (&$listeners, $builder) {
                $listeners[$eventName] = $callback;

                return $builder;
            }
        );

        $this->createType()->buildForm($builder, ['accept' => $accept, 'context' => $context]);

        return $listeners;
    }

    // buildForm() only branches on $options['accept']/$options['context'] - a mocked builder that just records "add()" calls is enough to assert which fields end up on the form, without having to resolve VichImageType/VichFileType's own (Vich-bundle) constructor dependencies. "file"'s *final* type is decided later, in the PRE_SET_DATA listener (see buildFieldNamesForEntry()) - buildForm() itself only ever adds a VichFileType placeholder, so it keeps rendering as the first field.
    private function buildFieldNames(?string $accept, ?string $context): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null) use (&$added, $builder) {
            $added[$name] = $type;

            return $builder;
        });
        $builder->method('addEventListener')->willReturnSelf();

        $this->createType()->buildForm($builder, ['accept' => $accept, 'context' => $context]);

        return $added;
    }

    // Captures the PRE_SET_DATA listener and fires it with $media as the entry's data, simulating what happens once a real (possibly already-uploaded) Media flows into the form - this is where "file" gets its real VichImageType/VichFileType decision, based on $media's own mimetype when it has one
    private function buildFieldNamesForEntry(?string $accept, ?string $context, ?Media $media): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $listeners = $this->captureListeners($builder, $accept, $context);

        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $type = null) use (&$added, $form) {
            $added[$name] = $type;

            return $form;
        });
        $listeners[FormEvents::PRE_SET_DATA](new PreSetDataEvent($form, $media));

        return $added;
    }

    // Same PRE_SET_DATA capture as above, but keeping each field's options instead of its type - "file"'s label is decided there too, from the entry's own mimetype
    private function buildFileOptionsForEntry(?string $accept, ?string $context, ?Media $media): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $listeners = $this->captureListeners($builder, $accept, $context);

        $form = $this->createStub(FormInterface::class);
        $form->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $form) {
            $added[$name] = $options;

            return $form;
        });
        $listeners[FormEvents::PRE_SET_DATA](new PreSetDataEvent($form, $media));

        return $added['file'];
    }

    public function testBuildFormAlwaysAddsFileAndPositionFields(): void
    {
        $added = $this->buildFieldNames(null, null);

        $this->assertArrayHasKey('file', $added);
        $this->assertArrayHasKey('position', $added);
    }

    public function testPreSetDataUsesVichImageTypeWhenAcceptIsImageAndEntryIsEmpty(): void
    {
        $added = $this->buildFieldNamesForEntry('image/*', null, new Media());

        $this->assertSame(VichImageType::class, $added['file']);
    }

    public function testPreSetDataUsesVichFileTypeWhenAcceptIsNotImage(): void
    {
        $added = $this->buildFieldNamesForEntry('audio/*', null, new Media());

        $this->assertSame(VichFileType::class, $added['file']);
    }

    // The bug this covers: a Slider's entry_options always advertise "image/*,video/*" (a slide can be either), which used to force every slide onto VichFileType - image slides included - losing their thumbnail preview in the admin form. An already-uploaded slide must go off its own real mimetype instead, so an image slide gets its VichImageType preview back and a video slide still gets VichFileType
    public function testPreSetDataForSliderPicksTypeFromTheEntrysOwnMimeTypeNotTheSharedAcceptList(): void
    {
        $image = new Media();
        $image->setMimeType('image/jpeg');
        $addedForImage = $this->buildFieldNamesForEntry('image/*,video/*', 'slider', $image);
        $this->assertSame(VichImageType::class, $addedForImage['file']);

        $video = new Media();
        $video->setMimeType('video/mp4');
        $addedForVideo = $this->buildFieldNamesForEntry('image/*,video/*', 'slider', $video);
        $this->assertSame(VichFileType::class, $addedForVideo['file']);
    }

    // A brand-new Slider entry has no file yet, hence no mimetype - falls back to the shared accept list, which for a Slider always contains "video/*" too, so it defaults to VichFileType
    public function testPreSetDataForSliderFallsBackToVichFileTypeWhenEntryHasNoMimeTypeYet(): void
    {
        $added = $this->buildFieldNamesForEntry('image/*,video/*', 'slider', new Media());

        $this->assertSame(VichFileType::class, $added['file']);
    }

    public function testBuildFormSkipsAllImageMetadataWhenNotAnImage(): void
    {
        $added = $this->buildFieldNames('audio/*', null);

        foreach (['cssClasses', 'alt', 'label', 'width', 'height', 'above', 'credits', 'rightsReserved', 'name'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" should not be added for a non-image upload");
        }
    }

    // A PDF (e.g. document_download) gets no image metadata at all, but does get "name" - an admin-typed value UiMediaNamer slugifies into the stored filename instead of the default "block-{kind}-{id}"
    public function testBuildFormAddsNameFieldForPdfAcceptOnly(): void
    {
        $added = $this->buildFieldNames('application/pdf', null);

        $this->assertArrayHasKey('name', $added);
        foreach (['cssClasses', 'alt', 'label', 'width', 'height', 'above', 'credits', 'rightsReserved'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" should not be added for a PDF upload");
        }
    }

    public function testBuildFormSkipsNameFieldForImageAccept(): void
    {
        $added = $this->buildFieldNames('image/*', null);

        $this->assertArrayNotHasKey('name', $added);
    }

    // "card" context (the Card block's teaser image, see templates/blocks/Card.html.twig) only ever reads the file itself and its cssClasses - none of the other display metadata applies to it
    public function testBuildFormForCardContextKeepsOnlyCssClasses(): void
    {
        $added = $this->buildFieldNames('image/*', 'card');

        $this->assertArrayHasKey('cssClasses', $added);
        foreach (['alt', 'label', 'width', 'height', 'above', 'credits', 'rightsReserved'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" should not be added for the \"card\" context");
        }
    }

    // A "cards" (plural) context - or any other unrecognized context string - must NOT be treated as the Card block's context; only the literal "card" kind should trigger the isCards branch
    public function testBuildFormForUnrecognizedContextBehavesLikePlainImage(): void
    {
        $added = $this->buildFieldNames('image/*', 'cards');

        $this->assertArrayHasKey('alt', $added);
        $this->assertArrayHasKey('credits', $added);
        $this->assertArrayHasKey('rightsReserved', $added);
    }

    // Inside a Slider, a slide has no standalone in-page position to control (no caption/sizing/ "above" layout), but still needs alt/credits/rightsReserved - see Slider/Slider.html.twig
    public function testBuildFormForSliderContextSkipsCaptionPositioningButKeepsAltAndCredits(): void
    {
        $added = $this->buildFieldNames('image/*', 'slider');

        $this->assertArrayHasKey('alt', $added);
        $this->assertArrayHasKey('cssClasses', $added);
        $this->assertArrayHasKey('credits', $added);
        $this->assertArrayHasKey('rightsReserved', $added);
        foreach (['label', 'width', 'height', 'above'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" should not be added for the \"slider\" context");
        }
    }

    // The BannerTitle block's background image is decoration behind the title text, not a captioned figure - only alt (accessibility) and cssClasses survive, same reduced set as "card"
    public function testBuildFormForBannerTitleContextKeepsOnlyAltAndCssClasses(): void
    {
        $added = $this->buildFieldNames('image/*', 'banner_title');

        $this->assertArrayHasKey('alt', $added);
        $this->assertArrayHasKey('cssClasses', $added);
        foreach (['label', 'width', 'height', 'above', 'credits', 'rightsReserved'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" should not be added for the \"banner_title\" context");
        }
    }

    // A portfolio_grid project card reuses "label" as its title and adds "description"/"url" (see Media::$description/$url) - but has no in-page position to control, hence no width/height/above
    public function testBuildFormForPortfolioGridContextAddsTitleDescriptionAndUrlButSkipsPositioning(): void
    {
        $added = $this->buildFieldNames('image/*', 'portfolio_grid');

        foreach (['alt', 'label', 'description', 'url', 'credits', 'rightsReserved'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added for the \"portfolio_grid\" context");
        }
        foreach (['width', 'height', 'above'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" should not be added for the \"portfolio_grid\" context");
        }
    }

    // A "video" block's medias are its video file and an image used as the player's cover - neither is a captioned figure, and the block's own form already carries the player's width/height
    public function testBuildFormForVideoContextAddsNoImageMetadataAtAll(): void
    {
        $added = $this->buildFieldNames('video/mp4,video/webm,video/ogg,image/*', 'video');

        foreach (['cssClasses', 'alt', 'label', 'width', 'height', 'above', 'credits', 'rightsReserved'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" should not be added for the \"video\" context");
        }
    }

    // A "video" block's two rows are the video file and the player's cover image - unlabelled, nothing in the row says which is which, and nothing tells the admin a cover can be added at all
    public function testVideoContextNamesEachUploadAfterItsOwnMimeType(): void
    {
        $image = new Media();
        $image->setMimeType('image/webp');
        $video = new Media();
        $video->setMimeType('video/mp4');
        $accept = 'video/mp4,video/webm,video/ogg,image/*';

        $this->assertSame('label.video_poster', $this->buildFileOptionsForEntry($accept, 'video', $image)['label']);
        $this->assertSame('label.video_file', $this->buildFileOptionsForEntry($accept, 'video', $video)['label']);
        $this->assertSame('label.video_file_or_poster', $this->buildFileOptionsForEntry($accept, 'video', new Media())['label']);
    }

    // Every other kind keeps its uploads unlabelled - a row among rows of the same nature is self-explanatory
    public function testOtherContextsKeepTheirUploadsUnlabelled(): void
    {
        $image = new Media();
        $image->setMimeType('image/webp');

        $this->assertFalse($this->buildFileOptionsForEntry('image/*,video/*', 'slider', $image)['label']);
        $this->assertFalse($this->buildFileOptionsForEntry('image/*', null, new Media())['label']);
    }

    // The accept list spells the video formats out one by one instead of using "video/*" - a brand-new entry (no mimetype yet) must still default to VichFileType, not to the image widget
    public function testPreSetDataFallsBackToVichFileTypeForAnExplicitVideoFormatList(): void
    {
        $added = $this->buildFieldNamesForEntry('video/mp4,video/webm,video/ogg,image/*', 'video', new Media());

        $this->assertSame(VichFileType::class, $added['file']);
    }

    // A standalone Image block (context null, e.g. the "image" kind) gets the full metadata set
    public function testBuildFormForPlainImageContextAddsEveryMetadataField(): void
    {
        $added = $this->buildFieldNames('image/*', null);

        foreach (['cssClasses', 'alt', 'label', 'width', 'height', 'above', 'credits', 'rightsReserved'] as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added for a plain image context");
        }
        $this->assertSame(ImageClassChoiceType::class, $added['cssClasses']);
        foreach (['description', 'url'] as $field) {
            $this->assertArrayNotHasKey($field, $added, "\"$field\" is only added for the \"portfolio_grid\" context");
        }
    }

    // Fires the POST_SUBMIT listener with $media as the entry's submitted data
    private function submitEntry(?string $accept, ?string $context, Media $media): void
    {
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();
        $listeners = $this->captureListeners($builder, $accept, $context);

        $listeners[FormEvents::POST_SUBMIT](new PostSubmitEvent($this->createStub(FormInterface::class), $media));
    }

    private function writePng(string $filename, int $width, int $height): void
    {
        imagepng(imagecreatetruecolor($width, $height), $this->projectDir . '/public/' . $filename);
    }

    private function createUploadedMedia(string $filename): Media
    {
        $media = new Media();
        $media->setFilename($filename);

        return $media;
    }

    // The bug this covers: saving a block whose width/height inputs were rendered empty (a form opened before MediaDimensionsCommand ever filled them) wrote null over the auto-detected size, silently losing it
    public function testPostSubmitRefillsBlankDimensionsFromTheStoredFile(): void
    {
        $this->writePng('medias/photo.png', 800, 600);
        $media = $this->createUploadedMedia('medias/photo.png');

        $this->submitEntry('image/*', null, $media);

        $this->assertSame('800', $media->getWidth());
        $this->assertSame('600', $media->getHeight());
    }

    // A width typed by the admin (height deliberately left blank to keep the ratio) is a chosen pair, not a gap to fill - overwriting the height with the file's own would stretch the image
    public function testPostSubmitLeavesAnAdminTypedDimensionAlone(): void
    {
        $this->writePng('medias/photo.png', 800, 600);
        $media = $this->createUploadedMedia('medias/photo.png');
        $media->setWidth('300');

        $this->submitEntry('image/*', null, $media);

        $this->assertSame('300', $media->getWidth());
        $this->assertNull($media->getHeight());
    }

    // A brand-new entry has no stored file yet (Vich only moves it on flush), and a non-image has no dimensions to read at all - both leave the entry untouched rather than failing the submission
    public function testPostSubmitLeavesAnEntryWithNoReadableFileUntouched(): void
    {
        $this->submitEntry('image/*', null, new Media());
        $media = $this->createUploadedMedia('medias/gone.png');

        $this->submitEntry('image/*', null, $media);

        $this->assertNull($media->getWidth());
        $this->assertNull($media->getHeight());
    }

    // Contexts with no width/height field to blank in the first place (card, slider, portfolio_grid...) never register the listener
    public function testPostSubmitListenerIsOnlyRegisteredWhereTheDimensionFieldsAre(): void
    {
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnSelf();

        $this->assertArrayHasKey(FormEvents::POST_SUBMIT, $this->captureListeners($builder, 'image/*', null));

        foreach (['card', 'slider', 'banner_title', 'portfolio_grid', 'video'] as $context) {
            $builder = $this->createStub(FormBuilderInterface::class);
            $builder->method('add')->willReturnSelf();

            $this->assertArrayNotHasKey(
                FormEvents::POST_SUBMIT,
                $this->captureListeners($builder, 'image/*', $context),
                "the \"$context\" context has no width/height field to protect"
            );
        }
    }

    public function testConfigureOptionsDefaultsToMediaDataClassAndNullAcceptContext(): void
    {
        $type = $this->createType();
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertSame(Media::class, $options['data_class']);
        $this->assertNull($options['accept']);
        $this->assertNull($options['context']);
    }
}
