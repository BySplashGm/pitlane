# CSS framework

- Status: Accepted
- Date: 2026-08-01
- Issue: [#12](https://github.com/BySplashGm/pitlane/issues/12)

## Context

Pitlane needs a CSS framework for its Twig UI: a dark-themed,
dashboard-style admin interface to create, start, stop, and configure
Assetto Corsa dedicated servers.

Constraints that shaped the decision:

- The asset pipeline is **Symfony AssetMapper** with an importmap. There
  is no Node.js toolchain, and we do not want to add one.
- Interactivity already runs on **Symfony UX** (Turbo, Stimulus), so a
  framework's bundled JavaScript is largely redundant.
- The UI should carry a **custom visual identity**, not a generic
  off-the-shelf look.

Evaluated candidates: Bootstrap 5, Tailwind CSS, Bulma, Pico CSS.

## Decision

Use **Tailwind CSS**, integrated through the
[`symfonycasts/tailwind-bundle`](https://symfony.com/bundles/TailwindBundle/current/index.html).

The bundle downloads the standalone Tailwind binary, so no Node.js build
step is introduced. It plugs into AssetMapper: `assets/styles/app.css`
imports `tailwindcss`, and the compiled output is served through the
existing `importmap('app')` in `base.html.twig`.

Tailwind v4 is configured from CSS (no `tailwind.config.js`). Dark mode
is class-based: `app.css` declares the `dark` variant and a dark base
layer, and `<html>` carries `class="dark"`, so the admin UI renders dark
by default while leaving room for a future light theme.

Developer and bootstrap ergonomics:

- `castor tailwind` runs `tailwind:build --watch` for live rebuilds.
- `castor setup` runs a one-shot `tailwind:build` during first-run
  bootstrap so a fresh clone has compiled CSS.

## Consequences

Positive:

- No Node.js toolchain; the standalone binary fits AssetMapper.
- Utility-first styling supports a bespoke visual identity, with a dark
  theme built from our own design tokens rather than a generic default.
- The production build purges unused classes, keeping the bundle small.
- No overlap with Symfony UX, which keeps owning interactivity.

Negative / trade-offs:

- Tailwind ships **no pre-built components**. Tables, cards, navigation,
  and modals are built by hand; interactive widgets rely on Stimulus.
  This is upfront work before the first finished screen.
- A production asset build must run `tailwind:build --minify` before
  `asset-map:compile`. No such prod pipeline exists yet, so this is a
  follow-up once one is added.

## Alternatives considered

- **Bootstrap 5** — mature, native `data-bs-theme="dark"`, rich
  component set that would deliver a dashboard fast. Rejected because its
  look is hard to make distinctive and its bundled JavaScript overlaps
  with Symfony UX.
- **Bulma** — lightweight, no JavaScript, flexbox-based with v1 dark
  support. Rejected because interactive components (dropdowns, modals)
  would need hand-written JavaScript, duplicating what Stimulus and
  Bootstrap already provide.
- **Pico CSS** — minimal, semantic, built-in dark mode. Rejected as too
  minimal for a dense admin dashboard; it offers too few components.
