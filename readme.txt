=== Coordina Project Wizard ===
Contributors: khalidhamada
Tags: project management, project, project manager, task management
Requires at least: 6.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: coordina-project-wizard

Guided, template-driven project setup add-on for Coordina.

== Description ==

Coordina Project Wizard adds a multi-step project setup flow on top of Coordina's modular platform.

Key capabilities:

* Template-first project setup
* Multi-step guided flow for project details, milestones, tasks, and risks
* Template catalog stored in JSON for cleaner add-on architecture
* Contract-backed project creation through Coordina's public platform layer
* Add-on-scoped defaults and route-only admin page integration

== Installation ==

1. Upload the coordina-project-wizard folder to /wp-content/plugins/.
2. Install the main Coordina plugin from https://github.com/khalidhamada/coordina
3. Activate the main Coordina plugin.
4. Activate Coordina Project Wizard.
5. Launch the wizard from the Coordina Projects area.

If Coordina core is missing or inactive, this add-on will refuse activation and will show an admin notice until the dependency is available.

== Screenshots ==

1. Template selection step using the normalized project template catalog.
2. Project details step with prefilled defaults from the selected template.
3. Milestones step for reviewing and tailoring the starter structure.
4. Tasks step for editing kickoff work before project creation.
5. Final review step before the add-on scaffolds the project.

== Frequently Asked Questions ==

= Does this plugin work without Coordina core? =

No. This is an add-on and requires the main Coordina plugin.

= What does the wizard create? =

It creates a project plus the selected starter milestones, tasks, risks, and optional kickoff task.

= Can templates be customized before project creation? =

Yes. The wizard lets users include, exclude, edit, and add milestones, tasks, and risks before creation.

== Changelog ==

= 0.2.0 =

* Added the multi-step template-driven project wizard experience
* Added a normalized JSON template catalog and dedicated catalog service
* Added scaffold creation for milestones, tasks, risks, and optional kickoff task
* Added standalone repo-ready docs, release notes, memory files, and distribution metadata
* Added release screenshots and a hard activation dependency check for Coordina core

== Upgrade Notice ==

= 0.2.0 =

First separately packaged repo-ready release of the Project Wizard add-on.