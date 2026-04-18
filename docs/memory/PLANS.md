# PLANS.md

## Current Phase
Standalone add-on packaging is prepared; runtime verification and hardening are next.

## Active Queue
1. Verify live WordPress activation and page access with Coordina core enabled.
2. Smoke-test template selection, defaults saving, and end-to-end project creation.
3. Confirm milestone, task, and risk scaffolding under real project templates.
4. Tighten validation and UX only where real runtime testing reveals friction.
5. Prepare a repeatable release and packaging workflow if the add-on will be distributed regularly.

## Open Decisions
- whether template-catalog generation should stay manual or move into a small build utility
- whether future wizard templates should live only in the add-on repo or sync from Coordina core sources
- how far add-on settings should go before a shared settings surface is justified