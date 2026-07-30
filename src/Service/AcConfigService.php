<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Server;
use App\Exception\EmptyCarListException;
use App\Exception\MissingContainerSlugException;
use Symfony\Component\Filesystem\Exception\IOException;

interface AcConfigService
{
    /**
     * Creates the server's config directory and writes its acServer config files.
     *
     * @throws IOException
     * @throws MissingContainerSlugException
     * @throws EmptyCarListException
     */
    public function writeConfig(Server $server): void;

    /**
     * Removes the server's entire config directory.
     *
     * @throws IOException
     * @throws MissingContainerSlugException
     */
    public function deleteConfig(Server $server): void;

    /**
     * Absolute path to the server's config directory (`<acServersDir>/<slug>/cfg`).
     *
     * @throws MissingContainerSlugException when the server has no container slug yet
     */
    public function getConfigDir(Server $server): string;
}
