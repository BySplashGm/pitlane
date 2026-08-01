<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Server;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use App\Exception\EmptyCarListException;
use App\Exception\MissingContainerSlugException;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final readonly class AcConfigService implements AcConfigServiceInterface
{
    public function __construct(
        private Filesystem $filesystem,
        #[Autowire('%env(AC_SERVERS_DIR)%')]
        private string $acServersDir,
    ) {
    }

    /**
     * @throws IOException
     * @throws MissingContainerSlugException
     * @throws EmptyCarListException
     */
    #[Override]
    public function writeConfig(Server $server): void
    {
        $configDir = $this->getConfigDir($server);

        $this->filesystem->dumpFile(Path::join($configDir, 'server_cfg.ini'), $this->buildServerConfig($server));
        $this->filesystem->dumpFile(Path::join($configDir, 'entry_list.ini'), $this->buildEntryList($server));
    }

    /**
     * @throws IOException
     * @throws MissingContainerSlugException
     */
    #[Override]
    public function deleteConfig(Server $server): void
    {
        $this->filesystem->remove($this->getConfigDir($server));
    }

    /**
     * @throws MissingContainerSlugException when the server has no container slug yet
     */
    #[Override]
    public function getConfigDir(Server $server): string
    {
        $slug = $server->getContainerSlug();
        if ('' === $slug) {
            throw new MissingContainerSlugException();
        }

        return Path::join($this->acServersDir, $slug, 'cfg');
    }

    private function buildServerConfig(Server $server): string
    {
        $sessionSection = match ($server->getSessionType()) {
            SessionType::Practice => 'PRACTICE',
            SessionType::Qualify => 'QUALIFY',
            SessionType::Race => 'RACE',
        };

        $sessionName = match ($server->getSessionType()) {
            SessionType::Practice => 'Practice',
            SessionType::Qualify => 'Qualify',
            SessionType::Race => 'Race',
        };

        $durationKey = match ($server->getDurationUnit()) {
            DurationUnit::Minutes => 'TIME',
            DurationUnit::Laps => 'LAPS',
        };

        $sections = [
            $this->renderSection('SERVER', [
                'NAME' => $server->getServerName(),
                'CARS' => implode(';', $server->getCars()),
                'TRACK' => $server->getTrack(),
                'CONFIG_TRACK' => $server->getTrackLayout() ?? '',
                'PASSWORD' => $server->getPassword(),
                'ADMIN_PASSWORD' => $server->getAdminPassword(),
                'UDP_PORT' => $server->getUdpPort(),
                'TCP_PORT' => $server->getTcpPort(),
                'HTTP_PORT' => $server->getHttpPort(),
                'MAX_CLIENTS' => $server->getMaxClients(),
                'REGISTER_TO_LOBBY' => (int) $server->isRegisterToLobby(),
                'TCP_NODELAY' => (int) $server->isTcpNoDelay(),
            ]),
            $this->renderSection($sessionSection, [
                'NAME' => $sessionName,
                $durationKey => $server->getSessionDuration(),
                'IS_OPEN' => 1,
            ]),
            $this->renderSection('DYNAMIC_TRACK', [
                'SESSION_START' => $server->getTrackGrip(),
                'RANDOMNESS' => $server->isDynamicTrack() ? 2 : 0,
                'SESSION_TRANSFER' => $server->isDynamicTrack() ? 50 : 100,
                'LAP_GAIN' => $server->isDynamicTrack() ? 30 : 0,
            ]),
            $this->renderSection('WEATHER_0', [
                'GRAPHICS' => $server->getWeatherGraphics(),
                'BASE_TEMPERATURE_AMBIENT' => $server->getAmbientTemp(),
                'BASE_TEMPERATURE_ROAD' => $server->getTrackTemp() - $server->getAmbientTemp(),
            ]),
        ];

        return implode("\n\n", $sections)."\n";
    }

    /**
     * @throws EmptyCarListException
     */
    private function buildEntryList(Server $server): string
    {
        $cars = $server->getCars();
        $carCount = \count($cars);
        if (0 === $carCount) {
            throw new EmptyCarListException();
        }

        $sections = [];
        for ($slot = 0; $slot < $server->getMaxClients(); ++$slot) {
            $sections[] = $this->renderSection(\sprintf('CAR_%d', $slot), [
                'MODEL' => $cars[$slot % $carCount],
                'SKIN' => '',
                'SPECTATOR_MODE' => 0,
                'DRIVERNAME' => '',
                'TEAM' => '',
                'GUID' => '',
            ]);
        }

        return implode("\n\n", $sections)."\n";
    }

    /**
     * @param array<string, int|string> $entries
     */
    private function renderSection(string $title, array $entries): string
    {
        $lines = [\sprintf('[%s]', $title)];
        foreach ($entries as $key => $value) {
            $lines[] = \sprintf('%s=%s', $key, $value);
        }

        return implode("\n", $lines);
    }
}
