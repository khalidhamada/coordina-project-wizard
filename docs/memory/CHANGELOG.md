# CHANGELOG.md

## Purpose
Memory-facing changelog for durable add-on changes.

## Durable Changes

### Version 0.2.0
- Project Wizard is now packaged as a standalone add-on with its own plugin metadata, release notes, memory docs, and repo-ready files.
- The wizard is now a multi-step flow covering template selection, core project details, milestones, starter tasks and risks, and final review.
- Template data is normalized into `config/project-template-catalog.json` and loaded through `ProjectTemplateCatalog`.
- Project creation now scaffolds selected milestones, tasks, risks, and optional kickoff work through Coordina's public contracts.
- The add-on keeps its own admin CSS and JS under `assets/admin/`.