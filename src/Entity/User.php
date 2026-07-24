<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\DoctrineUserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: DoctrineUserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', fields: ['email'])]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private readonly string $email;

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 20, enumType: UserRole::class)]
    private readonly UserRole $userRole;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private readonly DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Server>
     */
    #[ORM\ManyToMany(targetEntity: Server::class)]
    #[ORM\JoinTable(name: 'user_server')]
    private Collection $assignedServers;

    public function __construct(string $email, UserRole $userRole)
    {
        $this->email = $email;
        $this->userRole = $userRole;
        $this->createdAt = new DateTimeImmutable();
        $this->assignedServers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    #[Override]
    public function getUserIdentifier(): string
    {
        // The email is validated as non-blank before persistence, but its type stays a plain string.
        return $this->email; // @phpstan-ignore return.type
    }

    #[Override]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getRole(): UserRole
    {
        return $this->userRole;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function getRoles(): array
    {
        return [$this->userRole->roleName(), 'ROLE_USER'];
    }

    /**
     * @return Collection<int, Server>
     */
    public function getAssignedServers(): Collection
    {
        return $this->assignedServers;
    }

    public function assignServer(Server $server): void
    {
        if (!$this->assignedServers->contains($server)) {
            $this->assignedServers->add($server);
        }
    }

    public function revokeServer(Server $server): void
    {
        $this->assignedServers->removeElement($server);
    }

    public function hasAccessTo(Server $server): bool
    {
        if (\in_array($this->userRole, [UserRole::Owner, UserRole::Admin], true)) {
            return true;
        }

        return $this->assignedServers->contains($server);
    }
}
