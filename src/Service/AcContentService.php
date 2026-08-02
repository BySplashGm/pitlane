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

namespace App\Service;

use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

final readonly class AcContentService implements AcContentServiceInterface
{
    public function __construct(
        private Filesystem $filesystem,
        #[Autowire('%env(AC_CONTENT_DIR)%')]
        private string $acContentDir,
    ) {
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function cars(): array
    {
        return $this->folderNames(Path::join($this->acContentDir, 'cars'));
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function tracks(): array
    {
        return $this->folderNames(Path::join($this->acContentDir, 'tracks'));
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function trackLayouts(string $track): array
    {
        $tracksDir = Path::join($this->acContentDir, 'tracks');
        $trackDir = Path::join($tracksDir, $track);

        // Reject an empty or path-escaping track name so a layout lookup cannot read outside the
        // tracks directory (isBasePath canonicalizes '..' away before comparing).
        if ('' === $track || !Path::isBasePath($tracksDir, $trackDir)) {
            return [];
        }

        return $this->folderNames($trackDir);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function weather(): array
    {
        return $this->folderNames(Path::join($this->acContentDir, 'weather'));
    }

    /**
     * Immediate subdirectory names of the given directory, sorted; empty when the directory is absent.
     *
     * @return list<string>
     */
    private function folderNames(string $directory): array
    {
        if (!$this->filesystem->exists($directory)) {
            return [];
        }

        $names = [];
        foreach (new Finder()->directories()->depth(0)->sortByName()->in($directory) as $finder) {
            $names[] = $finder->getFilename();
        }

        return $names;
    }
}
