<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\ChoiceList;

use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

// The pages and sections a link field lists, plus whatever address it holds or is given that none of them is ("/shop", "https://…"): a link is never refused for not being in the list, and an address typed by hand shows under its own words when the field comes back
class LinkTargetChoiceLoader implements ChoiceLoaderInterface
{
    /** @var list<string> */
    private array $typed = [];

    /** @param array<string, string> $targets label => value */
    public function __construct(private readonly array $targets)
    {
    }

    public function loadChoiceList(?callable $value = null): ChoiceListInterface
    {
        $choices = $this->targets;
        foreach ($this->typed as $address) {
            if (!\in_array($address, $choices, true)) {
                $choices[$address] = $address;
            }
        }

        return new ArrayChoiceList($choices, $value);
    }

    public function loadChoicesForValues(array $values, ?callable $value = null): array
    {
        return $this->accept($values);
    }

    public function loadValuesForChoices(array $choices, ?callable $value = null): array
    {
        return $this->accept($choices);
    }

    // A value is its own choice, the empty one aside: nothing chosen stays nothing
    private function accept(array $values): array
    {
        $values = array_filter($values, static fn (mixed $value): bool => \is_string($value) && '' !== $value);
        $this->typed = array_values(array_unique([...$this->typed, ...$values]));

        return $values;
    }
}
