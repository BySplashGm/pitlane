# Driver data source

- Status: Accepted
- Date: 2026-11-03
- Issue: [#205](https://github.com/BySplashGm/pitlane/issues/205); [#27](https://github.com/BySplashGm/pitlane/issues/27) (connection history)

## Context

The new interface shows drivers everywhere: a count per server in the
fleet view, the list of drivers on track (name, car, since when) in the
cockpit, a "N drivers will be disconnected" confirmation before stop or
restart, free slots on the public status page, and the connection
history of #27. Pitlane has no driver data today.

Two sources exist: the HTTP port of each `acServer` (`/INFO` for the
session and client count, `/JSON|` for the car list with connected
drivers), and the server logs, which contain join and leave events.

## Decision

- **Drivers on track** are read live from each running server's HTTP
  port (`/INFO`, `/JSON|`), with a short timeout and a short cache.
- **Connection history** (#27) comes from the **logs**: join/leave
  events parsed by a scheduled task and stored in the database.

## Consequences

- The live view does not depend on parsing, and survives a Pitlane
  restart: the game server is the source of truth.
- A server whose HTTP port does not answer shows "unknown" drivers, not
  zero.
- The history keeps events that a 5 s poll would miss (quick join and
  leave). Parsing must be idempotent (no duplicate events after a
  re-read).
- Pitlane reaches the game servers on the Docker network, not through
  the public address.

## Alternatives considered

- **Logs only** — one source, but the "on track now" state has to be
  rebuilt from events and drifts after a Pitlane restart or a missed
  line. Rejected.
- **HTTP only** — history deduced from differences between two polls;
  short sessions are lost. Rejected.
