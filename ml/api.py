"""
HTTP API for the agent risk pipeline.

POST /analyze  -- body: {"observation": {...}, "analysis_options": {...}}
                  matches the frozen contract (backend/docs/05-analysis/
                  04-ml-client-contract.md) and the Backend's own MLClient --
                  see integration audit CONTRACT-001/CONTRACT-004.
                  analysis_options.debug=true returns the debug tier (full
                  provenance); default returns the public tier (evidence_type/
                  description/reference/confidence only).

GET  /health   -- liveness check.

Authorization: Bearer <ML_SERVICE_TOKEN> is required on /analyze -- see
SECURITY-004. A shared secret appropriate for an internal service boundary,
not a full OAuth/JWT scheme, which would be disproportionate here.
"""
import os
import sys
import time
import traceback
import uuid
from typing import Any, Dict

from fastapi import FastAPI, Header, HTTPException, Request
from pydantic import BaseModel, Field

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from run_pipeline import run

app = FastAPI(title="Agent Risk Pipeline API", version="1.2.0")

ML_SERVICE_TOKEN = os.environ.get("ML_SERVICE_TOKEN")


class AnalyzeRequest(BaseModel):
    observation: Dict[str, Any]
    analysis_options: Dict[str, Any] = Field(default_factory=dict)


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/analyze")
def analyze(payload: AnalyzeRequest, request: Request, authorization: str | None = Header(default=None)):
    # See SECURITY-004: matches the Backend's own MLClient, which now fails
    # loudly at first use if ML_SERVICE_TOKEN is unset, rather than silently
    # omitting the header. A missing/wrong token here is rejected before the
    # pipeline ever runs.
    if ML_SERVICE_TOKEN:
        expected = f"Bearer {ML_SERVICE_TOKEN}"
        if authorization != expected:
            raise HTTPException(status_code=401, detail="Missing or invalid bearer token.")
    # Reused from the Backend's own X-Request-Id header when present (see
    # MLClient::analyze()) so a single Backend->ML call reads as one
    # correlated line in both services' logs. Generated locally when absent,
    # so this service is never correlation-blind even when called by
    # something other than this project's Backend. See Phase 1.5 / OBS-001.
    request_id = request.headers.get("x-request-id") or str(uuid.uuid4())
    started_at = time.monotonic()

    obs = payload.observation

    if "context" not in obs:
        raise HTTPException(status_code=400, detail="observation.context is required")
    # FIXED (bug #3): previously only checked that the key existed, not that it
    # was actually a dict. A request like "context": "oops" or "context": null
    # passed validation and then blew up downstream (e.g. context.get(...)) with
    # an unhelpful AttributeError. Now the type is validated up front.
    if not isinstance(obs["context"], dict):
        raise HTTPException(status_code=400, detail="observation.context must be an object")
    if "events" not in obs or not isinstance(obs["events"], list):
        raise HTTPException(status_code=400, detail="observation.events must be a list")
    if "framework" not in obs["context"]:
        raise HTTPException(status_code=400, detail="observation.context.framework is required")

    for i, event in enumerate(obs["events"]):
        if "header" not in event or "event_type" not in event.get("header", {}):
            raise HTTPException(status_code=400, detail=f"events[{i}].header.event_type is required")
        if "payload" not in event:
            raise HTTPException(status_code=400, detail=f"events[{i}].payload is required")
        if not isinstance(event["payload"], dict):
            raise HTTPException(status_code=400, detail=f"events[{i}].payload must be an object")

    try:
        result = run(obs)
        if result is None:
            # Defensive guard: if run() ever again returns None (e.g. a future edit
            # reintroduces a code path with no return statement), fail loudly with a
            # clear 500 instead of crashing on result["public"] below with an opaque
            # TypeError that isn't even caught by this except block.
            raise RuntimeError("pipeline run() returned no result")

        debug = bool(payload.analysis_options.get("debug", False))
        # FIXED (bug #2): this used to sit *outside* the try/except, so if `result`
        # was ever None (as it was while bug #1 was present) this line raised
        # `TypeError: 'NoneType' object is not subscriptable` completely uncaught --
        # bypassing the structured "pipeline error" response entirely and surfacing
        # as a bare, undocumented Internal Server Error. It now lives inside the
        # try block so any failure here is caught and reported consistently.
        response = result["debug"] if debug else result["public"]

        # This service previously logged nothing at all on success (OBS-002)
        # — the only way to know a call succeeded was to inspect the
        # Backend's database. duration_ms lets a slow call be spotted from
        # the log alone, without cross-referencing timestamps by hand.
        duration_ms = round((time.monotonic() - started_at) * 1000, 1)
        print(
            f"[analyze] request_id={request_id} verdict={result['public'].get('verdict')} "
            f"risk_score={result['public'].get('risk_score')} duration_ms={duration_ms}",
            file=sys.stdout,
        )

        return response
    except Exception as e:
        # Full traceback goes to the server console (visible in your uvicorn
        # terminal) -- the caller only ever sees the exception type, not
        # internals.
        duration_ms = round((time.monotonic() - started_at) * 1000, 1)
        print(f"=== Pipeline error === request_id={request_id} duration_ms={duration_ms}", file=sys.stderr)
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"pipeline error: {type(e).__name__}") from e
