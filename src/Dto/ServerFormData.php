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
use App\Port\ReservedPorts;
use App\Validator\ContainerSlug;
use App\Validator\IniSafeValue;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Mutable form model for creating a {@see Server}.
 *
 * The entity has a required-argument constructor and non-null typed columns, so it cannot back
 * the form directly: an invalid submit would push nulls into typed setters before validation runs.
 * This DTO carries the submitted values, gets validated in place, then builds the entity.
 */
final class ServerFormData
{
    /**
     * The id of the server being edited, or null when creating. It excludes the server's own row from
     * the {@see ContainerSlug} uniqueness check so re-saving an unchanged name is not a false clash.
     */
    public ?int $serverId = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ContainerSlug]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[IniSafeValue]
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

    #[IniSafeValue]
    public ?string $password = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, minMessage: 'The admin password must be at least {{ limit }} characters long.')]
    #[IniSafeValue]
    public string $adminPassword = '';

    #[Assert\Range(min: 1, max: 64)]
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

    public bool $tcpNoDelay = true;

    public bool $registerToLobby = true;

    /**
     * Rejects reserved host ports on any of the three fields, and an HTTP port equal to the TCP port
     * (both are TCP, so the same number cannot serve both). UDP may share a number with either, as a
     * different transport protocol.
     */
    #[Assert\Callback]
    public function validatePorts(ExecutionContextInterface $executionContext): void
    {
        foreach (['tcpPort' => $this->tcpPort, 'udpPort' => $this->udpPort, 'httpPort' => $this->httpPort] as $field => $port) {
            if (ReservedPorts::contains($port)) {
                $executionContext->buildViolation('This port is reserved by the platform and cannot be used.')
                    ->atPath($field)
                    ->addViolation();
            }
        }

        if ($this->httpPort === $this->tcpPort) {
            $executionContext->buildViolation('The HTTP port must differ from the TCP port.')
                ->atPath('httpPort')
                ->addViolation();
        }
    }

    /**
     * Rejects an admin password equal to the join password: a join password is shared with every
     * driver, so reusing it as the admin password would hand admin rights to the whole grid. A blank
     * join password means the server is open, and is not compared.
     */
    #[Assert\Callback]
    public function validateAdminPassword(ExecutionContextInterface $executionContext): void
    {
        if (null !== $this->password && '' !== $this->password && $this->password === $this->adminPassword) {
            $executionContext->buildViolation('The admin password must differ from the join password.')
                ->atPath('adminPassword')
                ->addViolation();
        }
    }

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
