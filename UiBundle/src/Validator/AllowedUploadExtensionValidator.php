<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator;

use c975L\UiBundle\Listener\SvgSanitizeListener;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

// Refuses at the door what UiMediaNamer would otherwise store as a harmless ".bin", so the editor is told rather than left with a file nobody can open. A name with no extension at all goes through: a phone's picker sends some, and UiMediaNamer names those from their content
class AllowedUploadExtensionValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AllowedUploadExtension) {
            throw new UnexpectedTypeException($constraint, AllowedUploadExtension::class);
        }

        if (!$value instanceof File) {
            return;
        }

        if (!self::isAllowed($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('%extension%', self::shownExtension($value))
                ->addViolation();

            return;
        }

        // An SVG SvgSanitizeListener could not clean is told here, rather than refused by an exception once uploaded
        if (self::isSvg($value) && null === SvgSanitizeListener::sanitize((string) file_get_contents($value->getPathname()))) {
            $this->context->buildViolation($constraint->svgMessage)->addViolation();
        }
    }

    // An SVG by the name the browser sent or by its bytes
    private static function isSvg(File $file): bool
    {
        return 'svg' === self::extension($file) || str_contains((string) $file->getMimeType(), 'svg');
    }

    // What the message names: the type read off the bytes when that is what is refused, so an avif named "photo.jpg" is not told ".jpg" is refused
    private static function shownExtension(File $file): string
    {
        $mimeType = (string) $file->getMimeType();

        return self::hasRefusedType($file) ? substr($mimeType, (int) strpos($mimeType, '/') + 1) : self::extension($file);
    }

    // Whether a file's bytes are of a type no site takes in anymore, whatever it is named: asked by a form here, and by an import, which reaches no form
    public static function hasRefusedType(File $file): bool
    {
        return \in_array($file->getMimeType(), AllowedUploadExtension::REFUSED_MIME_TYPES, true);
    }

    // Its name on the list, or no extension at all, and bytes of no refused type
    private static function isAllowed(File $file): bool
    {
        $extension = self::extension($file);

        return ('' === $extension || \in_array($extension, AllowedUploadExtension::EXTENSIONS, true)) && !self::hasRefusedType($file);
    }

    // The name the browser sent for an upload, the file's own otherwise
    private static function extension(File $file): string
    {
        return strtolower($file instanceof UploadedFile ? $file->getClientOriginalExtension() : $file->getExtension());
    }
}
