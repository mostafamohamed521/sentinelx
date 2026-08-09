# Example — Django Custom Agent Integration

This example instruments a Django-based "Finance Assistant" Agent — the
same scenario walked through narratively in the product-discovery sessions
(a Django view that reviews invoices, reads from a finance system, writes a
report, and emails it to a manager).

It uses the `GenericAdapter` (Manual API) — the officially supported V1
integration path for custom Python Agents with no CrewAI/LangGraph
framework underneath them (`03-agent-integration-models.md`, Model 4).

## What This Demonstrates

- Configuring ASES once at Django app startup, not per-request.
- Using `begin_execution()` so concurrent Django requests never mix their
  Observations together.
- Emitting Events at every meaningful point: an outbound API call, a Tool
  call, a file write, and a second outbound API call — without touching any
  of the surrounding business logic.
- Closing the Observation on both the success and failure paths.

## Files

- [`example_integration.py`](./example_integration.py) — a Django view
  module, ready to be wired into `urls.py` in a real project.

## Running It Standalone (Without a Real Django Project)

The bottom of `example_integration.py` includes a `if __name__ == "__main__"`
block that calls the view function directly with a stub Django-like request,
against a mocked SentinelX endpoint — so you can see the full Observation
JSON without needing a running Django server or a live SentinelX backend:

```bash
python examples/django-custom-agent/example_integration.py
```
