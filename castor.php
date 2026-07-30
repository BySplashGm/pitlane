<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;

#[AsTask(name: 'build', description: 'Build the Docker images')]
function build(): void
{
    run('docker compose build --pull --no-cache');
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

#[AsTask(name: 'phpunit', description: 'Run PHPUnit tests with coverage check')]
function phpunit(): void
{
    runPhpunitTestsWithCoverageCheck();
}

#[AsTask(name: 'phpunit:no-coverage', description: 'Run PHPUnit tests without coverage check')]
function phpunitNoCoverage(): void
{
    runPhpunitTestsWithCoverageCheck(withCoverage: false);
}

#[AsTask(name: 'infection', description: 'Run Infection mutation testing on the whole project')]
function infection(): void
{
    runInfection();
}

#[AsTask(name: 'infection:diff', description: 'Run Infection mutation testing on changed lines only')]
function infectionDiff(string $target = 'main'): void
{
    runInfection(diff: true, target: $target);
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

function loadFixtures(bool $appendMode = false): void
{
    run('docker compose exec app bin/console foundry:load-fixtures'.($appendMode ? ' --append' : '').' --no-interaction');
}

function runPhpunitTestsWithCoverageCheck(bool $withCoverage = true): void
{
    $command = 'docker compose exec app vendor/bin/phpunit';
    if ($withCoverage) {
        $command .= ' --coverage-text --only-summary-for-coverage-text';
    }

    $process = run($command);
    if ($withCoverage && !preg_match('/Lines:\s+100\.00%/', $process->getOutput())) {
        io()->error('PHPUnit: Line coverage is below 100%.');
        exit(1);
    }
}

function runInfection(bool $diff = false, string $target = 'main'): void
{
    $command = 'docker compose exec app php -d memory_limit=1G bin/infection';

    if ($diff) {
        $command .= sprintf(' --git-diff-lines --git-diff-base=origin/%s', $target);
    }

    run($command);
}
