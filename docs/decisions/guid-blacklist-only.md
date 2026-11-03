# GUID access list: blacklist only

- Status: Accepted
- Date: 2026-11-03
- Issue: [#23](https://github.com/BySplashGm/pitlane/issues/23)

## Context

#23 planned a per-server mode `none` / `blacklist` / `whitelist` and a
`ServerGuid` list of Steam GUIDs. The official `acServer` reads a
`blacklist.txt` of banned GUIDs. It has no whitelist file: allowing only
listed players needs a locked `entry_list.ini` (`LOCKED_ENTRY_LIST=1`)
where each GUID is bound to a car and a slot. That changes how a server's
cars and slots are configured, far beyond an access list.

## Decision

Manage a **blacklist only**. No mode field: an empty list means nobody is
banned. Operators may edit the list of their assigned servers (their one
write permission).

## Consequences

- #23 drops `blacklistMode` and the GUID `type`; it is renamed "GUID
  blacklist management".
- `AcConfigService` writes `blacklist.txt` on every add/remove. Whether
  `acServer` re-reads it while running is still to verify; if not, a
  change on a running server needs a restart and must say so.
- The new interface's Access tab shows a single blacklist (pitlane-ui
  prototype updated on 2026-11-03).
- A private server stays possible with the join password.

## Alternatives considered

- **Whitelist through a locked entry list** — real "only these players"
  servers, but every GUID must be tied to a car and a slot in the
  server form. Rejected for now; could come back as its own feature if
  leagues ask for it.
