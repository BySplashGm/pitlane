<?php

declare(strict_types=1);

/*
 * This file is part of Pitlane.
 *
 * (c) Maxime Valin
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Repository\ServerRepository;
use App\Slug\ContainerSlugger;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServerRepository::class)]
#[ORM\Table(name: 'servers')]
#[ORM\UniqueConstraint(name: 'uniq_server_container_slug', fields: ['containerSlug'])]
#[ORM\UniqueConstraint(name: 'uniq_server_tcp_port', fields: ['tcpPort'])]
#[ORM\UniqueConstraint(name: 'uniq_server_udp_port', fields: ['udpPort'])]
#[ORM\UniqueConstraint(name: 'uniq_server_http_port', fields: ['httpPort'])]
#[ORM\HasLifecycleCallbacks]
final class Server
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $serverName;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $track;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $trackLayout;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\NotBlank()])]
    private array $cars;

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $adminPassword;

    #[ORM\Column]
    #[Assert\Range(min: 1, max: 64)]
    private int $maxClients;

    #[ORM\Column]
    #[Assert\Range(min: 1024, max: 65535)]
    private int $tcpPort;

    #[ORM\Column]
    #[Assert\Range(min: 1024, max: 65535)]
    private int $udpPort;

    #[ORM\Column]
    #[Assert\Range(min: 1024, max: 65535)]
    private int $httpPort;

    #[ORM\Column(length: 20, enumType: SessionType::class)]
    private SessionType $sessionType;

    #[ORM\Column]
    #[Assert\Positive]
    private int $sessionDuration;

    #[ORM\Column(length: 20, enumType: DurationUnit::class)]
    private DurationUnit $durationUnit;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $weatherGraphics;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 40)]
    private int $ambientTemp;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 55)]
    private int $trackTemp;

    #[ORM\Column]
    private bool $dynamicTrack;

    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100)]
    private int $trackGrip;

    #[ORM\Column]
    private bool $tcpNoDelay;

    #[ORM\Column]
    private bool $registerToLobby;

    #[ORM\Column(length: 255)]
    private string $containerSlug = '';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /**
     * @param list<string> $cars
     */
    public function __construct(
        string $name,
        string $serverName,
        string $track,
        ?string $trackLayout,
        array $cars,
        string $password,
        string $adminPassword,
        int $maxClients,
        int $tcpPort,
        int $udpPort,
        int $httpPort,
        SessionType $sessionType,
        int $sessionDuration,
        DurationUnit $durationUnit,
        string $weatherGraphics,
        int $ambientTemp,
        int $trackTemp,
        bool $dynamicTrack,
        int $trackGrip,
        bool $tcpNoDelay,
        bool $registerToLobby,
    ) {
        $this->name = $name;
        $this->serverName = $serverName;
        $this->track = $track;
        $this->trackLayout = $trackLayout;
        $this->cars = $cars;
        $this->password = $password;
        $this->adminPassword = $adminPassword;
        $this->maxClients = $maxClients;
        $this->tcpPort = $tcpPort;
        $this->udpPort = $udpPort;
        $this->httpPort = $httpPort;
        $this->sessionType = $sessionType;
        $this->sessionDuration = $sessionDuration;
        $this->durationUnit = $durationUnit;
        $this->weatherGraphics = $weatherGraphics;
        $this->ambientTemp = $ambientTemp;
        $this->trackTemp = $trackTemp;
        $this->dynamicTrack = $dynamicTrack;
        $this->trackGrip = $trackGrip;
        $this->tcpNoDelay = $tcpNoDelay;
        $this->registerToLobby = $registerToLobby;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getServerName(): string
    {
        return $this->serverName;
    }

    public function setServerName(string $serverName): static
    {
        $this->serverName = $serverName;

        return $this;
    }

    public function getTrack(): string
    {
        return $this->track;
    }

    public function setTrack(string $track): static
    {
        $this->track = $track;

        return $this;
    }

    public function getTrackLayout(): ?string
    {
        return $this->trackLayout;
    }

    public function setTrackLayout(?string $trackLayout): static
    {
        $this->trackLayout = $trackLayout;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCars(): array
    {
        return $this->cars;
    }

    /**
     * @param list<string> $cars
     */
    public function setCars(array $cars): static
    {
        $this->cars = $cars;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getAdminPassword(): string
    {
        return $this->adminPassword;
    }

    public function setAdminPassword(string $adminPassword): static
    {
        $this->adminPassword = $adminPassword;

        return $this;
    }

    public function getMaxClients(): int
    {
        return $this->maxClients;
    }

    public function setMaxClients(int $maxClients): static
    {
        $this->maxClients = $maxClients;

        return $this;
    }

    public function getTcpPort(): int
    {
        return $this->tcpPort;
    }

    public function setTcpPort(int $tcpPort): static
    {
        $this->tcpPort = $tcpPort;

        return $this;
    }

    public function getUdpPort(): int
    {
        return $this->udpPort;
    }

    public function setUdpPort(int $udpPort): static
    {
        $this->udpPort = $udpPort;

        return $this;
    }

    public function getHttpPort(): int
    {
        return $this->httpPort;
    }

    public function setHttpPort(int $httpPort): static
    {
        $this->httpPort = $httpPort;

        return $this;
    }

    public function getSessionType(): SessionType
    {
        return $this->sessionType;
    }

    public function setSessionType(SessionType $sessionType): static
    {
        $this->sessionType = $sessionType;

        return $this;
    }

    public function getSessionDuration(): int
    {
        return $this->sessionDuration;
    }

    public function setSessionDuration(int $sessionDuration): static
    {
        $this->sessionDuration = $sessionDuration;

        return $this;
    }

    public function getDurationUnit(): DurationUnit
    {
        return $this->durationUnit;
    }

    public function setDurationUnit(DurationUnit $durationUnit): static
    {
        $this->durationUnit = $durationUnit;

        return $this;
    }

    public function getWeatherGraphics(): string
    {
        return $this->weatherGraphics;
    }

    public function setWeatherGraphics(string $weatherGraphics): static
    {
        $this->weatherGraphics = $weatherGraphics;

        return $this;
    }

    public function getAmbientTemp(): int
    {
        return $this->ambientTemp;
    }

    public function setAmbientTemp(int $ambientTemp): static
    {
        $this->ambientTemp = $ambientTemp;

        return $this;
    }

    public function getTrackTemp(): int
    {
        return $this->trackTemp;
    }

    public function setTrackTemp(int $trackTemp): static
    {
        $this->trackTemp = $trackTemp;

        return $this;
    }

    public function isDynamicTrack(): bool
    {
        return $this->dynamicTrack;
    }

    public function setDynamicTrack(bool $dynamicTrack): static
    {
        $this->dynamicTrack = $dynamicTrack;

        return $this;
    }

    public function getTrackGrip(): int
    {
        return $this->trackGrip;
    }

    public function setTrackGrip(int $trackGrip): static
    {
        $this->trackGrip = $trackGrip;

        return $this;
    }

    public function isTcpNoDelay(): bool
    {
        return $this->tcpNoDelay;
    }

    public function setTcpNoDelay(bool $tcpNoDelay): static
    {
        $this->tcpNoDelay = $tcpNoDelay;

        return $this;
    }

    public function isRegisterToLobby(): bool
    {
        return $this->registerToLobby;
    }

    public function setRegisterToLobby(bool $registerToLobby): static
    {
        $this->registerToLobby = $registerToLobby;

        return $this;
    }

    public function getContainerSlug(): string
    {
        return $this->containerSlug;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function generateContainerSlug(): void
    {
        $this->containerSlug = ContainerSlugger::slugify($this->name);
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
