# Fixtures

Dev fixtures run on **Zenstruck Foundry**. `castor fixtures` (reset + load) and `castor fixtures:append` (load only) both call `bin/console foundry:load-fixtures`, which builds every `#[AsFixture]` story.

## Layout

- **Factories** live in `src/Factory/`, one per entity (`UserFactory`, `ServerFactory`). Each extends `PersistentObjectFactory`, carries the `@extends PersistentObjectFactory<Entity>` annotation, and is `final`.
- **Stories** live in `src/Story/`. `AppStory` (`#[AsFixture(name: 'main')]`) is the entry point that composes the local dataset.
- Because they sit under `src/`, factories and stories are covered by the **100% coverage and 100% MSI gates** like any other production code — every one gets a mirror test under `tests/Factory/` or `tests/Story/`.

## Deterministic Defaults

Factory `defaults()` return **fixed literals, never Faker random values**. A random default (`numberBetween`, `randomElement`, …) spawns Infection mutants on its bounds that no assertion can kill, which breaks the MSI gate. Give one valid, asserted value per field; let the story override what must vary (unique names, ports).

- The factory test asserts every default through the entity getters, so each literal has a mutant-killing assertion.
- Unique columns (server ports, container slug) are made unique in the **story**, via a `createSequence(...)` that derives distinct values per row — not in the factory default.

## Passwords

`UserFactory` injects `UserPasswordHasherInterface` and hashes the password in `afterInstantiate`; the plaintext is the `UserFactory::DEFAULT_PASSWORD` constant. Never store a plaintext password on the entity. The reduced-cost hasher wired under `when@test`/`when@dev` keeps this fast.

## Story Tests

A story test is a `KernelTestCase` using Foundry's `Zenstruck\Foundry\Test\Factories` trait (no `ResetDatabase` trait — DAMA DoctrineTestBundle already rolls back each test). Run `build()`, then `EntityManager::clear()` and re-read from the database so a dropped `flush()` or a missing assignment fails the assertions rather than passing off in-memory state.

## Local Accounts

`AppStory` seeds four sign-in accounts, all with password `password`:

- `owner@pitlane.local` — `owner`, assigned every seeded server
- `admin@pitlane.local` — `admin`
- `operator1@pitlane.local` — `operator`, assigned the first half of the servers
- `operator2@pitlane.local` — `operator`, assigned the second half

These credentials are dev-only fixtures; keep them out of any non-dev environment.
