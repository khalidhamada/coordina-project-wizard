=== Coordina Project Wizard ===
Contributors: coordina
Tags: project management, wizard, templates, onboarding, workflow
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

1. Upload the `coordina-project-wizard` folder to `/wp-content/plugins/`.
2. Activate the main Coordina plugin (https://github.com/khalidhamada/coordina)
3. Activate Coordina Project Wizard.
4. Launch the wizard from the Coordina Projects area.

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

== Upgrade Notice ==

= 0.2.0 =

First separately packaged repo-ready release of the Project Wizard add-on.