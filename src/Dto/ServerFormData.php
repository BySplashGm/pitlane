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

    /**
     * Installed-content choice lists, injected by the controller from
     * {@see \App\Service\AcContentServiceInterface} before validation. The {@see Assert\Choice}
     * callbacks below validate the submitted values against these, so a forged POST cannot smuggle a
     * value outside the installed content into the generated INI config, whatever the UI offered.
     *
     * @var list<string>
     */
    public array $availableTracks = [];

    /**
     * @var list<string>
     */
    public array $availableCars = [];

    /**
     * @var list<string>
     */
    public array $availableWeatherGraphics = [];

    /**
     * Nullable because the track {@see \Symfony\Component\Form\Extension\Core\Type\ChoiceType}'s
     * placeholder maps an empty submit to null; NotBlank then reports it (Choice skips null).
     */
    #[Assert\NotBlank]
    #[Assert\Choice(callback: 'trackChoices')]
    public ?string $track = null;

    /**
     * Validated server-side by {@see \App\Form\ServerType}'s dependent {@see \Symfony\Component\Form\Extension\Core\Type\ChoiceType}
     * (its choices are the chosen track's layouts), so membership is enforced without the layout list
     * being known ahead of the submitted track.
     */
    public ?string $trackLayout = null;

    /**
     * A repeatable list: the same car may appear more than once (grid slots), and removing a row
     * leaves an index gap. {@see toServer()} re-indexes it before it reaches the entity.
     *
     * @var array<int, string>
     */
    #[Assert\Count(min: 1)]
    #[Assert\Choice(callback: 'carChoices', multiple: true)]
    public array $cars = [];

    #[IniSafeValue]
    public ?string $password = null;

    /**
     * On create the admin password is required. On edit the field is left blank to mean "keep the
     * current one", so the stored secret is never rendered back into the form; {@see $currentAdminPassword}
     * carries it for validation and {@see applyTo()}. Length and the "differ from the join password"
     * rule are enforced by {@see validateAdminPassword()} against the effective value.
     */
    #[IniSafeValue]
    public string $adminPassword = '';

    /**
     * The current admin password on edit, seeded by {@see fromServer()} and never added to the form, so
     * a blank admin-password field can keep the current one without emitting the secret to the browser.
     */
    public ?string $currentAdminPassword = null;

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

    /**
     * Nullable for the same reason as {@see $track}: an empty weather select maps to null.
     */
    #[Assert\NotBlank]
    #[Assert\Choice(callback: 'weatherGraphicsChoices')]
    public ?string $weatherGraphics = null;

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
     * Enforces the admin-password rules against the effective value (the submitted one, or the current
     * one kept when the edit form leaves the field blank): required on create, at least 8 characters
     * when a new one is set, and never equal to the join password. A join password is shared with every
     * driver, so reusing it as the admin password would hand admin rights to the whole grid; a blank
     * join password means the server is open, and is not compared.
     */
    #[Assert\Callback]
    public function validateAdminPassword(ExecutionContextInterface $executionContext): void
    {
        // On create the admin password is mandatory; on edit a blank field keeps the current one. The
        // rules below are independent guards, each a no-op on a blank field, so no early return is
        // needed once this one has fired.
        if ('' === $this->adminPassword && null === $this->serverId) {
            $executionContext->buildViolation('The admin password is required.')
                ->atPath('adminPassword')
                ->addViolation();
        }

        // A newly submitted admin password must be long enough (counted in characters, not bytes); a
        // kept one already is.
        if ('' !== $this->adminPassword && mb_strlen($this->adminPassword) < 8) {
            $executionContext->buildViolation('The admin password must be at least 8 characters long.')
                ->atPath('adminPassword')
                ->addViolation();
        }

        // The effective admin password (submitted, or kept on edit) must differ from a set join
        // password: it is shared with every driver, so a match would hand admin rights to the grid. An
        // open server has no join password to compare: a blank one is excluded by the '' !== check, a
        // null one by the equality, since a string admin password can never equal null.
        if ('' !== $this->password && $this->password === $this->effectiveAdminPassword()) {
            $executionContext->buildViolation('The admin password must differ from the join password.')
                ->atPath('adminPassword')
                ->addViolation();
        }
    }

    /**
     * The admin password that will be persisted: the submitted one, or the current one kept when the
     * edit form left the field blank.
     */
    public function effectiveAdminPassword(): string
    {
        return '' === $this->adminPassword ? ($this->currentAdminPassword ?? '') : $this->adminPassword;
    }

    /**
     * @return list<string>
     */
    public function trackChoices(): array
    {
        return $this->availableTracks;
    }

    /**
     * @return list<string>
     */
    public function carChoices(): array
    {
        return $this->availableCars;
    }

    /**
     * @return list<string>
     */
    public function weatherGraphicsChoices(): array
    {
        return $this->availableWeatherGraphics;
    }

    /**
     * Builds a pre-filled form model from an existing server, for the edit page. The server id is
     * carried in {@see $serverId} so the {@see ContainerSlug} uniqueness check excludes this row.
     */
    public static function fromServer(Server $server): self
    {
        $serverFormData = new self();

        $serverFormData->serverId = $server->getId();
        $serverFormData->name = $server->getName();
        $serverFormData->serverName = $server->getServerName();
        $serverFormData->track = $server->getTrack();
        $serverFormData->trackLayout = $server->getTrackLayout();
        $serverFormData->cars = $server->getCars();
        // The join password is shared with every driver, so it is pre-filled like any other field. The
        // admin password is a genuine secret: keep the field blank and carry the current value aside so
        // it is never rendered back into the form.
        $serverFormData->password = $server->getPassword();
        $serverFormData->currentAdminPassword = $server->getAdminPassword();
        $serverFormData->maxClients = $server->getMaxClients();
        $serverFormData->tcpPort = $server->getTcpPort();
        $serverFormData->udpPort = $server->getUdpPort();
        $serverFormData->httpPort = $server->getHttpPort();
        $serverFormData->sessionType = $server->getSessionType();
        $serverFormData->sessionDuration = $server->getSessionDuration();
        $serverFormData->durationUnit = $server->getDurationUnit();
        $serverFormData->weatherGraphics = $server->getWeatherGraphics();
        $serverFormData->ambientTemp = $server->getAmbientTemp();
        $serverFormData->trackTemp = $server->getTrackTemp();
        $serverFormData->dynamicTrack = $server->isDynamicTrack();
        $serverFormData->trackGrip = $server->getTrackGrip();
        $serverFormData->tcpNoDelay = $server->isTcpNoDelay();
        $serverFormData->registerToLobby = $server->isRegisterToLobby();

        return $serverFormData;
    }

    /**
     * Builds the entity from the validated form values.
     */
    public function toServer(): Server
    {
        return new Server(
            name: $this->name,
            serverName: $this->serverName,
            track: $this->track ?? '',
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
            weatherGraphics: $this->weatherGraphics ?? '',
            ambientTemp: $this->ambientTemp,
            trackTemp: $this->trackTemp,
            dynamicTrack: $this->dynamicTrack,
            trackGrip: $this->trackGrip,
            tcpNoDelay: $this->tcpNoDelay,
            registerToLobby: $this->registerToLobby,
        );
    }

    /**
     * Writes the validated form values back onto an existing server, so the edit page updates the
     * managed row in place rather than inserting a new one. The container slug is left untouched: it
     * is derived from the name only at creation, keeping the config directory and container stable.
     */
    public function applyTo(Server $server): void
    {
        $server
            ->setName($this->name)
            ->setServerName($this->serverName)
            ->setTrack($this->track ?? '')
            ->setTrackLayout($this->trackLayout)
            ->setCars(array_values($this->cars))
            ->setPassword($this->password ?? '')
            ->setAdminPassword($this->effectiveAdminPassword())
            ->setMaxClients($this->maxClients)
            ->setTcpPort($this->tcpPort)
            ->setUdpPort($this->udpPort)
            ->setHttpPort($this->httpPort)
            ->setSessionType($this->sessionType)
            ->setSessionDuration($this->sessionDuration)
            ->setDurationUnit($this->durationUnit)
            ->setWeatherGraphics($this->weatherGraphics ?? '')
            ->setAmbientTemp($this->ambientTemp)
            ->setTrackTemp($this->trackTemp)
            ->setDynamicTrack($this->dynamicTrack)
            ->setTrackGrip($this->trackGrip)
            ->setTcpNoDelay($this->tcpNoDelay)
            ->setRegisterToLobby($this->registerToLobby);
    }
}
