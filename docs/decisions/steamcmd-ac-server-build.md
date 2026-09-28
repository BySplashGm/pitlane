# Legal ac-server image build via SteamCMD

- Status: Accepted (Phases 1–3 done)
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
- **Switched validation channel from CI to local, 2026-09-28.** Rather
  than putting a personal Steam account's password into GitHub Actions
  secrets, attempted this step on a local machine with Rosetta-based
  amd64 emulation enabled (Docker Desktop → Settings → General → "Use
  Rosetta for x86/amd64 emulation"), on the theory it might avoid the
  QEMU CPU-frequency abort seen earlier on this same arm64 host. The
  throwaway GitHub Actions workflow used for the anonymous-access test
  has been removed; it did its job (found the argument-order bug,
  confirmed anonymous access is rejected) and isn't needed for the
  account-owned test.
- **Rosetta doesn't fix the abort, and the next layer down is a real
  segfault, not a config problem.** With Rosetta emulation confirmed
  enabled in Docker Desktop, a login attempt still hit `Unable to
  determine CPU Frequency. Try defining CPU_MHZ. / Exiting on
  SPEW_ABORT`. `/proc/cpuinfo` inside the emulated container does
  contain a `cpu MHz` field (checked directly), so this isn't the usual
  "field missing" cause — steamclient does its own runtime frequency
  calibration (rdtsc-based) separately from reading that file, and that
  calibration fails under this emulation. The error message's own
  suggestion — setting the `CPU_MHZ` env var — does get past this
  specific abort (`docker run -e CPU_MHZ=2500 ...`). But immediately
  after, loading `libsteam_api` **segfaults** (`Segmentation fault`,
  exit 139), which is exactly the failure mode the issue already named
  ("SteamCMD is 32-bit x86; segfaults under Apple-Silicon emulation").
  That's a genuine binary-compatibility crash in translated 32-bit x86
  code, not something a further env var or setting is likely to route
  around. This settles it: this Mac cannot run the 32-bit x86 SteamCMD
  binary under Docker Desktop, in either its default QEMU mode or with
  Rosetta enabled. Finishing Phase 1 (which binary AppID 302550
  delivers to an owning account) needs genuine amd64 hardware — a real
  Linux box, a cloud VM the account owner controls directly, or CI
  (accepting the credentials-in-CI tradeoff this switch was trying to
  avoid).
- **Also found:** passing `STEAM_PASSWORD` via `--build-arg` is unsafe
  beyond "don't commit it" — Dockerfile `ARG` substitution splices the
  raw value as literal text into the `RUN` instruction before `/bin/sh`
  parses it, so shell-special characters in the password can break or
  hijack the command. Fixed in `Dockerfile.steamcmd` by reading
  credentials from `--mount=type=secret` files instead (see the file
  for the exact mechanism) — keep that fix regardless of which host
  ends up running Phase 1.
- **Also found:** `docker build`'s log doesn't reliably show SteamCMD's
  own output (steamcmd writes progress with `\r`, not `\n`; BuildKit's
  line-buffered log can drop everything written before a process exits
  quickly), even with `--progress=plain`. For any future interactive
  debugging of a steamcmd login/Guard-code flow, use `docker run -it`
  into a plain container and run `steamcmd.sh` directly rather than
  through `docker build`.
- **Phase 1 RESOLVED, 2026-09-28**, via a side channel that needed
  neither Docker nor Linux emulation: **macOS-native SteamCMD**
  (`brew install --cask steamcmd`) can download files for any platform
  without running them, so it was used purely to inspect what AppID
  302550 delivers — no need to execute the downloaded binary on this
  Mac at all.
  - `@sSteamCmdForcePlatformType linux` was rejected outright
    (`ERROR! Failed to install app '302550' (Invalid platform)`) — this
    looks like a macOS-hosted-steamcmd-specific restriction on forcing
    a foreign platform, not evidence the app lacks a Linux depot (the
    real amd64 CI run earlier used no override at all, on a genuine
    Linux host, and got past this stage cleanly to the "No subscription"
    error — no "Invalid platform" there).
  - `@sSteamCmdForcePlatformType windows` succeeded, and the downloaded
    tree contained **both** `acServer.exe` (confirmed `PE32 executable
    ... Intel 80386, for MS Windows`) **and** a plain `acServer`
    (confirmed `ELF 32-bit LSB executable, Intel 80386, ... statically
    linked`, built with Go). The depot bundles both platforms' binaries
    together rather than gating them behind the platform flag.
  - **Conclusion: no platform override is needed in
    `Dockerfile.steamcmd` at all.** It runs on a genuine Linux host
    (the container itself), which auto-selects the matching platform by
    default — exactly the path the earlier real-CI run already
    exercised successfully up to the subscription check. Once that CI
    run is repeated with an owning account's credentials, it should
    receive the same bundle, including the Linux `acServer` this
    project's `entrypoint.sh` already expects unchanged.
  - Being a statically-linked Go binary also means the Dockerfile's
    `lib32gcc-s1`/`lib32stdc++6` runtime packages, needed for the
    *old* handwritten `acServer` this repo previously used, may be
    unnecessary for this one — worth confirming when a real build
    finally runs it, but not blocking.
  - The downloaded tree also carries Windows-only files
    (`acServer.exe`, `acServerManager.exe`, `.pdb`, `.bat`) alongside
    the Linux ones — Phase 2 should prune those from the final image
    rather than ship dead weight.

1. With an account that owns Assetto Corsa, build
   `ac-server/Dockerfile.steamcmd` locally, passing credentials as
   **BuildKit secrets**, not `--build-arg`:
   ```
   read -s STEAM_USER; echo
   read -s STEAM_PASSWORD; echo
   export STEAM_USER STEAM_PASSWORD
   docker build -f ac-server/Dockerfile.steamcmd \
     --secret id=steam_user,env=STEAM_USER --secret id=steam_password,env=STEAM_PASSWORD \
     -t ac-server:steamcmd-test ac-server
   ```
   **Do not use `--build-arg` for the password.** A first attempt with
   `--build-arg STEAM_PASSWORD=...` broke the build for a password
   containing shell-special characters: Dockerfile `ARG` substitution
   splices the raw value as literal text into the `RUN` instruction's
   shell command *before* `/bin/sh` runs it, so characters like `` ` ``,
   `$`, or `"` in the password get reinterpreted as shell syntax rather
   than passed through as data — a shell-injection-shaped bug, not just
   a cosmetic quoting issue. `Dockerfile.steamcmd` now reads credentials
   from `--mount=type=secret` files instead, which never touch
   Dockerfile-processed shell text.
2. ~~Record the outcome~~ **Done — anonymous login is refused** (see
   findings above). Keep the secret mounts; the Dockerfile header and
   `AGENTS.md` should say a Steam account owning AC is required, and
   how to pass it (`--secret`, never a committed value).
3. ~~Inspect the installed tree~~ **Done — native `acServer` (ELF
   32-bit) is present**, confirmed via the macOS-native-SteamCMD
   side-channel test above. Proceed to Phase 2 as planned; the
   Windows-only-binary contingency below does not apply.

### Phase 2 — Wire into the project — **done, 2026-09-28**

4. ~~Replace `ac-server/Dockerfile`~~ Done: `git mv
   Dockerfile.steamcmd Dockerfile`, old bring-your-own-files Dockerfile
   removed. Also added a `RUN rm -f acServer.exe acServer.bat
   acServerManager.exe acServerManager.pdb` step to drop the
   Windows-only files the depot bundles alongside the Linux binary.
5. ~~Update `ac-server/entrypoint.sh`~~ Not needed — `acServer` lands at
   the same path/name/case the existing `entrypoint.sh` already expects.
6. ~~In `castor.php`'s `build()`~~ Done: dropped the
   `is_file('ac-server/acServer')` guard; `build()` now always builds
   `ac-server:latest`, adding `--secret id=steam_user,env=STEAM_USER`
   and `--secret id=steam_password,env=STEAM_PASSWORD` when those env
   vars are set (never `--build-arg`, never hardcoded).
7. ~~Remove the stale `Dockerfile.steamcmd` draft comments~~ Done —
   header now describes the shipped mechanism, not a draft.
8. ~~Update `AGENTS.md`~~ Nothing there described the manual file-drop
   step, so no change needed.

### Phase 3 — Content directory (tracks/cars)

9. ~~Base game content (tracks, cars) still requires ownership
   separately from the dedicated-server binary~~ Done — content mounts
   from `AC_CONTENT_DIR` at container runtime (existing pattern per
   `castor content:seed`, `castor.php:225`) rather than baking into the
   image, keeping the image itself legally distributable even if a
   user's mounted content is not. Split documented in the Dockerfile
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
