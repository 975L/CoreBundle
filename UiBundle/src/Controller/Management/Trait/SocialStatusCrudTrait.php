<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management\Trait;

use c975L\UiBundle\Model\SocialContentStatus;
use EasyCorp\Bundle\EasyAdminBundle\Collection\EntityCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

// Whether a social post holds the row - reserved by a draft, or published - on a site with SocialBundle only. The class using it carries $socialStatuses (see SocialContentStatusProviderInterface) and $translator, names the type SocialBundle files its rows under and the domain of its keys: "label.<type>_social", plus its "_date_format", "_reserved" and "_published" variants, are each bundle's own, a French "Publiée" agreeing with a page where a product is "Publié"
trait SocialStatusCrudTrait
{
    // What marks the column among the fields of a row, its property being the id another column may show too
    private const string SOCIAL_STATUS_OPTION = 'socialStatus';

    // The source type SocialBundle is asked about, and the prefix of the column's translation keys: 'product', 'page', 'book'...
    abstract private function socialSourceType(): string;

    // The translation domain the column's keys are shipped in
    abstract private function socialTranslationDomain(): string;

    // Whether the list shown has the column at all, a trash or a list of templates holding nothing a post could
    private function socialStatusColumnShown(): bool
    {
        return true;
    }

    // The index column, left empty here: the page's rows are only known once EasyAdmin has formatted them (see index())
    /** @return list<FieldInterface> */
    private function socialStatusFields(): array
    {
        if (null === $this->socialStatuses || !$this->socialStatusColumnShown()) {
            return [];
        }

        return [
            TextField::new('id')
                ->setLabel(t('label.' . $this->socialSourceType() . '_social', [], $this->socialTranslationDomain()))
                ->formatValue(static fn (): string => '')
                ->renderAsHtml()
                ->setSortable(false)
                // What a guided project highlights, the column sharing its "id" property with the IdField: "product-social", "crowdfunding-social"...
                ->addCssClass($this->socialSourceType() . '-social')
                ->onlyOnIndex()
                ->setCustomOption(self::SOCIAL_STATUS_OPTION, true),
        ];
    }

    // The badges of the page shown, SocialBundle being asked about its rows alone and in one call - the parameters being rendered only once returned
    #[\Override]
    public function index(AdminContext $context): KeyValueStore | Response
    {
        $responseParameters = parent::index($context);
        $entities = $responseParameters instanceof KeyValueStore ? $responseParameters->get('entities') : null;
        if (null === $this->socialStatuses || !$this->socialStatusColumnShown() || !$entities instanceof EntityCollection || 0 === count($entities)) {
            return $responseParameters;
        }

        $ids = [];
        foreach ($entities as $entity) {
            $ids[] = (string) $entity->getPrimaryKeyValue();
        }
        $statuses = $this->socialStatuses->getStatuses($this->socialSourceType(), $ids);

        foreach ($entities as $entity) {
            foreach ($entity->getFields() ?? [] as $field) {
                if (true === $field->getCustomOption(self::SOCIAL_STATUS_OPTION)) {
                    $field->setFormattedValue($this->socialStatusBadge($statuses[(string) $entity->getPrimaryKeyValue()] ?? null));
                }
            }
        }

        return $responseParameters;
    }

    // The badge of the post holding the row, empty when none does
    private function socialStatusBadge(?SocialContentStatus $status): string
    {
        if (null === $status) {
            return '';
        }

        // The date's format is the language's own ("09/10" reads as the 10th of September in English), in the site's time zone whatever the one it was read in
        $prefix = 'label.' . $this->socialSourceType() . '_social';
        $domain = $this->socialTranslationDomain();
        $date = $status->at->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format($this->translator->trans($prefix . '_date_format', [], $domain));

        return sprintf(
            '<span class="badge %s">%s</span>',
            $status->isPublished() ? 'badge-success' : 'badge-warning',
            htmlspecialchars($this->translator->trans($prefix . '_' . $status->state, ['%date%' => $date], $domain)),
        );
    }
}
