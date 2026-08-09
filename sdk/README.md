# ASES — SentinelX Integration Layer (Python SDK)

ASES is the official client-side integration layer connecting AI Agents to
the SentinelX security monitoring platform. It collects execution Events,
normalizes them into a canonical Observation, and delivers them to
SentinelX — without ever blocking or crashing the host Agent.

> **This layer does not monitor your Agent. It standardizes the events your
> Agent allows it to see, then transforms them into a single Observation
> sent to SentinelX.**

## Installation

```bash
pip install ases            # Core + GenericAdapter (Manual API)
pip install ases[crewai]    # + CrewAIAdapter
```

## Quick Start — CrewAI

Matches `04-public-api.md` and `public-api-contract.md` (section 7) verbatim:

```python
from crewai import Crew
from ases import ASES
from ases.adapters import CrewAIAdapter

crew = Crew(...)

ases = ASES(api_key="ases_xxxxxxxxx")
adapter = CrewAIAdapter(crew)
ases.attach(adapter)
ases.start()

crew.kickoff()

ases.stop()
```

Or the "Simple First" three-line shorthand:

```python
from ases import monitor
from ases.adapters import CrewAIAdapter

monitor(CrewAIAdapter(crew))
```

## Quick Start — Custom / Django Agents (Manual API)

For a custom Python Agent with no CrewAI/LangGraph framework underneath it
— including an AI Agent built as part of a Django application:

```python
from ases import ASES, configure
from ases.adapters import GenericAdapter

configure(api_key="ases_xxxxxxxxx")  # or rely on the ASES_API_KEY env var

ases = ASES()
adapter = GenericAdapter()
ases.attach(adapter)
ases.start()

adapter.emit("tool_execution", {"tool": "search", "query": "latest AI security news"})
adapter.complete()

ases.stop()
```

For concurrent Agents (e.g. a Django app serving multiple requests at once),
use `begin_execution()` instead of the single-execution shortcut — see
[`docs/getting-started.md`](./docs/getting-started.md) and
[`examples/django-custom-agent/`](./examples/django-custom-agent/).

## Supported Integration Models (this release)

| Model | Status |
|---|---|
| Callback Integration (`CrewAIAdapter`) | ✅ Shipped — verified against the real `crewai` package's event bus |
| Manual API (`GenericAdapter`) | ✅ Shipped — for any custom Python Agent, including Django-based Agents |
| LangGraph Adapter | 🟡 Structurally supported (`ases.adapters.base.Adapter`), not yet built |
| OpenAI Agents SDK Adapter | 🟡 Structurally supported, not yet built |

See [`docs/architecture.md`](./docs/architecture.md) for why the Core never
has to change when a new framework Adapter is added later.

⚠️ **A framework-scope note, flagged rather than silently resolved:**
`01-overview.md` states "V1 ships with CrewAI only," while
`07-agent-framework-ecosystem.md`'s own evaluation table marks CrewAI,
LangGraph, *and* the OpenAI Agents SDK all as "V1 ✅," and
`13-implementation-roadmap.md` sequences a LangGraph Adapter (Phase 10)
*before* the Public Release milestone. This build ships CrewAI only,
matching the more conservative reading — LangGraph and the OpenAI Agents
SDK are the clear next candidates, and the CrewAI Adapter (~150 lines,
consistent with ADR-002's "200 lines, not 3,000" claim) is direct, working
proof of how mechanical adding them will be.

## Documentation

- [Getting Started](./docs/getting-started.md)
- [Architecture](./docs/architecture.md)
- [Django Integration Example](./examples/django-custom-agent/)
- [CrewAI Integration Example](./examples/crewai-basic/)
- [Design Rationale](./docs/ases/README.md) — curious *why* a decision was made a certain way (e.g. why the retry count is fixed at 3, not configurable — see `docs/ases/adr/ADR-004-non-blocking-async-transport.md`)? This is where the full engineering rationale behind every part of this SDK lives. Not required reading to integrate ASES — start with Getting Started above for that.

## Compliance Notes

This build was audited against the full frozen documentation set
(01–13, all 7 ADRs, all 3 contracts) after initial delivery. Fixes made
during that audit:

- Added `monitor()` / `shutdown()` — public-api-contract.md, section 1 lists
  these as part of "the ENTIRE customer-facing import surface"; only the
  `ASES` class was originally implemented. Both are now thin convenience
  wrappers over `ASES` (see `ases/__init__.py` for the full reconciliation
  of the two API shapes the spec itself describes).
- Restricted `configure()`'s signature to `api_key` only, matching
  environment-configuration.md, section 4 exactly (it previously also
  accepted `endpoint`/`environment`, which aren't in the documented
  contract).
- Built and verified a real `CrewAIAdapter` against `crewai==1.15.10`'s
  actual event bus (`ases/adapters/crewai/adapter.py`) — V1's primary
  framework per every architectural document, previously out of scope.
- Fixed a real bug the CrewAI Adapter's addition introduced: `crewai`
  briefly became a *hard* dependency of `import ases` itself. Fixed with a
  lazy import (`ases/adapters/__init__.py`) — confirmed by a regression
  test that runs in a subprocess with `crewai` import-blocked.
- Confirmed and fixed the two assumptions previously listed here as
  unconfirmed against the real backend: the wire-format JSON schema
  (`ases/observation/models.py` — Context/Events/Metadata, header-nested
  Events, the ten-value canonical `event_type` vocabulary) and the
  authentication mechanism (`ases/transport/client.py` — a single
  `X-API-Key` header, never `Authorization: Bearer`). Both are now
  live-verified against the real Backend, not just documented — see
  `backend/tests/Feature/Observation/SdkObservationComplianceTest.php`.

No unconfirmed backend assumptions remain in this build.

## Development

```bash
pip install -e ".[dev,crewai]"
pytest tests/ -v
```

## License

MIT — see [`LICENSE`](./LICENSE).
