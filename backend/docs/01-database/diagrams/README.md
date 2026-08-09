# Diagrams

All diagrams here are in **SVG** format (viewable in any browser or design tool, and directly editable as text).

| File | Description | Used For |
|------|-------------|----------|
| [`erd.svg`](./erd.svg) | Full ERD — all seven tables with every column, PK/FK/UNIQUE markers, and relationships | Understanding the complete schema at a glance, and as a reference while writing migrations |
| [`entity-relationships.svg`](./entity-relationships.svg) | Simplified version — tables only and their relationships (no column-level detail) | Quick architecture explanation for any new team member |
| [`observation-lifecycle.svg`](./observation-lifecycle.svg) | Traces the Observation's lifecycle from SDK to Dashboard | Understanding how data flows through the system, and connecting the database to the bigger architecture |

All diagrams are built directly from the decisions documented in [`../schema/`](../schema) and [`../decisions/`](../decisions) — any schema update must be reflected here as well to maintain consistency.
