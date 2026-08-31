<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\FormOutputRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

// One computed result of a Form, turning it into a calculator: an arithmetic expression over the Form's own numeric fields and over the outputs declared before it, evaluated at each keystroke without anything being submitted or stored - see ExpressionEvaluator for the grammar, CalculatorController for the evaluation. A Form owning at least one of these is a calculator and has no action (see Form::isCalculator())
#[ORM\Entity(repositoryClass: FormOutputRepository::class)]
#[ORM\Table(name: 'site_form_output')]
#[ORM\UniqueConstraint(name: 'form_output_unique', columns: ['form_id', 'name'])]
#[UniqueEntity(fields: ['form', 'name'])]
class FormOutput implements \Stringable
{
    public const FORMAT_NUMBER = 'number';
    public const FORMAT_CURRENCY = 'currency';
    public const FORMAT_PERCENT = 'percent';

    public const FORMATS = [
        self::FORMAT_NUMBER,
        self::FORMAT_CURRENCY,
        self::FORMAT_PERCENT,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Form::class, inversedBy: 'outputs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Form $form = null;

    #[ORM\Column(length: 100)]
    private ?string $label = null;

    // Derived from "label" and unique among the owning Form's fields AND outputs alike, both living in one single variable namespace - see c975L\UiBundle\Service\FormFieldNamer
    #[ORM\Column(length: 100)]
    private ?string $name = null;

    // Arithmetic over the Form's variables, e.g. "km_an / 100 * conso * prix_e85" - validated at save time (see ValidExpressionsValidator), never evaluated by this entity itself
    #[ORM\Column(length: 500)]
    private ?string $expression = null;

    #[ORM\Column(length: 20)]
    private string $format = self::FORMAT_NUMBER;

    #[ORM\Column(options: ['default' => 0])]
    private int $decimals = 0;

    // Appended after the formatted number, separated from it by a non-breaking space, e.g. "L" or "t de CO₂" - a currency output needs none, the format carries it
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unit = null;

    // A hidden output is an intermediate step other expressions read, e.g. "litres consommés par an" feeding both budgets - it is computed like any other, just never shown
    #[ORM\Column(options: ['default' => true])]
    private bool $visible = true;

    // The one result the visitor came for, rendered big - purely presentational, several highlighted outputs simply all get the treatment
    #[ORM\Column(options: ['default' => false])]
    private bool $highlighted = false;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

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

    public function getExpression(): ?string
    {
        return $this->expression;
    }

    public function setExpression(?string $expression): static
    {
        $this->expression = $expression;

        return $this;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getDecimals(): int
    {
        return $this->decimals;
    }

    public function setDecimals(?int $decimals): static
    {
        $this->decimals = $decimals ?? 0;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    public function isHighlighted(): bool
    {
        return $this->highlighted;
    }

    public function setHighlighted(bool $highlighted): static
    {
        $this->highlighted = $highlighted;

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

    // Same dash/leading-digit treatment as FormField, fields and outputs sharing one namespace inside an expression
    public function getVariableName(): string
    {
        $variable = str_replace('-', '_', (string) $this->name);

        return preg_match('/^[0-9]/', $variable) ? 'f_' . $variable : $variable;
    }
}
