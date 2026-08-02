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

namespace App\Tests\Service;

use App\Service\AcContentService;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

final class AcContentServiceTest extends TestCase
{
    private Filesystem $filesystem;

    private string $contentDir;

    private AcContentService $acContentService;

    #[Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->contentDir = Path::join(sys_get_temp_dir(), uniqid('pitlane-ac-content-', true));
        $this->acContentService = new AcContentService($this->filesystem, $this->contentDir);

        // Created out of alphabetical order so the sorted output proves the service sorts. The nested
        // folder under a car proves the enumeration stays at depth 0 (a car, not its inner folder).
        $this->filesystem->mkdir(Path::join($this->contentDir, 'cars', 'test_car_b', 'ui'));
        $this->filesystem->mkdir(Path::join($this->contentDir, 'cars', 'test_car_a'));

        $this->filesystem->mkdir(Path::join($this->contentDir, 'tracks', 'test_track_multi', 'test_layout_b'));
        $this->filesystem->mkdir(Path::join($this->contentDir, 'tracks', 'test_track_multi', 'test_layout_a'));
        $this->filesystem->mkdir(Path::join($this->contentDir, 'tracks', 'test_track_single'));

        $this->filesystem->mkdir(Path::join($this->contentDir, 'weather', 'test_weather_b'));
        $this->filesystem->mkdir(Path::join($this->contentDir, 'weather', 'test_weather_a'));
    }

    public function test_cars_lists_the_installed_car_folders_sorted(): void
    {
        self::assertSame(['test_car_a', 'test_car_b'], $this->acContentService->cars());
    }

    public function test_tracks_lists_the_installed_track_folders_sorted(): void
    {
        self::assertSame(['test_track_multi', 'test_track_single'], $this->acContentService->tracks());
    }

    public function test_weather_lists_the_installed_weather_folders_sorted(): void
    {
        self::assertSame(['test_weather_a', 'test_weather_b'], $this->acContentService->weather());
    }

    public function test_track_layouts_lists_the_layout_subfolders_sorted(): void
    {
        self::assertSame(['test_layout_a', 'test_layout_b'], $this->acContentService->trackLayouts('test_track_multi'));
    }

    public function test_a_track_without_layout_subfolders_yields_an_empty_list(): void
    {
        self::assertSame([], $this->acContentService->trackLayouts('test_track_single'));
    }

    public function test_track_layouts_rejects_an_empty_track_name(): void
    {
        self::assertSame([], $this->acContentService->trackLayouts(''));
    }

    public function test_track_layouts_rejects_a_path_escaping_track_name(): void
    {
        // Resolves to <content>/cars, which exists and holds folders; the containment guard still
        // returns an empty list rather than leaking the car folders as if they were layouts.
        self::assertSame([], $this->acContentService->trackLayouts('../cars'));
    }

    public function test_a_missing_content_directory_yields_empty_lists(): void
    {
        $missingDir = Path::join(sys_get_temp_dir(), uniqid('pitlane-ac-content-missing-', true));
        $acContentService = new AcContentService($this->filesystem, $missingDir);

        self::assertSame([], $acContentService->cars());
        self::assertSame([], $acContentService->tracks());
        self::assertSame([], $acContentService->weather());
        self::assertSame([], $acContentService->trackLayouts('test_track_multi'));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->contentDir);
    }
}
