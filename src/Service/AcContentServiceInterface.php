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

/**
 * Enumerates the Assetto Corsa content installed under the configured content directory
 * (`AC_CONTENT_DIR`). It reads folder names only and never ships, copies or interprets game data,
 * so real content and non-copyright placeholders look identical to this code.
 *
 * The content directory is operator-managed: populated by a mounted volume in production and by a
 * few placeholder folders in local dev. A missing content type simply yields an empty list.
 */
interface AcContentServiceInterface
{
    /**
     * Installed car folder names, sorted.
     *
     * @return list<string>
     */
    public function cars(): array;

    /**
     * Installed track folder names, sorted.
     *
     * @return list<string>
     */
    public function tracks(): array;

    /**
     * Layout folder names installed under the given track, sorted. A track with no layout subfolders
     * yields an empty list. An unknown or unsafe track name also yields an empty list.
     *
     * @return list<string>
     */
    public function trackLayouts(string $track): array;

    /**
     * Installed weather folder names, sorted.
     *
     * @return list<string>
     */
    public function weather(): array;
}
