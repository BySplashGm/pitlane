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

namespace App\Tests\Story;

use App\Story\AppStory;
use PHPUnit\Framework\TestCase;

final class AppStoryTest extends TestCase
{
    public function test_build_completes_without_error(): void
    {
        $appStory = new AppStory();

        $this->expectNotToPerformAssertions();

        $appStory->build();
    }
}
