"""
Example: integrating the ASES SDK into a custom Django-based AI Agent using
the GenericAdapter (Manual API) — the officially supported V1 path for
Agents with no CrewAI/LangGraph framework underneath them
(05-customer-integration-journey.md, section 8; 03-agent-integration-models.md,
Model 4).

Everything under "Your existing business logic" below is a stand-in for
code that already exists in your Django project before ASES integration —
ASES never touches it. The only additions are the `configure()` /
`ASES()` / `GenericAdapter()` setup and the `execution.emit()` /
`execution.complete()` calls placed around your existing logic.
"""

from __future__ import annotations

try:
    from django.http import JsonResponse
except ImportError:  # pragma: no cover - allows the standalone demo below
    # to run without a real Django installation.
    class JsonResponse(dict):  # type: ignore[no-redef]
        def __init__(self, data, status=200):
            super().__init__(data)
            self.status_code = status

from ases import ASES, configure
from ases.adapters import GenericAdapter

# --- Setup: run once, at Django app startup ---------------------------
# In a real project, put this in your app's apps.py -> AppConfig.ready(),
# or at the bottom of settings.py. Never recreate `ases`/`adapter` per
# request — they are long-lived, process-wide objects.

configure(api_key="ases_xxxxxxxxx")  # or rely on the ASES_API_KEY env var

ases = ASES()
adapter = GenericAdapter()
ases.attach(adapter)
ases.start()


# --- The instrumented Django view --------------------------------------

def review_invoices_view(request):
    """Runs a Finance Assistant Task: reviews a month's invoices, generates
    a report, and emails it — instrumented with ASES Observations at every
    meaningful step, exactly the walkthrough in the product-discovery
    session narrative."""

    # A distinct Execution per request keeps concurrent Django requests'
    # Observations from being mixed together.
    execution = adapter.begin_execution()

    try:
        execution.emit("task_started", {"task_name": "review_june_invoices"})

        invoices = _load_invoices_from_finance_system()
        execution.emit(
            "api_call",
            {"system": "finance_system", "action": "list_invoices", "count": len(invoices)},
        )

        for invoice in invoices:
            _review_invoice(invoice, execution)

        report = _generate_report(invoices)
        execution.emit(
            "file_write",
            {"path": "/reports/june_invoices.pdf", "size_bytes": len(report)},
        )

        _send_report_to_manager(report)
        execution.emit("api_call", {"system": "email", "action": "send_report"})

        execution.complete(reason="agent_execution_ended")
        return JsonResponse({"status": "completed", "invoices_reviewed": len(invoices)})

    except Exception:
        # Close the Observation even on failure, so it isn't left open
        # until the 30-second timeout (09-observation-lifecycle.md,
        # section 3) — the Task genuinely ended here, just unsuccessfully.
        execution.complete(reason="agent_execution_ended")
        raise


def _review_invoice(invoice: dict, execution) -> None:
    execution.emit("tool_call", {"tool": "invoice_parser", "invoice_id": invoice["id"]})


# --- Your existing business logic — unchanged, not part of ASES --------
# Stand-ins for code that already exists in your Django project. Replace
# with your actual finance system client, PDF generator, and mailer.

def _load_invoices_from_finance_system() -> list:
    return [
        {"id": "INV-1001", "amount": 4200.00},
        {"id": "INV-1002", "amount": 1875.50},
    ]


def _generate_report(invoices: list) -> bytes:
    return f"Reviewed {len(invoices)} invoices.".encode("utf-8")


def _send_report_to_manager(report: bytes) -> None:
    return None


# --- Standalone demo: run this file directly to see the Observation ----
# JSON that would be sent to SentinelX, without a running Django server or
# a live backend.

if __name__ == "__main__":
    import time
    from unittest.mock import patch

    try:
        import django
        from django.conf import settings

        if not settings.configured:
            settings.configure(DEFAULT_CHARSET="utf-8", USE_TZ=True)
        django.setup()
    except ImportError:
        pass  # Falls back to the lightweight JsonResponse stand-in above.

    captured = []

    def _fake_send(self, serialized):
        captured.append(serialized)
        return True

    with patch("ases.transport.client.APIClient.send", _fake_send):
        response = review_invoices_view(request=None)
        time.sleep(1.5)  # let the background Worker drain the Queue
        ases.stop()

    if hasattr(response, "content"):
        print("View response:", response.content.decode())
    else:
        print("View response:", dict(response))
    print()
    print("Observation JSON that would be sent to SentinelX:")
    for payload in captured:
        print(payload)
