# CLAUDE.md

Guidance for working in this repository. Path-specific conventions live under `.claude/rules/`; treat both as authoritative and revise them in the same change that makes them stale — documentation must never describe a layout the code no longer has.

## What Pitlane Is

Pitlane is a Symfony web application for running and administering **Assetto Corsa dedicated servers**. Each game server is a Docker container built from `ac-server/Dockerfile`; Pitlane drives them through the Docker daemon (mounted `/var/run/docker.sock`) and presents a Twig UI to create, start, stop, and configure them.

Dependency versions are whatever `composer.json` declares — read it there rather than trusting numbers copied into docs.

## Stack

- **PHP** `>=8.4`, **Symfony 8**
- **PostgreSQL 16** with **Doctrine ORM**
- **Twig** views plus Symfony UX (Turbo, Stimulus) and AssetMapper for assets
- **Symfony Security** for authentication (form login, remember me)
- Server orchestration over the **Docker Engine API** via the mounted socket
- **Castor** as the task runner (`castor.php`)

## Everyday Commands

Task automation goes through Castor:

- `castor setup` — first-run bootstrap (build images, start services, build assets, load fixtures)
- `castor up` / `castor down` — start / tear down the dev stack
- `castor build` — rebuild the Docker images from scratch
- `castor tailwind` — watch and rebuild the Tailwind CSS during development
- `castor fixtures` — reset the database and load dev fixtures; `castor fixtures:append` loads without resetting
- `castor lint` — run CS Fixer, Rector and PHPStan (max) in check mode
- `castor lint:fix` — same, auto-fixing CS Fixer and Rector (PHPStan stays read-only)
- `castor phpstan` / `castor cs` / `castor rector` — run one tool on its own (each has a `:fix` variant where fixing applies)
- `castor phpunit` — full test run with the 100% coverage gate; `castor phpunit:no-coverage` skips the gate
- `castor infection` — mutation testing; `castor infection:diff` limits it to lines changed against `main`
- `bin/console <cmd>` — Symfony console

## Layout

- `src/Command/` — console commands, one job per class
- `src/Controller/` — HTTP entry points, kept thin (see `.claude/rules/controllers.md`)
- `src/Entity/` — Doctrine entities
- `src/Enum/` — backed enums (roles, statuses)
- `src/Repository/` — Doctrine repositories, behind interfaces
- `src/Security/` — authenticators, voters, hashers
- `templates/` — Twig views, laid out to follow the controllers
- `migrations/` — Doctrine migrations
- `tests/` — PHPUnit tests, one-to-one with `src/`
- `ac-server/` — Dockerfile for the managed game-server image

## Commits

Follow [Conventional Commits](https://www.conventionalcommits.org/): `<type>(scope): <subject>`. Types in use: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `build`, `ci`, `perf`. Typical scopes are `entity`, `security`, `deps`, `config`, `ci`. Flag breaking changes with `feat!:` and a `BREAKING CHANGE:` footer. Keep the issue reference (`Closes #6`) in the body, not the subject.

## CI Gates

Merging requires both jobs green:

- **Lint** — CS Fixer, Rector, PHPStan at max, and `doctrine:schema:validate`.
- **Tests** — PHPUnit with 100% line coverage and Infection at 100% MSI.

## How to Work Here

- **Decide out loud.** Name your assumptions and the tradeoffs before writing code; when a request reads more than one way, raise it instead of guessing.
- **Keep it small.** Ship the least code that solves the problem — no speculative hooks, no abstraction for something used once.
- **Stay in scope.** Change only what the task needs and match the surrounding style. Point out unrelated dead code rather than removing it; only drop symbols your own edit orphaned.
- **Work to a checkable goal.** Break multi-step work into steps each with a way to confirm it. Don't run the linters or test suite yourself — hand back the exact commands for the maintainer to run.
