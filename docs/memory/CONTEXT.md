# CONTEXT.md

## Snapshot
- Project: Coordina Project Wizard
- Type: WordPress add-on plugin for Coordina
- Stage: multi-step wizard implemented and packaged as a standalone repo-ready plugin
- Current focus: live WordPress verification, packaging polish, and contract-safe hardening

## Product Shape
- Adds a guided project setup surface to Coordina
- Starts with template selection from a JSON-backed template catalog
- Prepopulates core project details from the selected template
- Lets users review and tailor milestones, tasks, and risks before creation
- Creates projects and starter structure through Coordina's public platform contracts

## Architecture Notes
- This plugin depends on Coordina core and should remain add-on-scoped
- Registration happens through platform provider filters and entitlement-provider filters
- The add-on exposes a hidden direct-access admin page instead of a separate top-level menu
- Wizard data is normalized in `config/project-template-catalog.json`
- Business logic belongs in `src/`, not in templates or asset files
- Admin assets are local to this add-on under `assets/admin/`

## Known Limits
- Live WordPress runtime verification is still pending
- Template catalog generation is currently a maintained file workflow, not an automatic build step
- Packaging and publishing automation are not set up yet