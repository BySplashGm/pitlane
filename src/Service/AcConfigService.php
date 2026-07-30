<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Server;
use Symfony\Component\Filesystem\Exception\IOException;

interface AcConfigService
{
    /**
     * Creates the server's config directory and writes its acServer config files.
     *
     * @throws IOException
     */
    public function writeConfig(Server $server): void;

    /**
     * Removes the server's entire config directory.
     *
     * @throws IOException
     */
    public function deleteConfig(Server $server): void;

    /**
     * Absolute path to the server's config directory (`<acServersDir>/<slug>/cfg`).
     */
    public function getConfigDir(Server $server): string;
}
