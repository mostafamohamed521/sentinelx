# SentinelX ML Service

FastAPI service implementing the agent risk pipeline described in
`backend/docs/05-analysis/04-ml-client-contract.md`. Three signals are
combined into one risk score: a prompt-injection classifier, a taxonomy
matcher (rule-based + embedding retrieval), and a CVE/framework lookup.

## Running it

```bash
cd ml
python -m venv .venv          # if you haven't already
.venv\Scripts\activate         # Windows
pip install -r requirements.txt   # if present, otherwise: fastapi uvicorn pandas numpy scikit-learn joblib

set ML_SERVICE_TOKEN=your-shared-secret   # required -- see Authentication below
uvicorn api:app --reload
```

The service listens on `http://127.0.0.1:8000` by default. Check it's up with:

```bash
curl http://127.0.0.1:8000/health
```

## What files this needs, and what's actually required

Everything under `config.py` falls into one of two categories. Getting this
distinction right is the whole point of this section -- most of what looks
like a hard requirement at first glance (the dataset CSVs) is not.

### Required: the two trained model artifacts

```
ml/pipeline/injection_vectorizer.joblib
ml/pipeline/injection_classifier.joblib
```

These back the prompt-injection classifier (`classify_injection()` in
`run_pipeline.py`). There is no fallback for this signal -- if either file
is missing, `joblib.load()` raises and the service fails to start. Get
these from whoever trained them (`train_injection_classifier.py` is what
produces them, from `hf_prompt_injections.csv`), and place them at the
paths above. **If you already have these two files, that's all you need to
run the full service** -- nothing else below is required to start it.

> You may see an `InconsistentVersionWarning` from scikit-learn when these
> load (e.g. "unpickling from version 1.8.0 when using 1.9.0"). That's
> informational, not an error -- the objects still load and work. If it
> ever starts producing genuinely wrong predictions, the fix is to
> re-`joblib.dump()` the artifacts using the `scikit-learn` version this
> service actually has installed.

### Optional: the five dataset CSVs

```
ml/AI Agent Cybersecurity Dataset/data/core/ai_agent_core_threats.csv
ml/AI Agent Cybersecurity Dataset/data/core/agent_attack_taxonomy.csv
ml/AI Agent Cybersecurity Dataset/data/vulnerabilities/nvd_ai_cves_enriched.csv
ml/AI Agent Cybersecurity Dataset/data/vulnerabilities/known_exploited_vulnerabilities.csv
ml/AI Agent Cybersecurity Dataset/data/threat_intelligence/hf_prompt_injections.csv
```

None of these are required to start or run the service. `taxonomy_matcher.py`
and `cve_lookup.py` both check for their CSVs at import time and degrade
gracefully if missing -- a clear one-line warning is printed to stderr for
each missing file, naming exactly what's disabled:

| Missing file | What degrades | What still works |
|---|---|---|
| `ai_agent_core_threats.csv` / `agent_attack_taxonomy.csv` | The embedding-based taxonomy match (`taxonomy_matcher.match()`) returns `matched: False` with a note explaining the dataset isn't available, instead of a real match | The dataset-independent sensitive-path rule (`/etc/shadow`, `.ssh/`, `.aws/credentials`, etc. in `SENSITIVE_PATH_PATTERNS`) still matches at full confidence -- it never touches these CSVs |
| `nvd_ai_cves_enriched.csv` / `known_exploited_vulnerabilities.csv` | `cve_lookup.lookup()` reports every framework as "not checked" (never as "checked, found nothing") | Nothing else depends on this signal |
| `hf_prompt_injections.csv` | Nothing at request time -- this file is only read by `train_injection_classifier.py`, the offline training script, never by the running service | Everything |

In every degraded case, the response still returns a real `verdict`/
`risk_score`/`confidence` -- it's just computed from fewer signals, exactly
the same way it would be for, say, a framework the CVE dataset genuinely
doesn't cover. Nothing crashes, and nothing silently reports "safe" for
data it never actually checked (see `cve_lookup.py`'s own docstring on why
that distinction matters).

If you want the full taxonomy-matching and CVE-lookup signals, ask whoever
has the datasets for the five CSVs above and place them at those exact
paths -- `config.py` names every path explicitly, no other configuration
is needed.

## Authentication

`/analyze` requires a bearer token matching `ML_SERVICE_TOKEN` in this
service's own environment (see `docs/ADR-ml-001-verdict-thresholds.md`'s
sibling decision, SECURITY-004, in `backend/docs/05-analysis/
04-ml-client-contract.md`). If `ML_SERVICE_TOKEN` is unset here, the check
is skipped entirely (open) -- set it to require and verify a token. The
Backend's own `MLClient` always sends one and will refuse to make the call
at all if it has none configured on its side, so for local Backend<->ML
testing, set the same value in both `backend/.env`'s `ML_SERVICE_TOKEN` and
this service's environment.

## Endpoints

```
GET  /health    -- liveness check, no auth required
POST /analyze    -- {"observation": {...}, "analysis_options": {...}}
                    analysis_options.debug=true returns the full-provenance
                    debug tier; default returns the public tier.
```

## Verdict thresholds

`risk_score` -> `verdict` mapping (`SAFE` / `SUSPICIOUS` / `MALICIOUS`) and
the `confidence` heuristic are both explicitly flagged engineering
defaults, not calibrated against real labeled data yet -- see
`docs/ADR-ml-001-verdict-thresholds.md` for the exact numbers and the
reasoning, and `config.py` for where to change them.
