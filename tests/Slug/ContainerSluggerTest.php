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

namespace App\Tests\Slug;

use App\Slug\ContainerSlugger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContainerSluggerTest extends TestCase
{
    #[DataProvider('names')]
    public function test_slugify_lowercases_and_asciifies_the_name(string $name, string $expected): void
    {
        self::assertSame($expected, ContainerSlugger::slugify($name));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'spaces and case' => ['Monza Cup', 'monza-cup'];
        yield 'already lower' => ['postgres', 'postgres'];
        yield 'uppercase reserved word' => ['Postgres', 'postgres'];
        yield 'symbols only slugify to empty' => ['!!!', ''];
        yield 'accents are asciified' => ['Nürburgring', 'nurburgring'];
    }
}
