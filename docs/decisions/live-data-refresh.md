# Live data refresh

- Status: Accepted
- Date: 2026-11-03
- Issue: [#203](https://github.com/BySplashGm/pitlane/issues/203)

## Context

Several screens of the new interface show data that changes while the
page is open: the fleet view (status, drivers, CPU), the server cockpit
(status, drivers, CPU/RAM, ports, logs), the activity journal ("N new
entries") and the public status page. Today only the server logs refresh,
by reloading an HTML fragment from `/server/{id}/logs` every 5 s.

The new interface needs more than a page reload can give:

- a **Pause / Resume** control and "Updated N s ago";
- **stale data** kept on screen ("read at HH:MM:SS") when the connection
  drops, with a retry every 5 s instead of an empty page;
- a **frozen row order** in the fleet view while a row is hovered, focused,
  open or running an action, and FLIP animations keyed on `data-id`.

Options were JSON polling, Turbo Streams (polled HTML fragments) and
Mercure (server push; the hub ships with FrankenPHP).

## Decision

Use **JSON polling**: a light JSON endpoint per screen (fleet list,
single server, journal head, public status) read every **5 s** (30 s for
`/status`) by Stimulus controllers. The endpoint applies the same voters
as the HTML pages and returns only what the screen shows.

## Consequences

- One small endpoint per screen; no extra service, no long-lived
  connections, works behind any proxy.
- The client keeps full control of rendering, so pause, stale state,
  frozen order and animations stay simple.
- Load grows linearly with open tabs × servers. Each poll must stay
  cheap: Docker status in bulk (`getBulkStatus()`), cached driver and
  stats reads, no per-request port probing.
- Mercure stays possible later behind the same payloads, if polling cost
  becomes a problem.

## Alternatives considered

- **Turbo Streams over polling** — less JavaScript, but server-rendered
  fragments fight the frozen ordering and FLIP animations of the
  prototype. Rejected for these screens.
- **Mercure** — true push, built into FrankenPHP. Needs a process that
  watches Docker and publishes updates, plus authorization of private
  topics. Rejected for the first version: more moving parts than the
  5 s refresh needs.
