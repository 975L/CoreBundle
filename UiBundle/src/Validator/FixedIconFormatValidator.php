<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\SvgRasterizer;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

// Roles with a fixed icon spec (favicon, apple-touch-icon) end up stored under their own .ico/.png name whatever was uploaded (see UiMediaNamer), the conversion happening after the fact in VichImageResizeListener - so anything that conversion can't handle has to be refused here, at the door, or it would be stored as SVG markup under an .ico name, i.e. a file no browser can read
class FixedIconFormatValidator extends ConstraintValidator
{
    // Read directly by GD, which the icon pipeline runs on
    private const RASTER_MIME_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

    // Both are met in the wild, the second one mostly from older tooling
    private const SVG_MIME_TYPES = ['image/svg+xml', 'image/svg'];

    // What the admin is told to upload instead, the raw mime types meaning nothing to them
    private const FORMAT_LABELS = 'PNG, JPG, GIF, WEBP';

    public function __construct(private SvgRasterizer $svgRasterizer)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof FixedIconFormat) {
            throw new UnexpectedTypeException($constraint, FixedIconFormat::class);
        }

        if (!$value instanceof Media || null === $value->getFixedIconSpec() || null === $value->getFile()) {
            return;
        }

        $mimeType = $value->getFile()->getMimeType();
        if (in_array($mimeType, self::RASTER_MIME_TYPES, true)) {
            return;
        }

        // An SVG is only ever accepted once actually rendered: ext-imagick may be missing altogether, and its SVG support chokes on a fair share of real-world markup even when present
        if (in_array($mimeType, self::SVG_MIME_TYPES, true) && $this->svgRasterizer->canRasterize($value->getFile()->getPathname())) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('%formats%', SvgRasterizer::isSupported() ? self::FORMAT_LABELS . ', SVG' : self::FORMAT_LABELS)
            ->atPath('file')
            ->setTranslationDomain('ui')
            ->addViolation();
    }
}
