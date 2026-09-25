<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\MenuBuilder;
use c975L\ConfigBundle\Management\MenuEntryResolver;
use c975L\ConfigBundle\Management\UnusedConfigFeatureReader;
use c975L\ConfigBundle\Management\UnusedFeatureBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Translation\TranslatorInterface;

class UnusedFeatureBuilderTest extends TestCase
{
    // One CRUD per entity name, the builder reading the entity off the controller class
    private function crud(string $entity): string
    {
        $crud = match ($entity) {
            'Gallery' => new class extends AbstractCrudController {
                public static function getEntityFqcn(): string
                {
                    return 'App\Entity\Gallery';
                }
            },
            'Product' => new class extends AbstractCrudController {
                public static function getEntityFqcn(): string
                {
                    return 'App\Entity\Product';
                }
            },
            'Redirect' => new class extends AbstractCrudController {
                public static function getEntityFqcn(): string
                {
                    return 'App\Entity\Redirect';
                }
            },
            'Form' => new class extends AbstractCrudController {
                public static function getEntityFqcn(): string
                {
                    return 'App\Entity\Form';
                }
            },
            'Payment' => new class extends AbstractCrudController {
                public static function getEntityFqcn(): string
                {
                    return 'App\Entity\Payment';
                }
            },
        };

        return $crud::class;
    }

    private function menu(string $entity, array $extra = []): array
    {
        return array_merge(['controller' => $this->crud($entity), 'label' => 'label.' . $entity, 'translation_domain' => 'site', 'icon' => 'fa fa-star'], $extra);
    }

    // Rows per entity, anything left out holding one
    private function createBuilder(array $menus, array $counts, bool $isGranted = true, string $day = '2026-01-01', array $configFeatures = []): UnusedFeatureBuilder
    {
        $menuBuilder = $this->createStub(MenuBuilder::class);
        $menuBuilder->method('getOrderedMenus')->willReturn($menus);

        $resolver = $this->createStub(MenuEntryResolver::class);
        $resolver->method('isGranted')->willReturn($isGranted);
        $resolver->method('url')->willReturnCallback(fn (array $entry) => '/management/' . $entry['label']);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(function (string $fqcn) use ($counts) {
            $repository = $this->createStub(EntityRepository::class);
            $repository->method('count')->willReturn($counts[substr($fqcn, \strlen('App\Entity\\'))] ?? 1);

            return $repository;
        });

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $id) => 'translated:' . $id);

        $configReader = $this->createStub(UnusedConfigFeatureReader::class);
        $configReader->method('getFeatures')->willReturn($configFeatures);

        return new UnusedFeatureBuilder($menuBuilder, $resolver, $entityManager, $configReader, $translator, new MockClock($day));
    }

    public function testAnEmptyCrudIsAFeatureNotUsedYet(): void
    {
        $builder = $this->createBuilder(
            [$this->menu('Gallery', ['description' => 'label.info_gallery']), $this->menu('Product')],
            ['Gallery' => 0],
        );

        $this->assertSame(
            [['label' => 'translated:label.Gallery', 'description' => 'translated:label.info_gallery', 'url' => '/management/label.Gallery']],
            $builder->getFeatures(),
        );
    }

    // A list of what happened being empty says nothing about its feature
    public function testAnEmptyListOfWhatHappenedIsLeftOut(): void
    {
        $builder = $this->createBuilder([$this->menu('Payment', ['creatable' => false])], ['Payment' => 0]);

        $this->assertSame([], $builder->getFeatures());
    }

    // Nothing to point at for a user the sidebar doesn't draw the entry for
    public function testAnEntryTheUserCannotOpenIsLeftOut(): void
    {
        $builder = $this->createBuilder([$this->menu('Gallery')], ['Gallery' => 0], false);

        $this->assertSame([], $builder->getFeatures());
    }

    // A screen with no entity behind it (a health check, an overview) has nothing to count
    public function testAnEntryThatIsNoCrudIsLeftOut(): void
    {
        $builder = $this->createBuilder([['controller' => self::class, 'label' => 'label.overview', 'translation_domain' => 'site', 'icon' => 'fa fa-list']], []);

        $this->assertSame([], $builder->getFeatures());
    }

    // A few at a time, the next ones coming round the next day, and the same list on every reload of one day
    public function testTheListIsRotatedByTheDay(): void
    {
        $menus = [$this->menu('Gallery'), $this->menu('Product'), $this->menu('Redirect'), $this->menu('Form')];
        $counts = ['Gallery' => 0, 'Product' => 0, 'Redirect' => 0, 'Form' => 0];

        $firstDay = array_column($this->createBuilder($menus, $counts, true, '2026-01-01')->getFeatures(), 'label');
        $nextDay = array_column($this->createBuilder($menus, $counts, true, '2026-01-02')->getFeatures(), 'label');

        $this->assertSame(['translated:label.Gallery', 'translated:label.Product', 'translated:label.Redirect'], $firstDay);
        $this->assertSame(['translated:label.Product', 'translated:label.Redirect', 'translated:label.Form'], $nextDay);
        $this->assertSame($firstDay, array_column($this->createBuilder($menus, $counts, true, '2026-01-01')->getFeatures(), 'label'));
    }

    // The keys left empty come after the screens, in the same list
    public function testTheConfigFeaturesFollowTheScreens(): void
    {
        $configFeature = ['label' => 'Clé Google Maps', 'description' => '', 'url' => '/management/config/1'];
        $builder = $this->createBuilder([$this->menu('Gallery')], ['Gallery' => 0], true, '2026-01-01', [$configFeature]);

        $this->assertSame(['translated:label.Gallery', 'Clé Google Maps'], array_column($builder->getFeatures(), 'label'));
    }
}
