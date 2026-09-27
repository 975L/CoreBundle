<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Form\Type;

use App\Entity\User;
use c975L\ConfigBundle\Form\Type\AccountProfileType;
use c975L\ConfigBundle\Service\SiteLocales;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class AccountProfileTypeTest extends TestCase
{
    // Every column of the site's User is offered, and none the bundles manage
    public function testOffersTheSiteColumnsOnly(): void
    {
        $form = $this->form(['firstname', 'lastname']);

        $this->assertSame(['firstname', 'lastname'], array_keys($form->all()));
    }

    // A "locale" column is chosen among the site's languages, each named in itself
    public function testOffersTheSiteLanguagesForALocaleColumn(): void
    {
        $locale = $this->form(['locale'])->get('locale')->getConfig();

        $this->assertInstanceOf(ChoiceType::class, $locale->getType()->getInnerType());
        $this->assertSame(['Français' => 'fr', 'English' => 'en'], $locale->getOption('choices'));
    }

    // The column the member logs in with is not theirs to edit here, whatever it is called
    public function testLeavesOutTheColumnTheMemberLogsInWith(): void
    {
        $user = new User()->setEmail('laurent');

        // The builder rather than the form: the scaffold's User has neither column to read the values back from
        $builder = $this->factory(['username', 'firstname'], [], ['username' => 'laurent', 'firstname' => 'Laurent'])->createBuilder(AccountProfileType::class, $user);

        $this->assertSame(['firstname'], array_keys($builder->all()));
    }

    // A column holding a date or an array is offered, not cast to a string and failing the page
    public function testKeepsANonScalarColumn(): void
    {
        $builder = $this->factory(['birthDate'], [], ['birthDate' => new \DateTime('1970-01-01')])->createBuilder(AccountProfileType::class, new User()->setEmail('laurent'));

        $this->assertSame(['birthDate'], array_keys($builder->all()));
    }

    // A translated label when a catalogue has one, the column's name made readable otherwise, never a raw key
    public function testLabelsAreTranslatedOrReadable(): void
    {
        $form = $this->form(['firstname', 'birthPlace'], ['label.firstname' => 'Prénom']);

        $this->assertSame('label.firstname', $form->get('firstname')->getConfig()->getOption('label'));
        $this->assertSame('Birth place', $form->get('birthPlace')->getConfig()->getOption('label'));
    }

    // The form for a User mapping those site columns on top of the managed ones, with those "config" translations
    /**
     * @param list<string>          $siteFields
     * @param array<string, string> $translations
     */
    private function form(array $siteFields, array $translations = []): FormInterface
    {
        return $this->factory($siteFields, $translations)->create(AccountProfileType::class);
    }

    // The form factory knowing the type, over that mapping
    /**
     * @param list<string>          $siteFields
     * @param array<string, string> $translations
     * @param array<string, mixed>  $values       what the User holds in each site column
     */
    private function factory(array $siteFields, array $translations = [], array $values = []): FormFactoryInterface
    {
        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getFieldNames')->willReturn([...AccountProfileType::MANAGED_FIELDS, ...$siteFields]);
        $metadata->method('getFieldValue')->willReturnCallback(static fn (object $entity, string $field): mixed => $values[$field] ?? null);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $translations, 'fr', 'config');

        return Forms::createFormFactoryBuilder()
            ->addType(new AccountProfileType($entityManager, new SiteLocales(['fr', 'en'], 'fr'), $translator))
            ->getFormFactory();
    }
}
