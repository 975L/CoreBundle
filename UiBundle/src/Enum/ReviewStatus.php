<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Where a review stands between having been written and being readable. Only Published is ever served to a visitor, which is what lets this bundle accept a review from anyone: what an anonymous form submits is held, never displayed, until someone says so
// An imported review is born Published - the platform it comes from moderated it already, and holding it back would show a site's own reviews as more recent than the ones it re-publishes
// Translatable rather than carrying a label() the screens would have to call: EasyAdmin reads that interface off the enum itself and offers the three cases translated, no choice list to keep in step with this one
enum ReviewStatus: string implements TranslatableInterface
{
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('label.review_status_' . $this->value, [], 'ui', $locale);
    }

    /**
     * The badge of a status as EasyAdmin hands it over, which is not always the case itself.
     *
     * Its badge selector is called with the choice as ChoiceConfigurator flattened it: the case name for an enum implementing TranslatableInterface - which this one does - its value otherwise, and the case itself when something else asks. All three are answered here rather than in the screen, which has no business knowing how the three are told apart.
     *
     * Anything else falls back on Pending: a badge is decoration, and a status nobody recognises is not worth a 500 on a screen whose whole job is to show what is waiting.
     */
    public static function badgeFor(mixed $value): string
    {
        if ($value instanceof self) {
            return $value->badge();
        }

        $name = \is_scalar($value) ? (string) $value : '';
        foreach (self::cases() as $case) {
            if ($case->value === $name || $case->name === $name) {
                return $case->badge();
            }
        }

        return self::Pending->badge();
    }

    // The bootstrap badge the moderation screen paints the status in - what is waiting has to be the one that catches the eye
    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Published => 'success',
            self::Rejected => 'secondary',
        };
    }
}
