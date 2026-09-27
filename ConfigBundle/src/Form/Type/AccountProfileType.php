<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Form\Type;

use App\Entity\User;
use c975L\ConfigBundle\Service\SiteLocales;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// What the member edits of their own account: every column the site's User holds beyond the ones the bundles manage, read off its Doctrine mapping as EasyAdmin's Users screen reads it, so a column added to the entity shows here with nothing to write. A site still adds, changes or removes a field through an AbstractTypeExtension on this type. Check readme for usage
class AccountProfileType extends AbstractType
{
    // The columns the bundles set or guard themselves: the address and the password have their own forms, the rest is not the member's to write
    public const array MANAGED_FIELDS = ['id', 'email', 'roles', 'password', 'isVerified', 'isEnabled', 'creation', 'modification', 'lastLogin', 'inactivityNoticeSentAt'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteLocales $siteLocales,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // One field per site column, its type guessed from the mapping, a "locale" column offered as the site's languages. The column the member logs in with is left out whatever its name (genealogie's "username"): changing it is changing the way in
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $metadata = $this->entityManager->getClassMetadata(User::class);
        $user = $options['data'] ?? null;

        foreach (array_diff($metadata->getFieldNames(), self::MANAGED_FIELDS) as $field) {
            if ($user instanceof User && $user->getUserIdentifier() === $metadata->getFieldValue($user, $field)) {
                continue;
            }

            $fieldOptions = ['label' => $this->label($field)];

            if ('locale' === $field) {
                $builder->add($field, ChoiceType::class, $fieldOptions + ['choices' => $this->localeChoices(), 'choice_translation_domain' => false, 'required' => false]);

                continue;
            }

            $builder->add($field, null, $fieldOptions);
        }
    }

    // The site's own entity, which only the application knows
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'config',
        ]);
    }

    // "label.<snake_case>" when a catalogue translates it (ConfigBundle's, or the site's own "config" one), the column's name made readable otherwise, rather than a raw key on the page
    private function label(string $field): string
    {
        $key = 'label.' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $field));

        if ($this->translator instanceof TranslatorBagInterface && $this->translator->getCatalogue()->has($key, 'config')) {
            return $key;
        }

        return ucfirst(strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $field)));
    }

    // The site's languages, each named in itself
    /** @return array<string, string> */
    private function localeChoices(): array
    {
        $choices = [];
        foreach ($this->siteLocales->all() as $locale) {
            $choices[ucfirst((string) \Locale::getDisplayLanguage($locale, $locale))] = $locale;
        }

        return $choices;
    }
}
