<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Validator;

use c975L\UiBundle\Validator\SafeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Validation;

class SafeUrlValidatorTest extends TestCase
{
    private function violations(mixed $value): int
    {
        return \count(Validation::createValidator()->validate($value, new SafeUrl()));
    }

    // Everything an editor really types in a link field, and what the link pickers store
    #[DataProvider('legitimateValues')]
    public function testALegitimateValuePasses(mixed $value): void
    {
        $this->assertSame(0, $this->violations($value));
    }

    /** @return iterable<string, array{mixed}> */
    public static function legitimateValues(): iterable
    {
        yield 'null' => [null];
        yield 'empty' => [''];
        yield 'http' => ['http://example.com/a?b=c#d'];
        yield 'https mixed case' => ['HTTPS://example.com'];
        yield 'mailto' => ['mailto:contact@example.com'];
        yield 'tel' => ['tel:+33600000000'];
        yield 'path' => ['/shop/books'];
        yield 'path holding a colon' => ['/shop/a:b'];
        yield 'page slug' => ['a-propos'];
        yield 'dotted relative' => ['../shop'];
        yield 'anchor' => ['#offers'];
        yield 'anchor holding a colon' => ['#a:b'];
        yield 'query' => ['?page=2'];
        yield 'query holding a scheme' => ['?next=javascript:x'];
        yield 'query holding an ampersand' => ['/a?b=1&c=2'];
        yield 'protocol-relative' => ['//example.com/a'];
        yield 'placeholder' => ['{{ order_url }}'];
        yield 'url around a placeholder' => ['https://example.com/{{ slug }}'];
        yield 'page target' => ['page:12'];
        yield 'page target and anchor' => ['page:12#offer-34'];
        yield 'route target' => ['route:shop_index'];
        yield 'route target of a row' => ['route:crowdfunding.12'];
        yield 'stringable' => [new class implements \Stringable {
            public function __toString(): string
            {
                return '/a';
            }
        }];
    }

    #[DataProvider('dangerousValues')]
    public function testADangerousValueIsRefused(string $value): void
    {
        $this->assertSame(1, $this->violations($value));
    }

    /** @return iterable<string, array{string}> */
    public static function dangerousValues(): iterable
    {
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'mixed case' => ['JaVaScRiPt:alert(1)'];
        yield 'leading spaces' => ['   javascript:alert(1)'];
        yield 'leading control' => ["\x01javascript:alert(1)"];
        yield 'leading null' => ["\x00javascript:alert(1)"];
        yield 'tab in the scheme' => ["java\tscript:alert(1)"];
        yield 'newline in the scheme' => ["java\nscript:alert(1)"];
        yield 'carriage return in the scheme' => ["java\r\nscript:alert(1)"];
        yield 'space before the colon' => ["javascript\t:alert(1)"];
        yield 'zero-width space' => ["java\u{200B}script:alert(1)"];
        yield 'non-breaking space' => ["\u{00A0}javascript:alert(1)"];
        yield 'entity in the scheme' => ['java&#115;cript:alert(1)'];
        yield 'entity with no semicolon' => ['java&#115cript:alert(1)'];
        yield 'colon as an entity with no semicolon' => ['javascript&#58alert(1)'];
        yield 'hexadecimal colon with no semicolon' => ['javascript&#x3A//%0Aalert(1)'];
        yield 'encoded colon' => ['javascript&colon;alert(1)'];
        yield 'twice encoded' => ['javascript&amp;colon;alert(1)'];
        yield 'data' => ['data:text/html,x'];
        yield 'vbscript' => ['vbscript:x'];
        yield 'file' => ['file:///etc/passwd'];
        yield 'ftp' => ['ftp://example.com'];
        yield 'blob' => ['blob:https://example.com/x'];
        yield 'invalid bytes' => ["java\xFFscript:alert(1)"];
        yield 'route target carrying a scheme' => ['route:javascript:alert(1)'];
        yield 'route target carrying a call' => ['route:a(1)'];
        yield 'page target that is no id' => ['page:javascript'];
    }

    public function testAValueThatIsNoTextIsRefused(): void
    {
        $this->assertSame(1, $this->violations([]));
    }

    // The message the three "validators" catalogs carry
    public function testTheViolationCarriesTheTranslatedKey(): void
    {
        $violations = Validation::createValidator()->validate('javascript:x', new SafeUrl());

        $this->assertSame('label.url_scheme_not_allowed', $violations[0]->getMessageTemplate());
    }

    public function testItIsUsableAsAnAttribute(): void
    {
        $subject = new class {
            #[SafeUrl]
            public ?string $url = 'javascript:x';
        };

        $validator = Validation::createValidatorBuilder()
            ->setMetadataFactory(new LazyLoadingMetadataFactory(new AttributeLoader()))
            ->getValidator();

        $this->assertCount(1, $validator->validate($subject));
    }
}
