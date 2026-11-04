# License

- Status: Accepted
- Date: 2026-11-03
- Issue: [#20](https://github.com/pitlane-studios/pitlane/issues/20)

## Context

The repository ships an AGPL license: `LICENSE` is the GNU Affero
General Public License v3 (added in #61) and `composer.json` declares
`AGPL-3.0-or-later`. Issue #20 (README) and the design documents of the
new interface still said **MIT**, so the README, the Help page and the
Settings › About section would have shown the wrong license.

Pitlane is self-hosted, but anyone can also run it as a hosted service
for other communities. The license decides whether a modified version
offered that way must publish its changes.

## Decision

Keep **AGPL-3.0-or-later**. Every place that names the license says
AGPL-3.0, never MIT.

## Consequences

- #20 (README) states AGPL-3.0 and links `LICENSE`.
- The new interface shows the license in Help › License and in
  Settings › About, with a link to the source repository: AGPL section 13
  asks that users interacting with the software over a network can get
  its source.
- A hosted fork that modifies Pitlane must publish its modified source
  under the same license. Contributions are accepted under the same
  terms.
- The design documents (pitlane-ui) were corrected on 2026-11-03.

## Alternatives considered

- **MIT** — what #20 originally said. Simpler for reusers, but a closed
  hosted fork would be allowed. Rejected: the license file and composer
  metadata were already AGPL, and the project wants hosted variants to
  stay open.
