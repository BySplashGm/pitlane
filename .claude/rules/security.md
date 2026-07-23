---
description: Security conventions — authentication, role-based access, Docker socket, secrets
globs: ["src/**"]
alwaysApply: true
---

# Security

Pitlane logs users in and then drives a **Docker daemon** on their behalf — it mounts `/var/run/docker.sock`, which is effectively host-root. Treat anything that reaches a container, a process, or the daemon as hostile until proven otherwise.

## Authentication

- Hash passwords with the `auto` hasher (bcrypt / argon2id). Never store, log, or reversibly encrypt a password.
- The provider loads `App\Entity\User` by `email`; login is `form_login` with CSRF on. Keep `/login` — and only `/login` — at `PUBLIC_ACCESS`; everything else demands `IS_AUTHENTICATED_FULLY`.

## Authorization

- Roles are the `UserRole` backed enum: `owner`, `admin`, `operator`. The server enforces them; the UI hiding a button is never the control.
- Route every decision through a **voter** in `src/Security/Voter/`, called via `denyAccessUnlessGranted(...)` or `#[IsGranted]`. Don't sprinkle `in_array('ROLE_…', $roles)` across controllers and services.
- The rules the voter encodes: `owner` can do everything; `admin` matches that except touching or deleting the owner account; `operator` is scoped to the servers assigned to them — read-only on settings, allowed to start/stop/restart and read logs.

## Talking to Docker and Processes

- Every call to `docker` or a server binary goes through `symfony/process` with the command as an **array** (arguments escaped for you) — never assemble a shell string from user input, and never `exec`/`shell_exec`.
- Whitelist and validate any user value that becomes a container name, image tag, path, or config key. Keep writes inside the configured `AC_SERVERS_DIR` and reject path traversal.

## Secrets

- Keep secrets (database and SMTP credentials, API keys, `APP_SECRET`) out of the repo and out of tracked config — supply them at runtime through the environment or Symfony's encrypted vault. A missing required secret should abort startup, not boot on an empty default.
- Never write sensitive values (passwords, tokens, session ids) to a log; mask them first.
