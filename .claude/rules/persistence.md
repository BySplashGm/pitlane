---
description: Doctrine conventions — entities, interface-backed repositories, migrations
globs: ["src/Entity/**", "src/Repository/**", "migrations/**"]
alwaysApply: false
---

# Persistence

PostgreSQL 16 through Doctrine ORM. The `phpstan-doctrine` extension is on, so mapping generics are analysed.

## Entities

- Map with PHP attributes (`#[ORM\Entity]`, `#[ORM\Column]`) — not XML or YAML.
- Use modern native types: identity `int` primary keys, `timestamptz` as `datetime_immutable`, real `boolean`, backed enums via `#[ORM\Column(enumType: UserRole::class)]`. Every persisted property is explicitly typed.
- Prefer immutable dates (`DateTimeImmutable`).
- Let entities hold their own invariants — role checks, state transitions — rather than draining that logic into services and leaving the entity a bag of getters.

## Repositories

- Back every repository with an **interface** and depend on the interface from services, never the concrete Doctrine class. Naming follows the same `Foo` / `FooInterface` convention as services (see `php.md`): the interface carries the `Interface` suffix (`UserRepositoryInterface`) and the implementation takes the bare name (`UserRepository`). Only once a second implementation lands do you rename each one for its mechanism (`DoctrineUserRepository`, …) and let the interface keep the bare concept name.
- A Doctrine repository extends `ServiceEntityRepository` (hence not `final`) and carries the `@extends ServiceEntityRepository<Entity>` annotation.
- Expose named, intention-revealing finders (`ownerExists()`, `findAssignedTo(...)`) instead of handing query builders to callers. Read with `findOneBy` / `findBy` / DQL. Steer clear of `setMaxResults`-guarded `getOneOrNullResult` blocks whose `catch` can never fire — dead branches break the 100% coverage and MSI gates.

## Migrations

- Ship a Doctrine migration with every schema change, in the same commit as the mapping change.
- CI runs `bin/console doctrine:schema:validate`; after your migration applies, mapping and schema must agree.
- Never rewrite an applied migration — add a new one. When data already exists, alter in place rather than drop-and-recreate.
