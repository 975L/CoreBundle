<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Entity;

use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Validator\ValidExpressions;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

// Shared, generic "form definition" owning a sortable collection of FormField rows, so several bundles (ContactFormBundle today, a future form-builder tomorrow) can each manage their own named row (e.g. name="contact") in one table instead of each keeping a private fields table - see UiBundle Readme
#[ORM\Entity(repositoryClass: FormRepository::class)]
#[ORM\Table(name: 'site_form')]
#[UniqueEntity('name')]
#[ValidExpressions]
class Form implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $name = null;

    // Key resolved via c975L\UiBundle\Registry\FormActionRegistry to process a submission (e.g. "send_email") - nullable, a Form with no action set simply can't be submitted yet
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $action = null;

    // Free-shape config consumed by whichever FormActionInterface "action" points to (e.g. send_email reads "to"/"from"/"subject"/"template"...) - same principle as Block::$data, interpreted differently per action, not by Form itself
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $actionConfig = null;

    // A Form seeded by its owning bundle (e.g. ContactFormBundle's "contact") - see FormCrudController, which disables the "name" field for such rows: still fully editable otherwise, just never renamed/duplicated into a conflicting identity
    #[ORM\Column(options: ['default' => false])]
    private bool $restricted = false;

    // Lets an admin pause a Form without unpublishing its Page or clearing "action" - checked by FormController before building/submitting, see FormDisabled.html.twig
    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    // Which of the two columns a calculator is read first - the numbers or the controls that move them. Its own setting because the two collections' own ordering cannot express it: dragging an output only moves it among the outputs, and that order is the calculation's anyway, an expression seeing nothing but what sits above it. Read by Calculator.html.twig, which hands it to sass/_calculator.scss as a class
    #[ORM\Column(options: ['default' => false])]
    private bool $outputsFirst = false;

    #[ORM\OneToMany(mappedBy: 'form', targetEntity: FormField::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $fields;

    // Owning at least one turns this Form into a calculator (see isCalculator()) - the order matters, an expression only ever seeing the outputs declared before it
    #[ORM\OneToMany(mappedBy: 'form', targetEntity: FormOutput::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $outputs;

    public function __construct()
    {
        $this->fields = new ArrayCollection();
        $this->outputs = new ArrayCollection();
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): self
    {
        $this->action = $action;

        return $this;
    }

    public function getActionConfig(): ?array
    {
        return $this->actionConfig;
    }

    public function setActionConfig(?array $actionConfig): self
    {
        $this->actionConfig = $actionConfig;

        return $this;
    }

    // Virtual, not persisted - lets FormCrudController edit $actionConfig as raw JSON text instead of needing a dynamic per-action sub-form. "links" is left out: it has its own collection editor (see getLinks()), and showing it twice would let one editor silently undo the other
    public function getActionConfigJson(): ?string
    {
        $actionConfig = $this->actionConfig ?? [];
        unset($actionConfig['links']);

        return [] === $actionConfig ? null : json_encode($actionConfig, JSON_PRETTY_PRINT);
    }

    public function setActionConfigJson(?string $actionConfigJson): self
    {
        // Kept aside and put back below, the raw JSON never carrying it in the first place
        $links = $this->actionConfig['links'] ?? null;

        $decoded = null === $actionConfigJson || '' === trim($actionConfigJson) ? null : json_decode($actionConfigJson, true);
        $this->actionConfig = is_array($decoded) ? $decoded : null;

        if (null !== $links) {
            $this->actionConfig = array_merge($this->actionConfig ?? [], ['links' => $links]);
        }

        return $this;
    }

    /**
     * Virtual, not persisted - the links shown under the submit button (e.g. register's "already have an account?"),
     * stored in $actionConfig rather than in a column of their own, like "offerReceiveCopy" already is: both are read
     * by FormController/Form.html.twig, not by the FormActionInterface the rest of that config belongs to.
     *
     * @return list<array{label: string, url: string}>
     */
    public function getLinks(): array
    {
        return $this->actionConfig['links'] ?? [];
    }

    public function setLinks(array $links): self
    {
        // Only entries actually filled in - the collection's "+ Add" row submits an empty pair when left untouched
        $links = array_values(array_filter($links, static fn (mixed $link): bool => is_array($link)
            && '' !== trim((string) ($link['label'] ?? ''))
            && '' !== trim((string) ($link['url'] ?? ''))));

        $actionConfig = $this->actionConfig ?? [];
        if ([] === $links) {
            // Stored empty rather than dropped: that is what lets FormSeeder tell "emptied by the admin" from "never seeded" (it goes by the key being there), so an admin who clears the links keeps them cleared instead of having them seeded back on the next deploy
            $actionConfig['links'] = [];
        } else {
            $actionConfig['links'] = array_map(static fn (array $link): array => [
                'label' => trim((string) $link['label']),
                'url' => trim((string) $link['url']),
            ], $links);
        }

        // Never null out here: the branch above always sets the "links" key, empty or not, and it is that key's presence FormSeeder reads
        $this->actionConfig = $actionConfig;

        return $this;
    }

    public function isRestricted(): bool
    {
        return $this->restricted;
    }

    public function setRestricted(bool $restricted): self
    {
        $this->restricted = $restricted;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function isOutputsFirst(): bool
    {
        return $this->outputsFirst;
    }

    public function setOutputsFirst(bool $outputsFirst): self
    {
        $this->outputsFirst = $outputsFirst;

        return $this;
    }

    /** @return Collection<int, FormField> */
    public function getFields(): Collection
    {
        return $this->fields;
    }

    public function addField(FormField $field): self
    {
        if (!$this->fields->contains($field)) {
            $this->fields->add($field);
            $field->setForm($this);
        }

        return $this;
    }

    public function removeField(FormField $field): self
    {
        if ($this->fields->removeElement($field)) {
            if ($field->getForm() === $this) {
                $field->setForm(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, FormOutput> */
    public function getOutputs(): Collection
    {
        return $this->outputs;
    }

    public function addOutput(FormOutput $output): self
    {
        if (!$this->outputs->contains($output)) {
            $this->outputs->add($output);
            $output->setForm($this);
        }

        return $this;
    }

    public function removeOutput(FormOutput $output): self
    {
        if ($this->outputs->removeElement($output)) {
            if ($output->getForm() === $this) {
                $output->setForm(null);
            }
        }

        return $this;
    }

    // A calculator computes and displays, it never submits: no action to run, no rate limiter, no honeypot, no flash - see FormController, which renders it through the very same field types all the same, so it wears the site's form theme like any other
    public function isCalculator(): bool
    {
        return !$this->outputs->isEmpty();
    }

    // Only the outputs a visitor actually sees, an invisible one being an intermediate other expressions read
    /** @return list<FormOutput> */
    public function getVisibleOutputs(): array
    {
        return array_values(array_filter(
            $this->outputs->toArray(),
            static fn (FormOutput $output): bool => $output->isVisible()
        ));
    }
}
