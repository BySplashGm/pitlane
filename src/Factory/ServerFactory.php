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

namespace App\Factory;

use App\Entity\Server;
use App\Enum\DurationUnit;
use App\Enum\SessionType;
use Override;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Server>
 */
final class ServerFactory extends PersistentObjectFactory
{
    /**
     * @return class-string<Server>
     */
    #[Override]
    public static function class(): string
    {
        return Server::class;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function defaults(): array
    {
        return [
            'name' => 'Test Server',
            'serverName' => 'Pitlane Test Server',
            'track' => 'ks_nordschleife',
            'trackLayout' => null,
            'cars' => ['ks_ferrari_488_gt3'],
            'password' => '',
            'adminPassword' => 'admin-password',
            'maxClients' => 24,
            'tcpPort' => 9600,
            'udpPort' => 9700,
            'httpPort' => 8081,
            'sessionType' => SessionType::Race,
            'sessionDuration' => 20,
            'durationUnit' => DurationUnit::Minutes,
            'weatherGraphics' => '3_clear',
            'ambientTemp' => 26,
            'trackTemp' => 30,
            'dynamicTrack' => true,
            'trackGrip' => 95,
            'tcpNoDelay' => true,
            'registerToLobby' => false,
        ];
    }
}
