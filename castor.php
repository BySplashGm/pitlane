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

use Castor\Attribute\AsArgsAfterOptionEnd;
use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;

#[AsTask(name: 'setup', description: 'First-run bootstrap: build images, start services, build assets, load fixtures')]
function setup(): void
{
    build();
    up();
    run('docker compose exec app bin/console tailwind:build');
    seedContent();
    fixtures();
}

#[AsTask(name: 'content:seed', description: 'Seed AC_CONTENT_DIR with non-copyright placeholder content for local dev')]
function contentSeed(): void
{
    seedContent();
}

#[AsTask(name: 'build', description: 'Build the Docker images')]
function build(): void
{
    run('docker compose build --pull --no-cache');

    // The managed game-server image (ac-server/Dockerfile) is not a compose service, so build it
    // separately; its tag must match DockerService::IMAGE ('ac-server:latest'). The Dockerfile COPYs
    // the proprietary Assetto Corsa dedicated-server files (acServer, system/, content/), which are
    // gitignored and supplied by hand. Skip with a notice when they are absent so the rest of the
    // bootstrap still works — starting a server needs this image.
    if (is_file('ac-server/acServer')) {
        run('docker build --pull -t ac-server:latest ac-server');
    } else {
        io()->warning('Skipping ac-server:latest build: place the Assetto Corsa dedicated-server files (acServer, system/, content/) in ac-server/, then run "castor build" again. Starting a server needs this image.');
    }
}

#[AsTask(name: 'up', description: 'Start the dev services (app, database, mailer)')]
function up(): void
{
    run('docker compose up --wait --build');
}

#[AsTask(name: 'down', description: 'Stop all containers and remove orphans')]
function down(): void
{
    run('docker compose down --remove-orphans');
}

#[AsTask(name: 'tailwind', description: 'Watch and rebuild the Tailwind CSS during development')]
function tailwind(): void
{
    run('docker compose exec app bin/console tailwind:build --watch');
}

#[AsTask(name: 'fixtures', description: 'Reset the database and load dev fixtures')]
function fixtures(): void
{
    loadFixtures();
}

#[AsTask(name: 'fixtures:append', description: 'Load dev fixtures without resetting the database')]
function fixturesAppend(): void
{
    loadFixtures(appendMode: true);
}

#[AsTask(name: 'lint', description: 'Check code style and analyze code')]
function lint(): void
{
    runCodeQualityTools();
}

#[AsTask(name: 'lint:fix', description: 'Fix code style and apply refactorings')]
function fix(): void
{
    runCodeQualityTools(fixMode: true);
}

#[AsTask(name: 'phpstan', description: 'Run PHPStan static analysis')]
function phpstan(): void
{
    runPhpStan();
}

#[AsTask(name: 'cs', description: 'Check code style with PHP-CS-Fixer')]
function cs(): void
{
    runCsFixer();
}

#[AsTask(name: 'cs:fix', description: 'Fix code style with PHP-CS-Fixer')]
function csFix(): void
{
    runCsFixer(fixMode: true);
}

#[AsTask(name: 'rector', description: 'Check refactorings with Rector')]
function rector(): void
{
    runRector();
}

#[AsTask(name: 'rector:fix', description: 'Apply refactorings with Rector')]
function rectorFix(): void
{
    runRector(fixMode: true);
}

/**
 * @param list<string> $phpunitArgs extra arguments forwarded to PHPUnit, e.g. `castor phpunit -- --filter Foo`
 */
#[AsTask(name: 'phpunit', description: 'Run PHPUnit tests with coverage check (pass extra args after --)')]
function phpunit(
    #[AsArgsAfterOptionEnd]
    array $phpunitArgs = [],
): void {
    // A filtered run cannot reach 100% line coverage, so the gate only makes sense on a full run.
    runPhpunitTestsWithCoverageCheck(withCoverage: [] === $phpunitArgs, extraArgs: $phpunitArgs);
}

/**
 * @param list<string> $phpunitArgs extra arguments forwarded to PHPUnit, e.g. `castor phpunit:no-coverage -- --filter Foo`
 */
#[AsTask(name: 'phpunit:no-coverage', description: 'Run PHPUnit tests without coverage check (pass extra args after --)')]
function phpunitNoCoverage(
    #[AsArgsAfterOptionEnd]
    array $phpunitArgs = [],
): void {
    runPhpunitTestsWithCoverageCheck(withCoverage: false, extraArgs: $phpunitArgs);
}

/**
 * @param list<string> $infectionArgs extra arguments forwarded to Infection, e.g. `castor infection -- --filter=src/Foo.php`
 */
#[AsTask(name: 'infection', description: 'Run Infection mutation testing on the whole project (pass extra args after --)')]
function infection(
    #[AsArgsAfterOptionEnd]
    array $infectionArgs = [],
): void {
    runInfection(extraArgs: $infectionArgs);
}

/**
 * @param list<string> $infectionArgs extra arguments forwarded to Infection, e.g. `castor infection:diff -- --filter=src/Foo.php`
 */
#[AsTask(name: 'infection:diff', description: 'Run Infection mutation testing on changed lines only (pass extra args after --)')]
function infectionDiff(
    string $target = 'main',
    #[AsArgsAfterOptionEnd]
    array $infectionArgs = [],
): void {
    runInfection(diff: true, target: $target, extraArgs: $infectionArgs);
}

#[AsTask(name: 'composer:update', description: 'Update composer dependencies')]
function composerUpdate(bool $lockUpdate = false): void
{
    runComposerUpdate(lockUpdate: $lockUpdate);
}

#[AsTask(name: 'composer:update-lock', description: 'Update composer.lock only')]
function composerUpdateLock(): void
{
    runComposerUpdate(lockUpdate: true);
}

function runComposerUpdate(bool $lockUpdate = false): void
{
    run('docker compose exec app composer update'.($lockUpdate ? ' --lock' : ''));
}

#[AsTask(name: 'composer:install', description: 'Install composer dependencies')]
function composerInstall(): void
{
    run('docker compose exec app composer install');
}

function runCodeQualityTools(bool $fixMode = false): void
{
    run('docker run --rm -v "$(pwd):/workdir" davidanson/markdownlint-cli2 "docs/**/*.md"');
    run('docker compose exec app composer normalize'.($fixMode ? '' : ' --dry-run'));
    run('docker compose exec app bin/console doctrine:schema:validate');
    runCsFixer($fixMode);
    runRector($fixMode);
    runPhpStan();
    runPhpunitTestsWithCoverageCheck();
    runInfection();

    io()->success($fixMode ? 'Fixing complete.' : 'Linting complete.');
}

function runPhpStan(): void
{
    run('docker compose exec app vendor/bin/phpstan analyze --memory-limit=500M');
}

function runCsFixer(bool $fixMode = false): void
{
    run('docker compose exec app vendor/bin/php-cs-fixer fix'.($fixMode ? '' : ' --dry-run --diff'));
}

function runRector(bool $fixMode = false): void
{
    run('docker compose exec app vendor/bin/rector process'.($fixMode ? '' : ' --dry-run'));
}

function seedContent(): void
{
    $contentDir = getenv('AC_CONTENT_DIR') ?: './var/ac-content';

    // Non-copyright placeholders: two cars, a single-layout track, a multi-layout track, two weathers.
    $placeholders = [
        'cars/test_car_a',
        'cars/test_car_b',
        'tracks/test_track_a',
        'tracks/test_track_b/test_layout_a',
        'tracks/test_track_b/test_layout_b',
        'weather/test_weather_clear',
        'weather/test_weather_rain',
    ];

    $paths = array_map(static fn (string $placeholder): string => escapeshellarg($contentDir.'/'.$placeholder), $placeholders);

    run('mkdir -p '.implode(' ', $paths));
    io()->success('Placeholder AC content seeded.');
}

function loadFixtures(bool $appendMode = false): void
{
    run('docker compose exec app bin/console foundry:load-fixtures'.($appendMode ? ' --append' : '').' --no-interaction');
}

/**
 * @param list<string> $extraArgs
 */
function runPhpunitTestsWithCoverageCheck(bool $withCoverage = true, array $extraArgs = []): void
{
    $command = 'docker compose exec app vendor/bin/phpunit';
    if ($withCoverage) {
        $command .= ' --coverage-text --only-summary-for-coverage-text';
    }

    $command .= extraArgsSuffix($extraArgs);

    $process = run($command);
    if ($withCoverage && !preg_match('/Lines:\s+100\.00%/', $process->getOutput())) {
        io()->error('PHPUnit: Line coverage is below 100%.');
        exit(1);
    }
}

/**
 * @param list<string> $extraArgs
 */
function runInfection(bool $diff = false, string $target = 'main', array $extraArgs = []): void
{
    $command = 'docker compose exec app php -d memory_limit=1G vendor/bin/infection';

    if ($diff) {
        $command .= sprintf(' --git-diff-lines --git-diff-base=origin/%s', $target);
    }

    $command .= extraArgsSuffix($extraArgs);

    run($command);
}

/**
 * Escapes forwarded CLI tokens so a filter value can never break out of the command string.
 *
 * @param list<string> $extraArgs
 */
function extraArgsSuffix(array $extraArgs): string
{
    if ([] === $extraArgs) {
        return '';
    }

    return ' '.implode(' ', array_map(escapeshellarg(...), $extraArgs));
}
