<?php

namespace App\Entity;

use App\Repository\TrainingSlotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingSlotRepository::class)]
class TrainingSlot
{
    public const TASK_UPGRADE_MODULES   = 'upgrade_modules';
    public const TASK_APPLY_EXTENSIONS  = 'apply_extensions';

    /** Duration in minutes for each module level upgrade */
    public const DURATION_LEVEL_1_TO_2     = 10;
    public const DURATION_LEVEL_2_TO_3     = 30;
    public const DURATION_APPLY_EXTENSIONS = 30;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserHero $userHero;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $slotIndex;

    #[ORM\Column(length: 30)]
    private string $taskType;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column]
    private \DateTimeImmutable $finishedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $claimedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $taskData = null;

    public function __construct(
        User $user,
        UserHero $userHero,
        int $slotIndex,
        string $taskType,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
    ) {
        $this->user       = $user;
        $this->userHero   = $userHero;
        $this->slotIndex  = $slotIndex;
        $this->taskType   = $taskType;
        $this->startedAt  = $startedAt;
        $this->finishedAt = $finishedAt;
    }

    public function getId(): ?int                          { return $this->id; }
    public function getUser(): User                        { return $this->user; }
    public function getUserHero(): UserHero                { return $this->userHero; }
    public function getSlotIndex(): int                    { return $this->slotIndex; }
    public function getTaskType(): string                  { return $this->taskType; }
    public function getStartedAt(): \DateTimeImmutable     { return $this->startedAt; }
    public function getFinishedAt(): \DateTimeImmutable    { return $this->finishedAt; }
    public function getClaimedAt(): ?\DateTimeImmutable    { return $this->claimedAt; }
    public function isClaimed(): bool                      { return $this->claimedAt !== null; }
    public function isReady(): bool                        { return new \DateTimeImmutable() >= $this->finishedAt; }
    public function claim(): void                          { $this->claimedAt = new \DateTimeImmutable(); }
    public function getTaskData(): ?array                  { return $this->taskData; }
    public function setTaskData(?array $data): static      { $this->taskData = $data; return $this; }
}
