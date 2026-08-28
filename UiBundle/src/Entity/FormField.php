<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\FormFieldRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: FormFieldRepository::class)]
#[ORM\Table(name: 'site_form_field')]
#[ORM\UniqueConstraint(name: 'form_field_unique', columns: ['form_id', 'name'])]
#[UniqueEntity(fields: ['form', 'name'])]
class FormField implements \Stringable
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_EMAIL = 'email';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_PASSWORD = 'password';
    public const TYPE_PASSWORD_REPEATED = 'password_repeated';
    public const TYPE_URL = 'url';
    public const TYPE_TEL = 'tel';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE = 'date';
    public const TYPE_RANGE = 'range';
    public const TYPE_CHOICE = 'choice';

    // Types carrying a numeric value, hence usable as a variable in a FormOutput expression - see ExpressionEvaluator/CalculatorController
    public const NUMERIC_TYPES = [
        self::TYPE_NUMBER,
        self::TYPE_RANGE,
        self::TYPE_CHOICE,
    ];

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXTAREA,
        self::TYPE_EMAIL,
        self::TYPE_CHECKBOX,
        self::TYPE_PASSWORD,
        self::TYPE_PASSWORD_REPEATED,
        self::TYPE_URL,
        self::TYPE_TEL,
        self::TYPE_NUMBER,
        self::TYPE_DATE,
        self::TYPE_RANGE,
        self::TYPE_CHOICE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Form::class, inversedBy: 'fields')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Form $form = null;

    #[ORM\Column(length: 100)]
    private ?string $label = null;

    // Derived from "label", scoped unique within the owning Form - see c975L\UiBundle\Service\FormFieldNamer
    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_TEXT;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeholder = null;

    // Optional link shown next to the label (e.g. a checkbox field's "J'accepte les [CGU]" pointing at the real terms-of-use page) - see FormSubmissionType, which appends it to the label as a real <a> when set
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column]
    private bool $required = false;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    // A field seeded by its owning bundle as part of a form's core identity (e.g. register's "email"/"plainPassword") - see FormFieldType, which disables the "type" field and the delete button for such rows: reorderable/relabellable, never removable or reclassifiable
    #[ORM\Column(options: ['default' => false])]
    private bool $restricted = false;

    // Bounds and increment of a number/range field, ignored by every other type - a range with no min/max would render a slider going nowhere, so FormSubmissionType falls back to 0/100 rather than emitting a broken input
    #[ORM\Column(nullable: true)]
    private ?float $minValue = null;

    #[ORM\Column(nullable: true)]
    private ?float $maxValue = null;

    #[ORM\Column(nullable: true)]
    private ?float $stepValue = null;

    // Value the field starts with, so a calculator shows a meaningful result before the visitor touches anything - kept as text, a choice field's default being one of its own option values
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultValue = null;

    // A choice field's options, as [['label' => 'Véhicule léger', 'value' => '1.15'], ...] - the value is what the expression sees, which is why a choice field counts as numeric (see NUMERIC_TYPES)
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = null;

    public function __toString(): string
    {
        return (string) $this->label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getForm(): ?Form
    {
        return $this->form;
    }

    public function setForm(?Form $form): static
    {
        $this->form = $form;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;

        return $this;
    }

    public function isRestricted(): bool
    {
        return $this->restricted;
    }

    public function setRestricted(bool $restricted): static
    {
        $this->restricted = $restricted;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position ?? 0;

        return $this;
    }

    public function getMinValue(): ?float
    {
        return $this->minValue;
    }

    public function setMinValue(?float $minValue): static
    {
        $this->minValue = $minValue;

        return $this;
    }

    public function getMaxValue(): ?float
    {
        return $this->maxValue;
    }

    public function setMaxValue(?float $maxValue): static
    {
        $this->maxValue = $maxValue;

        return $this;
    }

    public function getStepValue(): ?float
    {
        return $this->stepValue;
    }

    public function setStepValue(?float $stepValue): static
    {
        $this->stepValue = $stepValue;

        return $this;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): static
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    /** @return array<int, array{label: string, value: string}> */
    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    /** @param array<int, array{label: string, value: string}>|null $options */
    public function setOptions(?array $options): static
    {
        $this->options = [] === $options ? null : $options;

        return $this;
    }

    // Virtual, not persisted - the options edited as one line per option, "Véhicule léger|1.15", rather than through a third level of nested collection in an admin screen that already holds two
    public function getOptionsText(): ?string
    {
        if ([] === $this->getOptions()) {
            return null;
        }

        return implode("\n", array_map(
            static fn (array $option): string => $option['label'] . '|' . $option['value'],
            $this->getOptions()
        ));
    }

    public function setOptionsText(?string $optionsText): static
    {
        $options = [];
        foreach (preg_split('/\R/', (string) $optionsText) ?: [] as $line) {
            if ('' === trim($line)) {
                continue;
            }
            // No separator means the admin typed the value alone, which is a usable option labelled by itself
            [$label, $value] = array_pad(explode('|', $line, 2), 2, null);
            $options[] = [
                'label' => trim($label),
                'value' => trim($value ?? $label),
            ];
        }

        return $this->setOptions($options);
    }

    // Whether this field's submitted value can feed a FormOutput expression
    public function isNumeric(): bool
    {
        return in_array($this->type, self::NUMERIC_TYPES, true);
    }

    // The identifier this field is known by inside an expression: its own "name" with dashes turned into underscores, a dash being a subtraction to any expression parser - see FormFieldNamer, which slugs the label and so routinely produces "prix-de-l-essence"
    public function getVariableName(): string
    {
        $variable = str_replace('-', '_', (string) $this->name);

        // An expression variable can't start with a digit, while a label like "95 sans plomb" slugs to exactly that
        return preg_match('/^[0-9]/', $variable) ? 'f_' . $variable : $variable;
    }
}
