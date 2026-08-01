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

namespace App\Dto;

use App\Entity\Server;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mutable form model for creating a {@see Server}.
 *
 * The entity has a required-argument constructor and non-null typed columns, so it cannot back
 * the form directly: an invalid submit would push nulls into typed setters before validation runs.
 * This DTO carries the submitted values, gets validated in place, then builds the entity.
 */
final class ServerFormData
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $serverName = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $track = '';

    #[Assert\Length(max: 255)]
    public ?string $trackLayout = null;

    /**
     * Numeric-keyed rather than a list: the cars collection leaves index gaps when blank rows
     * are pruned. {@see toServer()} re-indexes it before it reaches the entity.
     *
     * @var array<int, string>
     */
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\NotBlank()])]
    public array $cars = [];

    public ?string $password = null;

    #[Assert\NotBlank]
    public string $adminPassword = '';

    #[Assert\Range(min: 1, max: 24)]
    public int $maxClients = 12;

    #[Assert\Range(min: 1024, max: 65535)]
    public int $tcpPort = 0;

    #[Assert\Range(min: 1024, max: 65535)]
    public int $udpPort = 0;

    #[Assert\Range(min: 1024, max: 65535)]
    public int $httpPort = 0;

    public SessionType $sessionType = SessionType::Race;

    #[Assert\Positive]
    public int $sessionDuration = 15;

    public DurationUnit $durationUnit = DurationUnit::Minutes;

    #[Assert\NotBlank]
    public string $weatherGraphics = '';

    #[Assert\Range(min: 0, max: 40)]
    public int $ambientTemp = 20;

    #[Assert\Range(min: 0, max: 55)]
    public int $trackTemp = 26;

    public bool $dynamicTrack = false;

    #[Assert\Range(min: 0, max: 100)]
    public int $trackGrip = 100;

    public bool $tcpNoDelay = false;

    public bool $registerToLobby = false;

    /**
     * Builds the entity from the validated form values.
     */
    public function toServer(): Server
    {
        return new Server(
            name: $this->name,
            serverName: $this->serverName,
            track: $this->track,
            trackLayout: $this->trackLayout,
            cars: array_values($this->cars),
            password: $this->password ?? '',
            adminPassword: $this->adminPassword,
            maxClients: $this->maxClients,
            tcpPort: $this->tcpPort,
            udpPort: $this->udpPort,
            httpPort: $this->httpPort,
            sessionType: $this->sessionType,
            sessionDuration: $this->sessionDuration,
            durationUnit: $this->durationUnit,
            weatherGraphics: $this->weatherGraphics,
            ambientTemp: $this->ambientTemp,
            trackTemp: $this->trackTemp,
            dynamicTrack: $this->dynamicTrack,
            trackGrip: $this->trackGrip,
            tcpNoDelay: $this->tcpNoDelay,
            registerToLobby: $this->registerToLobby,
        );
    }
}
