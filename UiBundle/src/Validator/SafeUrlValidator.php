<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

// Reads an address the way a browser's parser does, or more strictly: an entity decoded, then every control, space and invisible character taken out, a browser ignoring them at the front of an address and tabs and newlines anywhere in it ("java\tscript:")
class SafeUrlValidator extends ConstraintValidator
{
    // The value a link field stores for a page or a route of the site, the one form a colon may take outside a scheme of the list (see SiteBundle's LinkTargetChoices)
    private const string INTERNAL_TARGET = '/^(?:page:\d+|route:[\w.\-]+)(?:\#[\w.\-]*)?$/';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof SafeUrl) {
            throw new UnexpectedTypeException($constraint, SafeUrl::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!$this->isSafe((string) $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }

    // No scheme at all is a relative address, whatever follows
    private function isSafe(string $url): bool
    {
        $readable = $this->readable($url);
        if (null === $readable) {
            return false;
        }

        if (1 !== preg_match('/^([a-z][a-z0-9+.\-]*):/', $readable, $matches)) {
            return true;
        }

        return \in_array($matches[1], SafeUrl::SCHEMES, true) || 1 === preg_match(self::INTERNAL_TARGET, $readable);
    }

    // Null on bytes that are no text at all, nothing a browser could be trusted to read as the validator did
    private function readable(string $url): ?string
    {
        $decoded = $url;
        do {
            $previous = $decoded;
            // A browser reads a numeric entity with no semicolon, which html_entity_decode() leaves as it is
            $decoded = html_entity_decode((string) preg_replace('/&#(x[0-9a-f]+|[0-9]+);?/i', '&#$1;', $previous), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } while ($decoded !== $previous);

        $readable = preg_replace('/[\p{C}\p{Z}]+/u', '', $decoded);

        return null === $readable ? null : strtolower($readable);
    }
}
