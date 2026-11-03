# Game container memory limit

- Status: Accepted
- Date: 2026-11-03
- Issue: to be filed ("Container memory limit"); [#26](https://github.com/BySplashGm/pitlane/issues/26)

## Context

`DockerService` creates game containers without any memory limit
(`HostConfig` only sets binds, port bindings, network and the restart
policy). #26 wants to show RAM as "used and % of the limit", which has no
meaning without a limit, and one leaking server can today starve the
host and every other server.

## Decision

Every game container gets a **default memory limit** (an environment
variable, for example 1 GB), **overridable per server** in the server
edit page (Options section).

## Consequences

- `DockerService` sets `HostConfig.Memory` at container creation.
  Changing the limit of an existing server only applies when its
  container is recreated, so it marks the server "restart required".
- #26 can show RAM as a share of the limit; a server killed for memory
  shows its exit cause in the cockpit.
- The default must fit a typical server; it is documented in the README
  with how to change it.

## Alternatives considered

- **One fixed limit from the environment, no UI** — simpler, but a big
  track pack or many slots may need more on a single server. Rejected.
- **No limit** — show used RAM only. Rejected: no protection for the
  host.
