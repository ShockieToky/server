<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    /**
     * Mot de passe hashé (par exemple bcrypt / sodium).
     */
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 20)]
    private ?string $role = 'player';

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $loginStreak = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $goldToken = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $historyToken = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $starterDone = false;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): self
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Retourne le hash du mot de passe stocké.
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Attend un mot de passe déjà hashé.
     */
    public function setPassword(string $hashedPassword): self
    {
        $this->password = $hashedPassword;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(\DateTimeInterface $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getLoginStreak(): int
    {
        return $this->loginStreak;
    }

    public function setLoginStreak(int $loginStreak): self
    {
        $this->loginStreak = $loginStreak;
        return $this;
    }

    public function getGoldToken(): int
    {
        return $this->goldToken;
    }

    public function setGoldToken(int $goldToken): self
    {
        $this->goldToken = $goldToken;
        return $this;
    }

    public function getHistoryToken(): int
    {
        return $this->historyToken;
    }

    public function setHistoryToken(int $historyToken): self
    {
        $this->historyToken = $historyToken;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function getRoles(): array
    {
        return ['ROLE_' . strtoupper($this->role ?? 'player')];
    }

    public function eraseCredentials(): void
    {
    }

    public function isStarterDone(): bool { return $this->starterDone; }
    public function setStarterDone(bool $v): self { $this->starterDone = $v; return $this; }
}
