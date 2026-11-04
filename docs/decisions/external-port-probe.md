# External port probe

- Status: Accepted
- Date: 2026-11-03
- Issue: [#195](https://github.com/pitlane-studios/pitlane/issues/195); related: [#196](https://github.com/pitlane-studios/pitlane/issues/196) (network diagnostic), [#194](https://github.com/pitlane-studios/pitlane/issues/194) (health checks)

## Context

`PortCheckerService` resolves the public IP through `api.ipify.org`,
then opens a TCP connection to `publicIp:port` **from the Pitlane host
itself**. A connection from the machine to its own public address often
succeeds even when the port is closed to the outside (hairpin NAT,
host firewall rules that allow local traffic, provider security groups
that only filter inbound Internet traffic). The result can say
"Accessible" while players cannot join.

The new interface relies on this answer in three places: the setup
wizard health checks, Settings › System health and the per-server network
diagnostic (`/servers/{id}/network`). Their guides only make sense if the
test runs from outside.

## Decision

The project hosts a small **probe service** (for example
`probe.pitlane.dev`). Pitlane asks it to test TCP and HTTP ports; the
service connects back to **the IP address that called it, only**, and
returns reachable / not reachable per port.

- Only the caller's own IP can be tested: the service cannot be used to
  scan third parties. Requests are rate-limited.
- TCP and HTTP only. UDP stays "not probeable" (connectionless, no
  reliable answer), shown as neutral, never as an error.
- The feature can be disabled, and the probe URL is configurable in the
  instance settings, so anyone can run their own probe.
- Pitlane also detects whether the host has a private address (machine
  behind a home router) to choose the right guide.

## Consequences

- `PortCheckerService` calls the probe instead of connecting to itself.
  When the probe is disabled or unreachable, ports are reported
  "unknown", not "accessible".
- The project must host, monitor and maintain the probe service; its
  code lives outside this application (separate repository or folder).
- The probe sees the public IP of every instance that uses it: document
  this in the README and the settings page.

## Alternatives considered

- **Third-party port-check API** — nothing to host, but an external
  dependency with unclear terms of use, rate limits and reliability.
  Rejected.
- **No external probe** — local checks only (port published by Docker,
  host firewall) and a deduction for the rest. The diagnostic could never
  state "reachable from the Internet". Rejected.
