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

namespace App\Slug;

use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Derives the Docker container slug from a server name. Single source of truth so the entity that
 * persists the slug and the validator that checks its uniqueness never disagree on the value.
 */
final class ContainerSlugger
{
    public static function slugify(string $name): string
    {
        return new AsciiSlugger()->slug($name)->lower()->toString();
    }
}
