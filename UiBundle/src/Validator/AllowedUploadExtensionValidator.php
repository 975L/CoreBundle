<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator;

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

        $extension = strtolower($value instanceof UploadedFile ? $value->getClientOriginalExtension() : $value->getExtension());
        if ('' === $extension || \in_array($extension, AllowedUploadExtension::EXTENSIONS, true)) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('%extension%', $extension)
            ->addViolation();
    }
}
