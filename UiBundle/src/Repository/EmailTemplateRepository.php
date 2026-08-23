<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Repository;

use c975L\UiBundle\Entity\EmailTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailTemplate>
 */
class EmailTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailTemplate::class);
    }

    /**
     * The version of that e-mail to send to somebody being written to in that language.
     *
     * Three tries rather than one: the language asked for, the site's own, then whichever version exists. A
     * customer who ordered in a language the shop has since stopped writing that e-mail in still gets the e-mail,
     * in another language - which is worth more to them than the silence of a template nobody wrote.
     */
    public function findForRendering(string $name, ?string $locale, string $defaultLocale): ?EmailTemplate
    {
        foreach (array_unique(array_filter([$locale, $defaultLocale])) as $wanted) {
            $emailTemplate = $this->findOneBy(['name' => $name, 'locale' => $wanted]);
            if (null !== $emailTemplate) {
                return $emailTemplate;
            }
        }

        return $this->findOneBy(['name' => $name], ['locale' => 'ASC']);
    }
}
