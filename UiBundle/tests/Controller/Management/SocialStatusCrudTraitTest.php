<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\UiBundle\Contract\SocialContentStatusProviderInterface;
use c975L\UiBundle\Controller\Management\Trait\SocialStatusCrudTrait;
use c975L\UiBundle\Model\SocialContentStatus;
use Doctrine\ORM\Mapping\ClassMetadata;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

// The social post badge the bundles' lists share: SocialBundle asked about the rows of the page shown, in one call, each row worded in its own bundle's domain
class SocialStatusCrudTraitTest extends TestCase
{
    // Only the rows of the page are asked about, and each gets the badge of its own post, its date in the language's format
    public function testTheRowsOfThePageAreAskedAboutInOneCall(): void
    {
        $statuses = $this->createMock(SocialContentStatusProviderInterface::class);
        $statuses->expects($this->once())->method('getStatuses')->with('product', ['1', '2'])->willReturn([
            '2' => new SocialContentStatus(SocialContentStatus::PUBLISHED, new \DateTimeImmutable('2026-10-09 12:00')),
        ]);
        [$first, $second] = [$this->row(1), $this->row(2)];

        $this->controller($statuses, [$first, $second])->index($this->context());

        $this->assertSame('', $this->badge($first));
        $this->assertSame('<span class="badge badge-success">shop:label.product_social_published 10/09</span>', $this->badge($second));
    }

    // A post still in draft holds the row all the same, shown apart from a published one
    public function testADraftIsShownAsAWarning(): void
    {
        $statuses = $this->createStub(SocialContentStatusProviderInterface::class);
        $statuses->method('getStatuses')->willReturn(['1' => new SocialContentStatus(SocialContentStatus::RESERVED, new \DateTimeImmutable('2026-10-12 12:00'))]);
        $row = $this->row(1);

        $this->controller($statuses, [$row])->index($this->context());

        $this->assertSame('<span class="badge badge-warning">shop:label.product_social_reserved 10/12</span>', $this->badge($row));
    }

    // The date is the site's, a post planned just before midnight in Paris being read the day it goes out there rather than the day before in UTC
    public function testTheDateIsReadInTheSiteTimeZone(): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');

        try {
            $statuses = $this->createStub(SocialContentStatusProviderInterface::class);
            $statuses->method('getStatuses')->willReturn(['1' => new SocialContentStatus(SocialContentStatus::RESERVED, new \DateTimeImmutable('2026-10-11 23:30', new \DateTimeZone('UTC')))]);
            $row = $this->row(1);

            $this->controller($statuses, [$row])->index($this->context());

            $this->assertStringEndsWith(' 10/12</span>', (string) $this->badge($row));
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    // An empty page asks nothing
    public function testAnEmptyPageAsksNothing(): void
    {
        $statuses = $this->createMock(SocialContentStatusProviderInterface::class);
        $statuses->expects($this->never())->method('getStatuses');

        $this->controller($statuses, [])->index($this->context());
    }

    // A list without the column, a trash for one, asks nothing either
    public function testAListWithoutTheColumnAsksNothing(): void
    {
        $statuses = $this->createMock(SocialContentStatusProviderInterface::class);
        $statuses->expects($this->never())->method('getStatuses');
        $controller = $this->controller($statuses, [$this->row(1)], false);

        $controller->index($this->context());

        $this->assertSame([], $controller->fields());
    }

    // The column is labelled in the bundle's own domain, and carries a class named after its type for a guided project to point at
    public function testTheColumnIsLabelledInTheBundleDomain(): void
    {
        $fields = $this->controller($this->createStub(SocialContentStatusProviderInterface::class), [])->fields();

        $this->assertCount(1, $fields);
        $this->assertSame('label.product_social', $fields[0]->getAsDto()->getLabel()->getMessage());
        $this->assertSame('shop', $fields[0]->getAsDto()->getLabel()->getDomain());
        $this->assertStringContainsString('product-social', $fields[0]->getAsDto()->getCssClass());
    }

    // A site without SocialBundle has no column at all
    public function testNoColumnWithoutSocialBundle(): void
    {
        $this->assertSame([], $this->controller(null, [])->fields());
    }

    // A controller using the trait over a parent answering the given rows, as EasyAdmin's index() hands them over - its translator naming the domain before the key
    private function controller(?SocialContentStatusProviderInterface $socialStatuses, array $rows, bool $columnShown = true): SocialStatusCrudTraitTestController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $parameters = [], ?string $domain = null): string => str_ends_with($id, '_date_format') ? 'm/d' : trim($domain . ':' . $id . ' ' . implode(' ', $parameters)));

        return new SocialStatusCrudTraitTestController($socialStatuses, $translator, KeyValueStore::new(['entities' => new EntityCollection($rows)]), $columnShown);
    }

    // A listed row carrying the badge column, as EasyAdmin has processed it
    private function row(int $id): EntityDto
    {
        $entity = new SocialStatusCrudTraitTestEntity($id);
        $metadata = new ClassMetadata(SocialStatusCrudTraitTestEntity::class);
        $metadata->setIdentifier(['id']);
        $row = new EntityDto(SocialStatusCrudTraitTestEntity::class, $metadata, null, $entity);
        $row->setFields(new FieldCollection([TextField::new('id')->setCustomOption('socialStatus', true)]));

        return $row;
    }

    // The badge column's formatted value of a row
    private function badge(EntityDto $row): mixed
    {
        return $row->getFields()?->first()?->getFormattedValue();
    }

    // Never read by the parent below
    private function context(): AdminContext
    {
        return new \ReflectionClass(AdminContext::class)->newInstanceWithoutConstructor();
    }
}

// A listed row, its id all the trait reads of it
class SocialStatusCrudTraitTestEntity
{
    public function __construct(public readonly int $id)
    {
    }
}

// Stands for EasyAdmin's AbstractCrudController, whose index() answers the rows of the page
class SocialStatusCrudTraitTestParent
{
    public function __construct(private readonly KeyValueStore $responseParameters)
    {
    }

    public function index(AdminContext $context): KeyValueStore | Response
    {
        return $this->responseParameters;
    }
}

// A controller using the trait, as ShopBundle's ProductCrudController does
class SocialStatusCrudTraitTestController extends SocialStatusCrudTraitTestParent
{
    use SocialStatusCrudTrait;

    public function __construct(
        private readonly ?SocialContentStatusProviderInterface $socialStatuses,
        private readonly TranslatorInterface $translator,
        KeyValueStore $responseParameters,
        private readonly bool $columnShown,
    ) {
        parent::__construct($responseParameters);
    }

    public function fields(): array
    {
        return $this->socialStatusFields();
    }

    private function socialSourceType(): string
    {
        return 'product';
    }

    private function socialTranslationDomain(): string
    {
        return 'shop';
    }

    private function socialStatusColumnShown(): bool
    {
        return $this->columnShown;
    }
}
