"""
Centralized paths for the SentinelX pipeline.
Everything is relative to this project's root folder, so the code runs
the same on any machine -- no hardcoded /mnt/project or D:/... paths.

Two different kinds of file live under here, with two different failure
behaviors when missing -- see README.md for the full explanation:

  - Model artifacts (VECTORIZER_PATH, CLASSIFIER_PATH): REQUIRED. There is
    no fallback for prompt-injection classification -- if these are
    missing, the service fails to start. Get these from whoever trained
    them (train_injection_classifier.py produces them) and place them at
    the paths below.

  - Dataset CSVs (everything under DATA_DIR): OPTIONAL. taxonomy_matcher.py
    and cve_lookup.py both degrade gracefully to "no data available" if
    their CSV is missing, rather than crashing -- see each module's own
    loader for exactly what "degraded" means for that signal.
"""
import os

PROJECT_ROOT = os.path.dirname(os.path.abspath(__file__))

DATA_DIR = os.path.join(PROJECT_ROOT, "AI Agent Cybersecurity Dataset", "data")
CORE_DIR = os.path.join(DATA_DIR, "core")
THREAT_INTEL_DIR = os.path.join(DATA_DIR, "threat_intelligence")
VULN_DIR = os.path.join(DATA_DIR, "vulnerabilities")

PIPELINE_DIR = os.path.join(PROJECT_ROOT, "pipeline")
os.makedirs(PIPELINE_DIR, exist_ok=True)

# Dataset file paths -- OPTIONAL, see module docstring above.
HF_PROMPT_INJECTIONS_CSV = os.path.join(THREAT_INTEL_DIR, "hf_prompt_injections.csv")
NVD_AI_CVES_CSV = os.path.join(VULN_DIR, "nvd_ai_cves_enriched.csv")
KEV_CSV = os.path.join(VULN_DIR, "known_exploited_vulnerabilities.csv")
AI_AGENT_CORE_THREATS_CSV = os.path.join(CORE_DIR, "ai_agent_core_threats.csv")
AGENT_ATTACK_TAXONOMY_CSV = os.path.join(CORE_DIR, "agent_attack_taxonomy.csv")

# Model artifact paths -- REQUIRED, see module docstring above.
VECTORIZER_PATH = os.path.join(PIPELINE_DIR, "injection_vectorizer.joblib")
CLASSIFIER_PATH = os.path.join(PIPELINE_DIR, "injection_classifier.joblib")

# Identifies which pipeline/model produced a given response -- stored verbatim
# in predictions.model_version on the Backend. Bump this whenever the scoring
# logic, thresholds, or underlying model artifacts change meaningfully, so a
# stored Prediction can always be traced back to the logic that produced it.
MODEL_VERSION = "sentinelx-ml-1.2.0"

# risk_score -> verdict decision boundaries. An explicitly flagged engineering
# default, not a frozen business rule -- see docs/ADR-ml-001-verdict-thresholds.md
# for the full reasoning (mirrors backend/docs/06-alert/adrs/ADR-001's own
# treatment of SeverityMapper's thresholds). Kept here, as two named constants,
# so recalibrating either boundary later is a one-line change, not a hunt
# through run_pipeline.py's scoring logic.
SAFE_SUSPICIOUS_BOUNDARY = 50
SUSPICIOUS_MALICIOUS_BOUNDARY = 85