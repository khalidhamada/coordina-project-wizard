# AGENTS.md

## Purpose
Coordina Project Wizard is a separately packaged Coordina add-on that guides users through templated project setup.

## Read First
For meaningful work in this plugin, read in this order:
1. `AGENTS.md`
2. `docs/memory/CONTEXT.md`
3. `docs/memory/PLANS.md`
4. `docs/memory/CHANGELOG.md`
5. `README.md`
6. `docs/memory/PROJECT_PROGRESS.md` when history matters

Do not treat `docs/memory/SESSION_LOG.md` as retrieval memory.

## Working Rules
- This plugin is an add-on, not the Coordina core plugin.
- Keep add-on behavior scoped to this plugin unless a narrow host seam is required in Coordina core.
- Preserve WordPress-native behavior: capabilities, nonces, sanitization, escaping, REST permissions, and i18n.
- Prefer the existing add-on architecture: provider registration, entitlement provider, route-only admin page, add-on-scoped settings, and contract-backed project creation.
- Keep admin JS and CSS localized to this plugin; do not push wizard-specific UI into Coordina core assets.
- Update this plugin's memory files when durable add-on direction, architecture, or release state changes.

## What To Avoid
- coupling wizard behavior directly to Coordina concrete classes when a public contract exists
- moving add-on defaults or UI into the core plugin without a clear host-level reason
- bundling this plugin's release notes or packaging notes into Coordina core docs