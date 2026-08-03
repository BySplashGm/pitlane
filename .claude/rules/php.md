---
description: PHP conventions — class design, typing, naming, modern idioms
globs: ["src/**/*.php", "tests/**/*.php"]
alwaysApply: false
---

# Writing PHP

Every file opens with `declare(strict_types=1);`. Don't hand-write license or copyright header blocks — CS Fixer injects the canonical header, so anything you add duplicates it.

## Names Carry Their Own Documentation

Spell identifiers out in full. `$entityManager`, `$userRepository`, `$request` — never `$em`, `$repo`, `$req`. A reader should infer a variable's type and role from its name alone, without scanning the surrounding lines. This applies to parameters and properties as much as locals.

## Typing Is Not Optional

PHPStan runs at `max` with the strict-rules, PHPUnit and Doctrine extensions, plus the stricter switches (`checkImplicitMixed`, `checkUninitializedProperties`, `checkBenevolentUnionTypes`, `checkMissingOverrideMethodAttribute`). In practice:

- Type every parameter, return and property, and pick the tightest type that is true — reach for `never`, unions and intersections instead of falling back to `mixed`.
- Put `#[Override]` on anything that overrides a parent method or satisfies an interface.

## Exception Contracts

Document propagated checked exceptions with `@throws`, in tests as well as source. If a method calls something marked `@throws X` and doesn't catch `X`, repeat the `@throws X` on the caller. Keep those tags around as the method's failure contract even when no tool is currently forcing them.

## Class Design

- **Default to `final`.** Drop `final` only where inheritance is a deliberate requirement — an abstract test base, or a Doctrine repository extending `ServiceEntityRepository`.
- **Default to `readonly`.** Mark every property that isn't meant to change; when they all qualify, make the whole class `readonly` (services, voters, handlers and value objects usually can).
- **Program to interfaces.** Give each service an interface and depend on that, never the concrete type. While a service has a single implementation, the interface carries the `Interface` suffix (`DockerServiceInterface`) and the implementation takes the bare name (`DockerService`). Only once a second implementation lands do you rename each one for how it works (`SocketDockerService`, `RemoteDockerService`) and let the interface keep the bare concept name. Repositories follow the same convention (`ServerRepository` / `ServerRepositoryInterface`), with the extra Doctrine specifics in `persistence.md`.

## Console Commands

A command is a `final` class in `src/Command/` tagged `#[AsCommand]` with a single responsibility. Non-trivial work belongs in an injected service; the command itself only handles prompts, output and exit codes.

## Reach for Symfony Before Raw PHP

Where a Symfony component covers the need, use it rather than the bare language function:

- `symfony/uid` — `Uuid::v7()->toString()`, never `(string) $uuid` or `->toRfc4122()`.
- `symfony/serializer` — inject `SerializerInterface` (or return `JsonResponse`) instead of calling `json_encode`/`json_decode`.
- `symfony/filesystem` — inject `Filesystem`; use `dumpFile()` (atomic), `copy()`, `remove()`, `mkdir()` over `file_put_contents`/`unlink`/`mkdir`.
- `symfony/finder` — traverse directories with `Finder`, not `glob()` or `opendir()` loops.
- `symfony/string` — `u()`, `UnicodeString`, `AsciiSlugger` for normalization/slugging/case, not `preg_replace` chains.
- `symfony/process` — the **only** way to shell out (Docker CLI, server binaries). Pass the command as an array so arguments are escaped; never `exec`/`shell_exec`/`proc_open`.
- Build paths and messages with `sprintf`, not `.` concatenation.

## Use the Language You Have

Target the newest idioms available at the `composer.json` minimum:

- Promote constructor properties instead of separate declare-and-assign.
- Use named arguments to skip defaulted parameters rather than padding a call with `null`s.
- Prefer `match` over `switch` for exhaustive mapping and let the no-match throw.
- Chain with the nullsafe `?->`; wrap single-call callbacks with first-class callable syntax (`$obj->method(...)`).
- Replace constant sets with backed enums in `src/Enum/`.
- Use `str_contains` / `str_starts_with` / `str_ends_with` / `array_is_list` over hand-rolled equivalents.

Don't polyfill anything the declared minimum PHP already ships.
