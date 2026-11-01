<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Form\Block\CollectionItemType;
use c975L\UiBundle\Form\Block\ContactDetailsType;
use c975L\UiBundle\Form\Block\MapPointType;
use c975L\UiBundle\Form\Block\VideoIframeType;
use c975L\UiBundle\Form\EmailBlockType;
use c975L\UiBundle\Form\FormFieldType;
use c975L\UiBundle\Form\FormLinkType;
use c975L\UiBundle\Form\LinkTargetType;
use c975L\UiBundle\Form\Util\FormTranslationBuilder;
use c975L\UiBundle\Registry\LinkTargetRegistry;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use c975L\UiBundle\Service\MapGeocoderInterface;
use c975L\UiBundle\Validator\SafeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;

// Every address an editor types in a form carries its check: a field written later without one would print whatever it was given in an href
class UrlFieldConstraintsTest extends TestCase
{
    /** @return array<string, array<string, mixed>> field => its options */
    private function fieldsOf(AbstractType $type): array
    {
        $fields = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $class = null, array $options = []) use (&$fields, $builder) {
            $fields[$name] = $options;

            return $builder;
        });

        $type->buildForm($builder, ['translation_locale' => null]);

        return $fields;
    }

    /** @return iterable<string, array{class-string<AbstractType>, string}> */
    public static function relativeFields(): iterable
    {
        yield 'collection item url' => [CollectionItemType::class, 'url'];
        yield 'collection item image' => [CollectionItemType::class, 'imageUrl'];
        yield 'collection item detail' => [CollectionItemType::class, 'detailUrl'];
        yield 'map point' => [MapPointType::class, 'url'];
        yield 'form field' => [FormFieldType::class, 'url'];
        yield 'form link' => [FormLinkType::class, 'url'];
        yield 'email block' => [EmailBlockType::class, 'url'];
    }

    /** @param class-string<AbstractType> $class */
    #[DataProvider('relativeFields')]
    public function testAFieldTakingARelativeAddressCarriesSafeUrl(string $class, string $field): void
    {
        $this->assertContainsOnlyInstancesOf(SafeUrl::class, $this->fieldsOf($this->instance($class))[$field]['constraints'] ?? [null]);
    }

    /** @return iterable<string, array{class-string<AbstractType>, string}> */
    public static function absoluteFields(): iterable
    {
        yield 'video' => [VideoIframeType::class, 'src'];
        yield 'website' => [ContactDetailsType::class, 'url'];
        yield 'map' => [ContactDetailsType::class, 'mapUrl'];
    }

    /** @param class-string<AbstractType> $class */
    #[DataProvider('absoluteFields')]
    public function testAFieldOnlyAnAbsoluteAddressFitsAllowsHttpAlone(string $class, string $field): void
    {
        $constraint = $this->fieldsOf($this->instance($class))[$field]['constraints'][0] ?? null;

        $this->assertInstanceOf(Url::class, $constraint);
        $this->assertSame(['http', 'https'], $constraint->protocols);
    }

    /** @param class-string<AbstractType> $class */
    private function instance(string $class): AbstractType
    {
        return match ($class) {
            MapPointType::class => new MapPointType($this->createStub(MapGeocoderInterface::class)),
            FormFieldType::class => new FormFieldType($this->createStub(FormTranslationBuilder::class)),
            ContactDetailsType::class => new ContactDetailsType($this->createStub(BlockAnchorSlugger::class)),
            default => new $class(),
        };
    }

    // The field every button, card, hero and slide link goes through, submitted for real: a value of the picker and an address typed by hand both pass
    #[DataProvider('linkTargets')]
    public function testALinkFieldReadsWhatItIsGiven(string $value, bool $valid): void
    {
        $form = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->addExtension(new PreloadedExtension([new LinkTargetType(new LinkTargetRegistry())], []))
            ->getFormFactory()
            ->create(LinkTargetType::class);

        $form->submit($value);

        $this->assertSame($valid, $form->isValid());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function linkTargets(): iterable
    {
        yield 'page' => ['page:12', true];
        yield 'page and anchor' => ['page:12#offer-34', true];
        yield 'route' => ['route:shop_index', true];
        yield 'path' => ['/shop', true];
        yield 'anchor' => ['#offers', true];
        yield 'absolute' => ['https://example.com', true];
        yield 'mailto' => ['mailto:a@example.com', true];
        yield 'javascript' => ['javascript:alert(1)', false];
        yield 'obfuscated' => [" java\tscript:alert(1)", false];
        yield 'data' => ['data:text/html,x', false];
    }

    // A caller of the type keeps what it set, the check coming as a default
    public function testTheLinkFieldStateItsCheckAsADefault(): void
    {
        $resolver = new OptionsResolver();
        $resolver->setDefined(['choice_loader']);
        new LinkTargetType(new LinkTargetRegistry())->configureOptions($resolver);

        $this->assertContainsOnlyInstancesOf(SafeUrl::class, $resolver->resolve()['constraints']);
    }
}
