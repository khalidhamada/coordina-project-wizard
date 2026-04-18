# Coordina Project Wizard v0.2.0 — Multi-Step Template Wizard

**Release Date:** April 18, 2026

## Overview

Coordina Project Wizard 0.2.0 is the first separately packaged release of the add-on. It turns the initial setup helper into a proper multi-step project wizard, moves template data into a normalized JSON catalog, and prepares the plugin to live in its own repository and distribution package.

## What Changed

### The Wizard Became Template-Driven And Multi-Step
- template selection is now the first step
- selected templates show a clear preview and summary before users continue
- core project fields, milestones, starter tasks, starter risks, and final review now live in separate steps
- the flow supports start-over behavior and clearer review before creation

### Template Data Was Moved Into A Dedicated Catalog
- a normalized JSON catalog now lives under `config/project-template-catalog.json`
- the add-on loads template data through a dedicated `ProjectTemplateCatalog` service
- the settings surface derives default template choices from the catalog instead of hardcoded values

### Project Scaffolding Became More Complete
- project creation now supports selected milestones, starter tasks, starter risks, and an optional kickoff task
- the add-on still creates everything through Coordina's public project, task, and settings contracts
- add-on behavior remains scoped to the plugin instead of pushing wizard logic into Coordina core

### The Plugin Is Now Ready To Live As Its Own Repo
- added standalone README, WordPress readme, release-notes index, license, composer metadata, AGENTS instructions, and memory docs
- added `.gitignore` tuned for an isolated plugin repository
- versioned the separately packaged plugin as `0.2.0`

### The Release Now Includes Screenshot Coverage And Dependency Guardrails
- added release screenshots for the template selection, project details, milestones, tasks, and final review steps under `releases/v0.2.0/src/`
- the add-on now refuses activation unless the main Coordina plugin is installed and active
- admin users now get a clear dashboard notice when Coordina Project Wizard is present without its required core dependency

## Screenshots

### Template Selection

![Project wizard template selection](src/coordina-wizard-template-v0.2.0.png)

### Project Details

![Project wizard project details](src/coordina-wizard-info-v0.2.0.png)

### Milestone Planning

![Project wizard milestone planning](src/coordina-wizard-milestones-v0.2.0.png)

### Starter Tasks

![Project wizard starter tasks](src/coordina-wizard-tasks-v0.2.0.png)

### Final Review

![Project wizard final review](src/coordina-wizard-review-v0.2.0.png)

## Why This Matters

- teams can start projects from reusable templates without manual setup repetition
- the add-on can now be maintained, versioned, and published independently from Coordina core
- template behavior is cleaner to evolve because the data model is separated from the UI and service logic

## Upgrade Notes

- this release expects the Coordina core plugin with the modular platform layer available
- no standalone database migration is introduced by the add-on packaging changes
- activate Coordina core before activating this plugin