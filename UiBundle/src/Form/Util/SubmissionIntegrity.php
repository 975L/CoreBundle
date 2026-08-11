<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Util;

/**
 * Whether a submission arrived complete.
 *
 * PHP stops parsing a request body once it has read `max_input_vars` variables (1000 by default) and drops
 * everything past that point - no warning, no error, no entry in any log. The submission simply arrives short,
 * and every collection whose fields fell past the cut looks emptied rather than absent.
 *
 * That distinction is the whole point: an HTML form cannot represent an empty array, only an absent key, so
 * both BlockType and SiteBundle's PageCrudController read an absent collection as "the editor removed the last
 * entry" and delete the rows for good - cascaded, orphan-removed, irreversible. A truncated submission walks
 * into exactly that path. A page holding a container full of cards is the shape that reaches the limit first:
 * the whole page is one form, every block, every nested slot and every media in a single POST.
 *
 * Reported rather than worked around: nothing here can recover the fields PHP never parsed, so the only safe
 * answer is to refuse the submission and say so. Raising max_input_vars in php.ini is the actual fix.
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
final class SubmissionIntegrity
{
    /**
     * A submission is read as truncated as soon as it holds as many variables as PHP would accept: at the limit
     * exactly, a complete body and a body cut at its last accepted variable are indistinguishable. Erring toward
     * refusing a rare complete submission is deliberate - the other way round deletes content with no trace.
     *
     * @param array<mixed> $submitted the whole request body, not one field's own sub-array
     */
    public static function isTruncated(array $submitted, ?int $limit = null): bool
    {
        $limit ??= (int) ini_get('max_input_vars');

        // Unlimited, or an ini PHP could not read: nothing to compare against
        if ($limit <= 0) {
            return false;
        }

        return self::countVariables($submitted, $limit) >= $limit;
    }

    // Counts the leaves PHP itself counted while parsing, stopping at the limit rather than walking a whole deeply nested body to answer a question already settled
    private static function countVariables(array $values, int $stopAt): int
    {
        $count = 0;

        foreach ($values as $value) {
            $count += is_array($value) ? self::countVariables($value, $stopAt - $count) : 1;

            if ($count >= $stopAt) {
                return $count;
            }
        }

        return $count;
    }
}
