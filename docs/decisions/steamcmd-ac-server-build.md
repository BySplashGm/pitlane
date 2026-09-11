# Legal ac-server image build via SteamCMD

- Status: Proposed
- Date: 2026-09-11
- Issue: [#82](https://github.com/BySplashGm/pitlane/issues/82)
- Depends on: [#81](https://github.com/BySplashGm/pitlane/issues/81) (draft `ac-server/Dockerfile.steamcmd`)

## Context

`ac-server:latest` builds today from `ac-server/Dockerfile`, which
expects the user to hand-drop proprietary Assetto Corsa dedicated-server
files (`acServer`, `system/`, `content/`) into `ac-server/` (gitignored).
We cannot ship those files, and manual copying is friction for anyone
bootstrapping the project.

A draft `ac-server/Dockerfile.steamcmd` already exists (added in #81):
fetches AppID `302550` via SteamCMD at build time, anonymous login with
`STEAM_USER`/`STEAM_PASSWORD` build-arg fallback. It is **not** wired
into `castor build` (`castor.php:36-51`), which still gates the
bring-your-own-files build behind `is_file('ac-server/acServer')`.

Two assumptions in the draft are unverified and block adoption:

1. Whether AppID 302550 is downloadable **anonymously**, or requires an
   account that owns Assetto Corsa.
2. Whether 302550 delivers the **native Linux** `acServer` binary this
   image expects, or only `acServer.exe` (a known Steam reference forces
   the Windows platform type for this AppID).

SteamCMD itself is 32-bit x86, so validation must run on a genuine
**amd64 Linux** host — Apple Silicon under QEMU emulation is known to
segfault it.

## Plan

### Phase 1 — Validate (amd64 Linux host required)

**Attempted on 2026-09-11 from an arm64 host (Docker Desktop, QEMU
`--platform linux/amd64` emulation):** `steamcmd.sh +login anonymous
+force_install_dir /ac-server +app_update 302550 validate +quit` aborts
before reaching Steam:

```
Unable to determine CPU Frequency. Try defining CPU_MHZ.
Exiting on SPEW_ABORT
```

SteamCMD's bootstrapper reads `cpu MHz` from `/proc/cpuinfo`, which
QEMU's emulated CPU doesn't populate — so this is an emulation artifact,
not evidence against anonymous access or the Linux binary. It reproduces
the segfault-class risk the issue already called out for Apple Silicon
and confirms this repo's dev machines (arm64) cannot run Phase 1
locally. Real validation needs either genuine amd64 hardware, a cloud
amd64 VM, or a real-amd64 CI runner (e.g. GitHub Actions
`ubuntu-latest`) — not Docker Desktop's QEMU emulation on Apple Silicon.
Docker Desktop's Rosetta-based amd64 emulation (Settings → General →
"Use Rosetta for x86/amd64 emulation") is reported by other SteamCMD
users to avoid this specific abort by preserving real CPU identification
through the translation layer, unlike QEMU — worth a quick retry there
before reaching for a separate host, but still not a substitute for the
real-hardware/CI confirmation below since Rosetta itself is another
translation layer.

**Confirmed on real amd64 CI (GitHub Actions `ubuntu-latest`, via a
throwaway `.github/workflows/steamcmd-validate.yml`, commits
`d258eb5`/`39210b8`/`47a472a` on this branch), 2026-09-11:**

- **Bug found and fixed:** the draft ran `+login` before
  `+force_install_dir`. SteamCMD requires the reverse order — logging in
  first produced `Please use force_install_dir before logon!` and
  `ERROR! Failed to install app '302550' (Missing configuration)`.
  Fixed in `Dockerfile.steamcmd` (force_install_dir now precedes login).
- **UNVERIFIED #1 resolved — anonymous access is REJECTED.** With the
  argument order fixed, anonymous login itself succeeds ("Connecting
  anonymously to Steam Public... OK", "Waiting for user info... OK"),
  but app install then fails: `ERROR! Failed to install app '302550'
  (No subscription)`. AppID 302550 requires a Steam account that owns
  Assetto Corsa — the account-fallback path in the Dockerfile
  (`STEAM_USER`/`STEAM_PASSWORD` build args) is **not optional**, it's
  required for every build.
- **UNVERIFIED #2 still open** — never reached `app_update` far enough
  to see which binary lands, since no subscription blocks the download
  before any files are fetched. Needs a real build with an
  account-owned login to answer.
- The workflow now takes `STEAM_USER`/`STEAM_PASSWORD` from GitHub
  Actions repository secrets (never inline) and only runs on
  `workflow_dispatch` — someone with a Steam account that owns AC needs
  to add those secrets and dispatch the workflow to finish Phase 1.

1. Build `ac-server/Dockerfile.steamcmd` anonymously:
   `docker build -f ac-server/Dockerfile.steamcmd -t ac-server:steamcmd-test ac-server`.
2. Record the outcome:
   - Anonymous login succeeds → note it, drop the `STEAM_USER`/
     `STEAM_PASSWORD` build-args section down to a documented fallback
     only.
   - Anonymous login is refused → keep the build-args, document in the
     Dockerfile header and in `README`/`AGENTS.md` that a Steam account
     owning AC is required, and how to pass it (`--build-arg`, never a
     committed secret).
3. Inspect the installed tree (`docker run --rm --entrypoint sh
   ac-server:steamcmd-test -c 'ls -la /ac-server'`):
   - Native `acServer` present → proceed to Phase 2 as-is.
   - Only `acServer.exe` → this is a fork decision, see
     "Contingency: Windows-only binary" below; **stop and re-scope**
     before Phase 2, since it changes the base image and entrypoint.

### Phase 2 — Wire into the project (only after Phase 1 confirms a native Linux binary)

4. Replace `ac-server/Dockerfile` with the validated contents of
   `Dockerfile.steamcmd` (or repoint the build to the steamcmd file and
   delete the old one — pick one, don't keep two Dockerfiles alive).
5. Update `ac-server/entrypoint.sh` if SteamCMD's install lands the
   binary under a different name/case/path than `./acServer`.
6. In `castor.php`'s `build()` (currently `castor.php:36-51`):
   - Drop the `is_file('ac-server/acServer')` guard and its warning
     branch.
   - Always run `docker build --pull -t ac-server:latest ac-server`.
   - If an account fallback is kept from step 2, thread `STEAM_USER`/
     `STEAM_PASSWORD` through as Castor build args sourced from the
     environment, never hardcoded.
7. Remove the stale `Dockerfile.steamcmd` draft comments (the "DRAFT",
   "UNVERIFIED #1/#2" header) since the file is now the real thing.
8. Update `AGENTS.md` / any onboarding doc that still describes the
   manual file-drop step.

### Phase 3 — Content directory (tracks/cars)

9. Base game content (tracks, cars) still requires ownership separately
   from the dedicated-server binary. Decide: mount `AC_CONTENT_DIR` at
   container runtime (current pattern per `castor content:seed`,
   `castor.php:225`) rather than baking content into the image. This
   keeps the image itself legally distributable even if a user's
   mounted content is not. Document this split clearly in the Dockerfile
   header and `AGENTS.md`.

### Contingency: Windows-only binary

If Phase 1 shows 302550 only ships `acServer.exe`:

- Base image needs Wine (or a Windows container, out of scope for a
  Linux Docker host), which is a materially bigger change than swapping
  a `COPY` for a `RUN steamcmd`.
- Before committing to Wine, check whether `+@sSteamCmdForcePlatformType
  linux` (rather than the `windows` override the known reference uses)
  yields a native binary — some AppIDs publish both platforms.
- If Wine is unavoidable, this becomes its own follow-up issue/ADR
  rather than folding into this one — the risk profile (running a
  Windows binary under Wine, inside a container that already runs with
  Docker-socket-adjacent privilege per `security.md`) deserves its own
  review.

## Verification

- `docker build -f ac-server/Dockerfile -t ac-server:latest ac-server`
  succeeds from a clean clone with nothing manually placed in
  `ac-server/`.
- `castor build` no longer prints the "Skipping ac-server:latest build"
  warning and does not require any file present in `ac-server/`.
- Existing `DockerService`-driven tests (server create/start/stop) keep
  passing unchanged — they exercise the Docker Engine API against
  whatever image tag exists, not the Dockerfile contents, so no test
  code should need to change unless the binary path moved (see step 5).

## Consequences

Positive:

- Repo and image stay free of proprietary Kunos/505 files; onboarding
  drops the manual copy step entirely.
- `castor setup` / `castor build` work out of the box on a supported
  host.

Negative / trade-offs:

- Build now depends on Valve's CDN and Steam infrastructure being
  reachable at build time (no more fully offline builds).
- If an account fallback turns out to be required, contributors without
  an AC-owning Steam account cannot build the image themselves.
- Adds build time (SteamCMD download + app validate) to every
  `castor build`, with no layer-cache reuse across AC server patches
  unless the base image is rebuilt deliberately.
