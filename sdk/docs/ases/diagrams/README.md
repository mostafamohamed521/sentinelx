# Diagrams

All diagrams are in **SVG** format, organized into four categories.

## `architecture/`

| File | Description | Source |
|------|-------------|--------|
| [`pipeline-architecture.svg`](./architecture/pipeline-architecture.svg) | The full processing pipeline: Framework → Adapter → Event Pipeline → Collector → Builder → Validator → Transport → SentinelX API | [`08-internal-architecture.md`](../08-internal-architecture.md), [`10-transport-layer.md`](../10-transport-layer.md) |
| [`repository-structure.svg`](./architecture/repository-structure.svg) | The full domain-driven package tree, inside and outside `ases/` | [`11-repository-architecture.md`](../11-repository-architecture.md) |

## `sequence/`

| File | Description | Source |
|------|-------------|--------|
| [`event-to-observation-sequence.svg`](./sequence/event-to-observation-sequence.svg) | A concrete CrewAI example: multiple Events collected into one Observation, built, validated, and handed to Transport — with the Agent never waiting | [`08-internal-architecture.md`](../08-internal-architecture.md), [`09-observation-lifecycle.md`](../09-observation-lifecycle.md) |

## `state/`

| File | Description | Source |
|------|-------------|--------|
| [`observation-state.svg`](./state/observation-state.svg) | `STARTED → COLLECTING → COMPLETED → BUILDING → SENDING → ARCHIVED` | [`09-observation-lifecycle.md`](../09-observation-lifecycle.md) |

## `flow/`

| File | Description | Source |
|------|-------------|--------|
| [`customer-integration-journey-flow.svg`](./flow/customer-integration-journey-flow.svg) | The full customer story, from Register to Dashboard | [`05-customer-integration-journey.md`](../05-customer-integration-journey.md) |
| [`framework-ecosystem-flow.svg`](./flow/framework-ecosystem-flow.svg) | Multiple frameworks, each with its own Adapter, converging on one unchanged Core | [`07-agent-framework-ecosystem.md`](../07-agent-framework-ecosystem.md) |

---

All diagrams are built directly from the decisions documented in the numbered files and in [`../adr/`](../adr). Any change to the design must be reflected here as well, to keep the documentation internally consistent.
