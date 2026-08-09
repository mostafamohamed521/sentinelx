"""
Model 3: framework/version -> CVE lookup.

Fixes vs. the backend's example response:
"No known exploited vulnerability was found for the detected framework version"
at confidence 1.0 was wrong for CrewAI -- nvd_ai_cves_enriched.csv doesn't cover
CrewAI at all (only General LLM Application, AutoGPT, LangChain,
OpenAI Agents SDK, LlamaIndex). "Not covered" and "checked, found nothing" are
different claims and this lookup keeps them distinct.

Both CSVs are OPTIONAL (see config.py's module docstring): if either is
missing, this module falls back to empty tables and CVE_DATA_AVAILABLE is
False -- lookup() then reports every framework as "not checked" (via the
same not-covered response shape, with a note that says so explicitly)
rather than crashing at import time.
"""
import sys

import pandas as pd
import config


def _read_csv_or_empty(path: str, columns: list) -> tuple[pd.DataFrame, bool]:
    try:
        return pd.read_csv(path), True
    except FileNotFoundError:
        print(f"[cve_lookup] {path} not found -- skipping (optional, see config.py). "
              f"CVE lookups will report as \"not checked\" instead of crashing.", file=sys.stderr)
        return pd.DataFrame(columns=columns), False


NVD, _nvd_available = _read_csv_or_empty(config.NVD_AI_CVES_CSV, ['agent_framework', 'base_score', 'cve_id', 'severity'])
KEV, _kev_available = _read_csv_or_empty(config.KEV_CSV, ['cveID'])
CVE_DATA_AVAILABLE = _nvd_available and _kev_available

COVERED_FRAMEWORKS = set(NVD['agent_framework'].dropna().unique()) - {'General LLM Application'}


def lookup(framework: str, agent_version: str = None) -> dict:
    framework_match = framework in COVERED_FRAMEWORKS

    if not framework_match:
        note = (
            f'"{framework}" is not a covered value in nvd_ai_cves_enriched.csv. '
            f'This is "not checked", not "checked and clean" -- do not report as safe.'
        ) if CVE_DATA_AVAILABLE else (
            'CVE dataset not available locally (nvd_ai_cves_enriched.csv / '
            'known_exploited_vulnerabilities.csv not found) -- no framework could be '
            'checked. This is "not checked", not "checked and clean" -- do not report '
            'as safe. See config.py.'
        )

        return {
            'framework_match': False,
            'covered_frameworks': sorted(COVERED_FRAMEWORKS),
            'cve_id': None,
            'exploited_in_wild': None,
            'severity': None,
            'note': note,
        }

    matches = NVD[NVD['agent_framework'] == framework].sort_values('base_score', ascending=False)
    top = matches.iloc[0]

    kev_ids = set(KEV['cveID'])
    exploited = top['cve_id'] in kev_ids

    return {
        'framework_match': True,
        'cve_id': top['cve_id'],
        'base_score': float(top['base_score']),
        'severity': top['severity'],
        'exploited_in_wild': exploited,
        'note': None,
    }


if __name__ == '__main__':
    print("Frameworks with real CVE coverage:", sorted(COVERED_FRAMEWORKS))
    for fw in ['CrewAI', 'LangChain', 'AutoGPT']:
        print(f"\n{fw}:", lookup(fw))
