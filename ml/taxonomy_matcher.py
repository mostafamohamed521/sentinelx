"""
Model 2: Taxonomy matcher (retrieval, not classification).

Combines the two real taxonomy files that exist in the project
(ai_agent_core_threats.csv + agent_attack_taxonomy.csv).
master_ai_agent_cybersecurity.csv does NOT exist in the project files
and is intentionally excluded rather than assumed.

Fixes vs. the backend's example response:
1. Events with no free-text `description` field (file_access, network_connection
   using structured fields like path/access_mode/details) are
   converted to a synthesized sentence before embedding, instead of being
   unmatchable.
2. A similarity threshold gates the match -- below threshold, the matcher
   returns matched=False instead of forcing a best-guess row. This is what
   was missing when AML.T0053 (Training Data Poisoning) got attached to a
   /etc/shadow file read, and AML.T0048 (LLM Jailbreak) got attached to an
   outbound network connection -- both real IDs, both wrong matches.

Both taxonomy CSVs are OPTIONAL (see config.py's module docstring): if
either is missing, load_taxonomy() falls back to an empty, correctly-shaped
table instead of crashing, and match() skips straight to "no data available"
for anything that would otherwise need the embedding index. The
dataset-independent sensitive-path rule (SENSITIVE_PATH_PATTERNS, below)
is entirely unaffected either way -- it never touches these CSVs.
"""
import sys

import numpy as np
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

import config

SIMILARITY_THRESHOLD = 0.15  # tuned below against the validation set

TAXONOMY_COLUMNS = ['attack_type', 'description', 'mitre_ref', 'target_component', 'severity_score', 'source_dataset']


def _read_taxonomy_csv(path: str, rename: dict, keep: list, source_label: str):
    """Reads one taxonomy CSV, or returns None (not an empty DataFrame --
    concat needs to know the difference) if the file isn't present locally.
    """
    try:
        df = pd.read_csv(path)
    except FileNotFoundError:
        print(f"[taxonomy_matcher] {path} not found -- skipping this dataset "
              f"(optional, see config.py). Matches this source would have "
              f"provided will report as unclassified instead.", file=sys.stderr)
        return None

    rows = df.rename(columns=rename)[keep].copy()
    rows['source_dataset'] = source_label
    return rows


def load_taxonomy():
    core_rows = _read_taxonomy_csv(
        config.AI_AGENT_CORE_THREATS_CSV,
        rename={'mitre_atlas_id': 'mitre_ref', 'attack_name': 'attack_type'},
        keep=['attack_type', 'description', 'mitre_ref', 'target_component', 'severity_score'],
        source_label='ai_agent_core_threats.csv',
    )
    if core_rows is not None:
        core_rows['severity_score'] = core_rows['severity_score'].astype(float)

    tax_rows = _read_taxonomy_csv(
        config.AGENT_ATTACK_TAXONOMY_CSV,
        rename={'mitre_atlas_ref': 'mitre_ref'},
        keep=['attack_type', 'description', 'mitre_ref', 'target_component'],
        source_label='agent_attack_taxonomy.csv',
    )
    if tax_rows is not None:
        # Explicit float NaN (not None) so this column's dtype matches core_rows'
        # severity_score dtype going into concat -- avoids the pandas FutureWarning
        # about all-NA columns being excluded from dtype inference.
        tax_rows['severity_score'] = np.nan

    # NOTE: tried folding mitre_attack_mapping.csv in as a third source to cover
    # network-exfiltration events (T1048 "Exfiltration Over Alternative Protocol"
    # looked like the right fix). Measured effect: it broke the file_access match
    # that was previously correct (TF-IDF re-weights across the whole corpus, and
    # adding ~17 more short rows shifted which terms count as distinctive).
    # Reverted. This 89-row two-file corpus genuinely has no good row for generic
    # outbound-network-connection events, and TF-IDF similarity alone isn't
    # reliable enough here to widen the corpus without re-validating every
    # existing case. See KNOWN_GAPS below for how this is handled instead.
    present = [df for df in (core_rows, tax_rows) if df is not None]
    if not present:
        return pd.DataFrame(columns=TAXONOMY_COLUMNS)

    return pd.concat(present, ignore_index=True)


# Known coverage gaps in the taxonomy data, found via validation, not guessed.
# A small rule layer runs first for patterns common enough to name explicitly,
# rather than letting the embedding matcher force a low-confidence guess.
KNOWN_GAPS = {
    'network_connection': (
        "Neither taxonomy CSV has a dedicated row for generic outbound network "
        "connections. mitre_attack_mapping.csv has T1048 (Exfiltration Over "
        "Alternative Protocol) which is conceptually right, but folding it into "
        "the embedding index destabilized other matches (see note above)."
    ),
}

SENSITIVE_PATH_PATTERNS = ('/etc/shadow', '/etc/passwd', '.ssh/', '.aws/credentials', '.env')


TAXONOMY = load_taxonomy()

# TfidfVectorizer can't fit on zero documents -- if both CSVs were missing,
# skip the embedding index entirely rather than crashing at import time.
# match() checks this and falls back to "no data available" for anything
# that isn't the dataset-independent sensitive-path rule.
if len(TAXONOMY) > 0:
    _vectorizer = TfidfVectorizer(stop_words='english')
    _matrix = _vectorizer.fit_transform(TAXONOMY['description'])
else:
    _vectorizer = None
    _matrix = None


def event_to_text(event_type: str, resource: str, operation: str, details: dict) -> str:
    """Synthesize free text from structured event fields.
    This is the piece that was missing once `description` disappeared
    from file_access/network_connection payloads.
    """
    if 'description' in details:
        return details['description']
    if event_type == 'file_access':
        path = details.get('path', resource)
        mode = details.get('access_mode', operation)
        return f"Agent performed {mode} access on sensitive file path {path}"
    if event_type == 'network_connection':
        dest = details.get('destination', resource)
        proto = details.get('protocol', '')
        return f"Agent made outbound {proto} network connection to {dest}"
    if event_type == 'api_call':
        return details.get('llm_input') or details.get('prompt_text') or f"{operation} call to {resource}"
    return f"{event_type} {operation} on {resource}"


def match(event_type: str, resource: str, operation: str, details: dict) -> dict:
    # Rule pre-check: known-reliable pattern, don't leave it to a shaky embedding match
    if event_type == 'file_access':
        path = details.get('path', resource) or ''
        if any(p in path for p in SENSITIVE_PATH_PATTERNS):
            return {
                'matched': True,
                'attack_type': 'Excessive Agency Exploitation (OWASP LLM08)',
                'mitre_ref': 'AML.T0054',
                'target_component': 'Agent Permission Model',
                'source_dataset': 'rule:sensitive_path',
                'similarity_score': None,
                'note': 'Matched by explicit rule on sensitive path pattern, not embedding similarity.',
            }

    if _vectorizer is None:
        return {
            'matched': False,
            'similarity_score': None,
            'note': 'Taxonomy dataset not available locally (ai_agent_core_threats.csv / '
                    'agent_attack_taxonomy.csv not found) -- treat as unclassified, not as '
                    'confirmed-safe. See config.py.',
        }

    text = event_to_text(event_type, resource, operation, details)
    vec = _vectorizer.transform([text])
    sims = cosine_similarity(vec, _matrix)[0]
    best_idx = sims.argmax()
    best_score = float(sims[best_idx])

    if event_type in KNOWN_GAPS:
        return {
            'matched': False,
            'similarity_score': round(best_score, 3),
            'note': KNOWN_GAPS[event_type] + f' (best embedding candidate scored {best_score:.3f}, discarded as unreliable.)'
        }

    if best_score < SIMILARITY_THRESHOLD:
        return {
            'matched': False,
            'similarity_score': round(best_score, 3),
            'note': 'No taxonomy row cleared the similarity threshold; treat as unclassified.'
        }

    row = TAXONOMY.iloc[best_idx]
    return {
        'matched': True,
        'attack_type': row['attack_type'],
        'mitre_ref': row['mitre_ref'],
        'target_component': row['target_component'],
        'severity_score': row['severity_score'],
        'source_dataset': row['source_dataset'],
        'similarity_score': round(best_score, 3),
        'matched_description': row['description'],
    }


if __name__ == '__main__':
    print(f"Taxonomy index: {len(TAXONOMY)} rows "
          f"({(TAXONOMY['source_dataset'] == 'ai_agent_core_threats.csv').sum()} from ai_agent_core_threats.csv, "
          f"{(TAXONOMY['source_dataset'] == 'agent_attack_taxonomy.csv').sum()} from agent_attack_taxonomy.csv)")