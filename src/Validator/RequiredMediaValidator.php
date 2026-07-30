<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

// Registry-aware, so it can't live as a plain #[Assert\Callback] on Block itself (entities have no DI) - same "ui" translation domain trick as Media::validateFixedIconMimeType
class RequiredMediaValidator extends ConstraintValidator
{
    public function __construct(private BlockRegistry $registry)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof Block) {
            return;
        }

        $kind = $value->getKind();
        if (null === $kind || !$this->registry->has($kind) || !$this->registry->isMediaRequired($kind)) {
            return;
        }

        if ($value->getMedias()->isEmpty()) {
            $this->context->buildViolation($constraint->message)
                ->atPath('medias')
                ->setTranslationDomain('ui')
                ->addViolation();
        }
    }
}
