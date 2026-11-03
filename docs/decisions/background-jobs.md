# Background and scheduled jobs

- Status: Proposed
- Date: 2026-11-03
- Issue: to be filed ("Background worker (Messenger + Scheduler)"); [#22](https://github.com/BySplashGm/pitlane/issues/22), [#27](https://github.com/BySplashGm/pitlane/issues/27), [#28](https://github.com/BySplashGm/pitlane/issues/28)

## Context

#22 (scheduled restarts) and #27 (log parsing) were written as console
commands run every minute by the **host's system cron**. Other planned
work also needs to run outside the HTTP request: crash detection and
alerts (#28), content archive import (#24), the network diagnostic test,
invitation and password-reset e-mails.

The installation target is now "one command, then everything in the
browser": asking users to edit the host crontab breaks that promise, and
Pitlane runs in a container anyway. Messenger is already configured
(`async` and `failed` transports, mailer and notifier messages routed to
`async`) but nothing consumes it in the compose stack.

## Decision

- Add a **worker service** to the compose stack running
  `messenger:consume` (async + scheduler transports).
- Periodic work uses **Symfony Scheduler** (scheduled restarts, log
  parsing, periodic port state check, update check), not the host cron.
- Long or slow work runs as **Messenger messages** (content import,
  network test, e-mails, alerts).

## Consequences

- Nothing to configure on the host; the worker is part of the one-command
  install and of the dev stack.
- The worker must restart on failure and on deploy; failed messages land
  in the `failed` transport and must be visible to the owner.
- Scheduled tasks use the instance time zone (instance settings).

## Alternatives considered

- **Host cron calling console commands** — what #22 and #27 describe.
  Rejected: needs host access, breaks the browser-only setup.
- **Cron inside the app container** — no host change, but a second
  process in the web container and no retry or failure tracking.
  Rejected.
