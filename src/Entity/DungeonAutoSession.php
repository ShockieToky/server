<?php

namespace App\Entity;

use App\Repository\DungeonAutoSessionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Session d'auto-ferme : le joueur "envoie" son équipe dans un donjon
 * pour une durée fixe et réclame les récompenses à la fin.
 *
 * Table MyISAM (même famille que les tables user_*) — pas de FK en base.
 */
#[ORM\Entity(repositoryClass: DungeonAutoSessionRepository::class)]
class DungeonAutoSession
{
    /** Durées autorisées en secondes. */
    public const DURATIONS = [600, 1800, 3600, 7200];

    /** Secondes par action de combat pour le calcul du nombre de runs. */
    public const SECONDS_PER_ACTION = 6;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Dungeon::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Dungeon $dungeon = null;

    /** Durée en secondes : 600 | 1800 | 3600 | 7200 */
    #[ORM\Column]
    private int $durationSeconds = 600;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    /** Nombre de runs complétés calculé au démarrage de la session. */
    #[ORM\Column]
    private int $completions = 0;

    /** Récompenses pré-calculées, encodées en JSON. NULL jusqu'au calcul. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rewardsJson = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isClaimed = false;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getEndsAt(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable(
            (new \DateTime())->setTimestamp($this->startedAt->getTimestamp() + $this->durationSeconds)
        );
    }

    public function hasEnded(): bool
    {
        return new \DateTimeImmutable() >= $this->getEndsAt();
    }

    public function getRemainingSeconds(): int
    {
        return max(0, $this->getEndsAt()->getTimestamp() - time());
    }

    // ── Getters / Setters ─────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getDungeon(): ?Dungeon { return $this->dungeon; }
    public function setDungeon(?Dungeon $dungeon): self { $this->dungeon = $dungeon; return $this; }

    public function getDurationSeconds(): int { return $this->durationSeconds; }
    public function setDurationSeconds(int $s): self { $this->durationSeconds = $s; return $this; }

    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }

    public function getCompletions(): int { return $this->completions; }
    public function setCompletions(int $c): self { $this->completions = $c; return $this; }

    public function getRewardsJson(): ?string { return $this->rewardsJson; }
    public function setRewardsJson(?string $json): self { $this->rewardsJson = $json; return $this; }

    public function isClaimed(): bool { return $this->isClaimed; }
    public function setIsClaimed(bool $b): self { $this->isClaimed = $b; return $this; }
}
