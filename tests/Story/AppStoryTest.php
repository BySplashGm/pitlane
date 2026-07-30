<?php

declare(strict_types=1);

namespace App\Tests\Story;

use App\Story\AppStory;
use PHPUnit\Framework\TestCase;

final class AppStoryTest extends TestCase
{
    public function test_build_completes_without_error(): void
    {
        $story = new AppStory();

        $this->expectNotToPerformAssertions();

        $story->build();
    }
}
