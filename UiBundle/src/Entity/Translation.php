<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\Mapping as ORM;

// One field of one thing, said in one other language: a page keeps its structure once and gains a language without gaining a row of its own
// The default language never enters this table, staying where it is (Block::$data, Page::$title) and playing the part of the msgid
// Named rather than related ("ui_block", "site_page"), like Favorite and Rating before it - so no foreign key takes them along, and removing an owner purges them explicitly (see TranslationPurgeListener)
#[ORM\Entity(repositoryClass: TranslationRepository::class)]
#[ORM\Table(name: 'site_translation')]
#[ORM\UniqueConstraint(name: 'uniq_translation_owner_field_locale', columns: ['owner_type', 'owner_id', 'field', 'locale'])]
#[ORM\Index(name: 'idx_translation_owner_locale', columns: ['owner_type', 'owner_id', 'locale'])]
class Translation implements \Stringable
{
    // What a translated block carries as its owner type; the other bundles name their own ("site_page" for a page)
    public const string OWNER_BLOCK = 'ui_block';

    // A form is composed once and translated afterwards, the same way a page is: a field's label or an output's unit is text a visitor reads, not a translation key (see FormTranslator)
    public const string OWNER_FORM_FIELD = 'ui_form_field';

    public const string OWNER_FORM_OUTPUT = 'ui_form_output';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Nullable rather than absent: an editor may deliberately leave a field empty in one language, which is not the same as never having translated it
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $value = null;

    public function __construct(
        // The same vocabulary Favorite and Rating store, so a bundle naming its pages "site_page" once is named that everywhere
        #[ORM\Column(length: 50)]
        private string $ownerType,
        #[ORM\Column]
        private int $ownerId,
        // Which of the owner's fields this says: a key of a block's own data ("title", "content"), or a property of an entity ("title", "summarySocialNetwork")
        #[ORM\Column(length: 100)]
        private string $field,
        #[ORM\Column(length: 10)]
        private string $locale,
    ) {
    }

    public function __toString(): string
    {
        return sprintf('%s#%d.%s[%s]', $this->ownerType, $this->ownerId, $this->field, $this->locale);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerType(): string
    {
        return $this->ownerType;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }
}
