<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Form\Block\ContactDetailsType;
use c975L\UiBundle\Form\Block\ContactHoursType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use c975L\UiBundle\Service\ContactSnippetBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Url;

class ContactDetailsTypeTest extends TestCase
{
    private function buildAddedFields(): array
    {
        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new ContactDetailsType(new BlockAnchorSlugger(new AsciiSlugger()))->buildForm($builder, []);

        return $added;
    }

    public function testBuildFormAddsExpectedFields(): void
    {
        $added = $this->buildAddedFields();

        $expected = [
            'anchor', 'schemaType', 'name', 'description',
            'addressStreetAddress', 'addressComplement', 'addressPostalCode', 'addressLocality', 'addressRegion', 'addressCountryName', 'addressCountryCode',
            'telephone', 'mobile', 'email', 'url', 'hours', 'priceRange', 'mapUrl', 'latitude', 'longitude',
        ];

        foreach ($expected as $field) {
            $this->assertArrayHasKey($field, $added, "\"$field\" should be added to the ContactDetails form");
        }
    }

    // Nothing is required: an editor fills in only what the business actually has, the rest never reaching the graph
    public function testEveryFieldIsOptional(): void
    {
        foreach ($this->buildAddedFields() as $field => $options) {
            $this->assertFalse($options['required'] ?? true, "\"$field\" should be optional");
        }
    }

    public function testSchemaTypeOffersTheTypesTheSnippetBuilderAccepts(): void
    {
        $choices = $this->buildAddedFields()['schemaType']['choices'];

        $this->assertSame(ContactSnippetBuilder::TYPES, array_values($choices));
        $this->assertSame(ContactSnippetBuilder::TYPES, array_keys($choices));
    }

    // A bare "example.com" would render as a relative href - resolved against SiteBundle's sitewide <base href> - and reach the graph non-absolute, so the protocol is prepended on submit and a broken value is refused outright
    public function testUrlsAreStoredAbsoluteAndValidated(): void
    {
        $added = $this->buildAddedFields();

        foreach (['url', 'mapUrl'] as $field) {
            $this->assertSame('https', $added[$field]['default_protocol']);
            $this->assertInstanceOf(Url::class, $added[$field]['constraints'][0]);
        }

        $this->assertInstanceOf(Email::class, $added['email']['constraints'][0]);
    }

    public function testHoursIsAnAddableCollectionOfHourRanges(): void
    {
        $hours = $this->buildAddedFields()['hours'];

        $this->assertSame(ContactHoursType::class, $hours['entry_type']);
        $this->assertTrue($hours['allow_add']);
        $this->assertTrue($hours['allow_delete']);
    }

    public function testConfigureOptionsDefaultsToNullDataClassAndUiTranslationDomain(): void
    {
        $type = new ContactDetailsType(new BlockAnchorSlugger(new AsciiSlugger()));
        $resolver = new OptionsResolver();
        $type->configureOptions($resolver);

        $options = $resolver->resolve();

        $this->assertNull($options['data_class']);
        $this->assertSame('ui', $options['translation_domain']);
    }
}
