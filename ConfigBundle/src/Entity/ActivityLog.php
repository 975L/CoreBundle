<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Entity;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Who did what and when in the back office, one row per entity written (see ActivityLogListener). No setter: a row never changes once written
#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: ActivityLog::TABLE)]
#[ORM\Index(name: 'idx_activity_log_occurred_at', columns: ['occurred_at'])]
class ActivityLog
{
    // Named here rather than in the repository too, as NotFound::TABLE
    public const string TABLE = 'site_activity_log';

    // What a row added by ActivityLogRecordCommand holds in place of a back-office path
    public const string PATH_CONSOLE = 'console';

    public const string ACTION_CREATE = 'create';
    public const string ACTION_UPDATE = 'update';
    public const string ACTION_DELETE = 'delete';
    public const string ACTION_TRASH = 'trash';
    public const string ACTION_RESTORE = 'restore';

    public const array ACTIONS = [
        self::ACTION_CREATE,
        self::ACTION_UPDATE,
        self::ACTION_DELETE,
        self::ACTION_TRASH,
        self::ACTION_RESTORE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @param array<string, bool|null> $changes */
    public function __construct(
        #[ORM\Column(name: 'occurred_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $occurredAt,
        // A relation and no copy of the name: an anonymized account reads anonymized here too, and a deleted one leaves nothing behind. Eager as every row of the list prints it
        #[ORM\ManyToOne(fetch: 'EAGER')]
        #[ORM\JoinColumn(onDelete: 'SET NULL')]
        private ?UserInterface $user,
        #[ORM\Column(length: 10)]
        #[Assert\Length(max: 10)]
        private string $action,
        // The short class name, eg. "Page"
        #[ORM\Column(name: 'entity_type', length: 100)]
        #[Assert\Length(max: 100)]
        private string $entityType,
        #[ORM\Column(name: 'entity_id', length: 64, nullable: true)]
        #[Assert\Length(max: 64)]
        private ?string $entityId,
        // What the entity was called at that moment, as it may be gone since. Null for an account or an #[ActivityLogUnnamed] entity, so no address or secret outlives them here
        #[ORM\Column(length: 255, nullable: true)]
        #[Assert\Length(max: 255)]
        private ?string $label,
        // The fields written, by name: no value is kept, so no secret or personal data lands here. A boolean field maps to its new value, which is the whole of what happened on "site-maintenance", and any other to null
        #[ORM\Column(type: Types::JSON)]
        private array $changes,
        // The back-office page the request was on: a block has no link to the page owning it, and "/management/page/12/edit" names it
        #[ORM\Column(length: 255)]
        #[Assert\Length(max: 255)]
        private string $path,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /** @return array<string, bool|null> */
    public function getChanges(): array
    {
        return $this->changes;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
