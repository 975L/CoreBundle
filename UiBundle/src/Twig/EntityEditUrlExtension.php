<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use Doctrine\Persistence\Proxy;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

// The way from an entity shown on the public site to its back-office form, for the pencil blockEditOverlay draws over a block. A cached fragment is the same for every visitor, so an url only ever lands outside it: on the wrapper of the entity (entity_edit_url, see components/Edit/Entity.html.twig), or as the pattern edit-pencils.js fills from the neutral marks the fragment carries (entity_edit_pattern, mounted by the layout). The CRUD is found by name rather than by a map: App\Entity\Resistant is edited by App\Controller\Management\ResistantCrudController, the convention every c975L bundle and site follows, so a new entity gets its pencil the day its CRUD is written
class EntityEditUrlExtension
{
    private const string ENTITY_SEGMENT = '\\Entity\\';

    private const string CRUD_SEGMENT = '\\Controller\\Management\\';

    private const string KIND = '__kind__';

    private const string ID = '__id__';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ?Security $security = null,
    ) {
    }

    // Null for anyone but an editor, and for an entity no CRUD edits: the template then writes no attribute at all
    #[AsTwigFunction('entity_edit_url')]
    public function editUrl(object $entity): ?string
    {
        if (!$this->canEdit() || !method_exists($entity, 'getId') || null === $entity->getId()) {
            return null;
        }

        // A lazily fetched entity is handed over as a proxy, whose class extends the entity's and names no CRUD
        $class = $entity instanceof Proxy ? (string) get_parent_class($entity) : $entity::class;
        $crudController = str_replace(self::ENTITY_SEGMENT, self::CRUD_SEGMENT, $class) . 'CrudController';

        if (!str_contains($class, self::ENTITY_SEGMENT) || !class_exists($crudController)) {
            return null;
        }

        return $this->generate($crudController, (string) $entity->getId());
    }

    // The edit url with "__kind__" and "__id__" left to fill, built off this bundle's own media CRUD so the dashboard's prefix is the router's and never restated here. Null for anyone but an editor, and on a dashboard without pretty urls, where the kind is no segment to swap
    #[AsTwigFunction('entity_edit_pattern')]
    public function editPattern(): ?string
    {
        if (!$this->canEdit()) {
            return null;
        }

        $url = $this->generate(MediaCrudController::class, self::ID);
        $segment = '/media/' . self::ID . '/';

        if (null === $url || !str_contains($url, $segment)) {
            return null;
        }

        return str_replace($segment, '/' . self::KIND . '/' . self::ID . '/', $url);
    }

    // The editor role, the one blocks answer to too (see components/Blocks/Blocks.html.twig), so a pencil on a block and on a fiche are shown to the same people
    private function canEdit(): bool
    {
        $role = (string) $this->configService->get('site-role-editor');

        return null !== $this->security && '' !== $role && $this->security->isGranted($role);
    }

    // EasyAdmin's route cache may be empty while the compiled routes are fresh, and losing the pencil beats a 500 on a public page - the same guard BlockEditUrlRegistry takes
    private function generate(string $crudController, string $id): ?string
    {
        try {
            return $this->adminUrlGenerator
                ->unsetAll()
                ->setController($crudController)
                ->setAction(Action::EDIT)
                ->setEntityId($id)
                ->generateUrl();
        } catch (\Throwable) {
            return null;
        }
    }
}
