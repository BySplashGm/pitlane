# Pitlane

Run several Assetto Corsa dedicated servers from one dashboard: one Docker
container per server, configured and monitored from the browser.

[![CI](https://github.com/BySplashGm/pitlane/actions/workflows/ci.yml/badge.svg)](https://github.com/BySplashGm/pitlane/actions/workflows/ci.yml)
[![License: AGPL v3+](https://img.shields.io/badge/license-AGPL--3.0--or--later-blue.svg)](LICENSE)
![PHP 8.4+](https://img.shields.io/badge/php-%E2%89%A58.4-777bb4)
![Symfony 8](https://img.shields.io/badge/symfony-8-000000)

Hosting more than one Assetto Corsa server by hand means juggling
`server_cfg.ini` files, port ranges and processes, and finding out a port is
closed only when players complain. Pitlane gives each server its own isolated
container, generates its config from a form, and shows its status, logs and port
reachability in one place.

> **Project status: early stage, pre-release.**
> There is no release and no published Docker image yet. The only way to run
> Pitlane today is the development setup below. Star or watch the repo to follow
> progress, and see the [roadmap](#roadmap) for what is planned. Issues and pull
> requests are welcome.

## Features

What the code on `main` does today:

### Server management

- Create, edit, start, stop, restart and delete servers; each one runs in its
  own Docker container.
- Configure track and layout, car list, session type (practice, qualify, race),
  duration in minutes or laps, weather, ambient and track temperature, track
  grip, dynamic track, passwords, max clients and lobby registration.
- Config is written for you; Pitlane mounts it read-only into the container.
- Port conflict detection across servers, with a suggestion for the next free
  ports (starting at 9600).

### Monitoring

- Live container status on the dashboard.
- Server log viewer on each server page, refreshed by polling (last 200 lines).
- Port exposure check: Pitlane resolves your public IP and tries to reach the
  server's TCP and HTTP ports on it. UDP is listed but not probed.

### Users and access

- Form login with remember me.
- Three roles: owner, admin and operator. Operators only see the servers
  assigned to them; they can start, stop and restart them but not edit settings.
- User management and a personal account page.
- The owner can impersonate any user to see Pitlane exactly as they do.
- `bin/console pitlane:create-owner` creates the initial owner account.

## How it works

Pitlane is a Symfony application backed by PostgreSQL. It talks to the Docker
Engine API through the mounted `/var/run/docker.sock` and starts one container
per game server from the image built from
[`ac-server/Dockerfile`](ac-server/Dockerfile). Cars, tracks and weather presets
offered in the forms are read from a content directory (`AC_CONTENT_DIR`).

Access to the Docker socket is effectively root on the host. Run Pitlane only on
a machine you control and keep the dashboard off the public internet.

### Assetto Corsa server files

Pitlane does not ship the Assetto Corsa dedicated server. The working image is
built from files you supply: place `acServer`, `system/` and `content/` in
`ac-server/` (they are gitignored). Without them `castor build` skips the
game-server image, and starting a server will not work.

This step goes away with
[#82](https://github.com/BySplashGm/pitlane/issues/82): the image will be built
with SteamCMD from a Steam account that owns Assetto Corsa. A draft lives in
`ac-server/Dockerfile.steamcmd`; it is not wired in yet.

## Getting started

### Prerequisites

- A Linux host (amd64) with Docker Engine and Docker Compose. The game-server
  image is `linux/amd64`.
- [Castor](https://castor.jolicode.com/), the task runner.
- No PHP or Symfony CLI on the host: everything runs in containers.
- The Assetto Corsa dedicated server files described above (optional if you
  only want to explore the UI).

### Install

```bash
git clone https://github.com/BySplashGm/pitlane.git
cd pitlane
```

Docker Compose reads `.env`. Before running Pitlane anywhere but your own
machine, change these values:

- `APP_SECRET`: a long random string.
- `POSTGRES_PASSWORD`: the database password (defaults to `!ChangeMe!`).
- `AC_CONTENT_DIR`: where your game content lives (defaults to
  `./var/ac-content`).

Then build and start everything:

```bash
castor setup
```

`castor setup` builds the images, starts the stack, builds the CSS, creates
placeholder content and loads dev fixtures.

- App: <http://localhost:8000>
- Mailpit (dev mail catcher): <http://localhost:8025>
- Dev fixtures create `owner@pitlane.local`, `admin@pitlane.local`,
  `operator1@pitlane.local` and `operator2@pitlane.local`, all with the password
  `password`. These are for local development only.

Without the fixtures, create the first account yourself:

```bash
docker compose exec app bin/console pitlane:create-owner
```

The [one-command installer](https://github.com/BySplashGm/pitlane/issues/191)
and [setup wizard](https://github.com/BySplashGm/pitlane/issues/192) will
replace these steps.

### Game content

Cars, tracks and weather are your own: they require owning the game, so Pitlane
never ships them. Pitlane reads them from `AC_CONTENT_DIR` on the host, laid
out like the game's `content/` folder:

```text
ac-content/
├── cars/<car>/
├── tracks/<track>/<layout>/
└── weather/<preset>/
```

For local development, `castor content:seed` fills `AC_CONTENT_DIR` with
placeholder, non-copyright content so the forms have something to show.

Today the game-server image also bakes in `ac-server/content/` at build time,
so a car or track must be present in both places to be usable.

### Your first server

1. Log in as the owner and create a server: name, track, cars, session.
2. Start it from its page and watch the logs.
3. Check the **Port exposure** block on the same page.

### Ports and network

Each server needs three host ports: TCP, UDP and HTTP. Pitlane suggests the
next free ones, counting up from 9600, and refuses a port another server
already uses.

Players can only join if these ports are reachable from the internet:

- **VPS or dedicated server**: open the ports in the host firewall (`ufw`,
  `firewalld`) and in your provider's security group.
- **Home machine**: forward the TCP and UDP ports on your router to the machine
  running Pitlane.

A step-by-step network diagnostic is planned in
[#196](https://github.com/BySplashGm/pitlane/issues/196).

### Everyday tasks

`castor up`, `castor down`, `castor build`, `castor tailwind` (watch CSS) and
`castor fixtures`. Run `castor list` for all of them.

## Roadmap

Everything below is planned, not built. Progress is tracked in [GitHub
milestones](https://github.com/BySplashGm/pitlane/milestones).

- **v0.1 MVP** (20 closed, 2 open): the current feature set. Remaining work is a
  legal SteamCMD-based server image build and installation docs.
- **Onboarding and new interface** (planned next): a production image and
  [one-command installer](https://github.com/BySplashGm/pitlane/issues/191), a
  [guided first-run setup](https://github.com/BySplashGm/pitlane/issues/192),
  English and French UI, and a [redesigned
  interface](https://github.com/BySplashGm/pitlane/issues/216).
- **v0.2 Advanced management**: multiple sessions per server, activity journal,
  Sol weather presets, content upload, GUID blacklist, scheduled restarts,
  server cloning, update check, container memory limits.
- **v0.3 Monitoring and UX**: REST API, public status page, crash alerts
  (Discord webhook, email), player connection history, CPU and RAM monitoring.

## Contributing

Issues and pull requests are welcome. Look for
[`good first issue`](https://github.com/BySplashGm/pitlane/labels/good%20first%20issue)
and [`help wanted`](https://github.com/BySplashGm/pitlane/labels/help%20wanted).
Open an issue before starting anything large.

The stack is PHP 8.4+, Symfony 8, Doctrine ORM on PostgreSQL 16, Twig with
Symfony UX (Turbo, Stimulus) and AssetMapper. [`AGENTS.md`](AGENTS.md) documents
the layout and conventions.

Quality bar, enforced in CI:

- PHPStan at level max
- 100% line coverage with PHPUnit
- 100% mutation score (MSI) with Infection
- PHP-CS-Fixer, Rector and a Doctrine schema validation
- [Conventional Commits](https://www.conventionalcommits.org/)
  (`feat(scope): subject`)

Run the checks locally through Castor:

```bash
castor lint       # every gate, tests and Infection included
castor lint:fix   # same, auto-fixing where possible
castor phpunit    # tests with the 100% coverage gate
castor infection  # mutation testing
```

## Community

Questions, ideas or a server to show off? Join the
[Discord](https://discord.gg/vNaVuc96YH).

## License

Pitlane is released under the [AGPL-3.0-or-later](LICENSE).

Pitlane is an independent project and is not affiliated with or endorsed by
Kunos Simulazioni or Assetto Corsa.
