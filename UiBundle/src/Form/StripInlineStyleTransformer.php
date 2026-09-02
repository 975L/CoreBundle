<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use Symfony\Component\Form\DataTransformerInterface;

// Editor content is pasted as often as it is typed, and a paste carries the source's style attributes - which the CSP blocks on every page rendering them, a style ATTRIBUTE being what a nonce can never authorize (style-src covers it, and hashes only apply with 'unsafe-hashes'). Trix re-applies them itself: its HTMLParser clones the <trix-editor>, inserts that clone in the document, then fills it with what DOMPurify let through - "style" included. Both directions are cleaned, and not only the way in: the editor must not be handed what it would then apply, and doing it in the browser instead is not an option, a DOMParser/<template> parse inheriting the page's CSP and raising the very violation it removes
class StripInlineStyleTransformer implements DataTransformerInterface
{
    // Content already stored is cleaned on its way to the editor, so nothing is applied on load - saving the form is then what takes the attributes out of the database
    public function transform(mixed $value): ?string
    {
        return $this->strip($value);
    }

    public function reverseTransform(mixed $value): ?string
    {
        return $this->strip($value);
    }

    private function strip(mixed $value): ?string
    {
        if (!\is_string($value) || !str_contains($value, 'style=')) {
            return $value;
        }

        // A fragment, so the parse is scoped to <body> - the tag is added here because createFromString() would otherwise put loose inline content in <head>
        $document = \Dom\HTMLDocument::createFromString('<body>' . $value . '</body>', LIBXML_NOERROR);
        foreach ($document->querySelectorAll('[style]') as $element) {
            $element->removeAttribute('style');
        }

        return $document->body->innerHTML;
    }
}
