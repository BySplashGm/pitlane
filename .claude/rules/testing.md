---
description: PHPUnit conventions — coverage, mutation, naming, structure
globs: ["tests/**"]
alwaysApply: false
---

# Tests

## One Test Per Source File, Same Path

A test sits at the mirror of its subject: `src/Security/Voter/UserVoter.php` is covered by `tests/Security/Voter/UserVoterTest.php`. Extend the lightest base that does the job — plain `TestCase` for an isolated class, `KernelTestCase` when you need the container, `WebTestCase` for a full HTTP round-trip.

## The Gates

- **100% line coverage.** `castor phpunit` fails unless the run reports `Lines: 100.00%`.
- **100% MSI.** Infection's `minMsi` and `minCoveredMsi` are both 100 — write assertions that kill mutants, not just execute lines. `castor infection:diff` narrows mutation testing to what changed against `origin/main`.
- The suite is configured to fail on deprecations, notices and warnings. Keep runs clean rather than muting them.

## Style

- Name methods in `snake_case` with a `test_` prefix (`test_new_user_exposes_constructor_values`) — no `#[Test]` / `@test`.
- Assert through `self::` (`self::assertSame(...)`), not `$this->`.
- Reach for `assertSame` over `assertEquals`, and assert the narrowest fact that proves the behaviour.
- Parameterise with `#[DataProvider]`, placing the provider directly beneath the test that uses it.
- No real credentials or secrets in fixtures — lean on the reduced-cost hasher wired under `when@test` in `security.yaml`.

## Database Tests

When a test needs the schema reset, go through the shared `tests/Support/ResetsDatabase` helper instead of re-writing setup/teardown per class. Hoist only genuinely shared harness into `setUp()` as typed properties; keep one-off setup inline.

## Member Order

Within a test class: constants and properties first, then `setUp()`, then the public test methods (each `#[DataProvider]` right after the test that consumes it), then `tearDown()`, then private/protected helpers.
