"""Regression tests for three compliance fixes made against the frozen
ASES documentation set:

1. monitor()/shutdown() exist, matching public-api-contract.md section 1's
   literal list of the "ENTIRE customer-facing import surface."
2. configure()'s signature matches environment-configuration.md section 4
   exactly: `api_key` only.
3. `import ases`, and `from ases.adapters import GenericAdapter`, never
   require the optional `crewai` package — the Core's zero-dependency
   promise (ADR-007) must hold even though CrewAIAdapter now ships in the
   same package.
"""

import inspect
import subprocess
import sys
import time
from unittest.mock import patch

import ases
from ases import ASES, configure, monitor, shutdown


def test_monitor_and_shutdown_are_exported():
    assert hasattr(ases, "monitor")
    assert hasattr(ases, "shutdown")
    assert "monitor" in ases.__all__
    assert "shutdown" in ases.__all__


def test_configure_signature_matches_environment_configuration_contract():
    signature = inspect.signature(configure)
    assert list(signature.parameters.keys()) == ["api_key"]


def test_monitor_creates_attaches_and_starts_a_default_instance():
    from ases.adapters import GenericAdapter

    sent_payloads = []

    def fake_send(self, serialized):
        sent_payloads.append(serialized)
        return True

    with patch("ases.transport.client.APIClient.send", fake_send):
        # Reset module-level singleton left over from any earlier test.
        ases._default_instance = None

        adapter = GenericAdapter()
        instance = monitor(adapter, api_key="ases_test_key")

        assert isinstance(instance, ASES)
        assert instance is ases._default_instance

        adapter.emit("tool_execution", {"tool": "search"})
        adapter.complete()

        time.sleep(1.5)
        shutdown()

    assert len(sent_payloads) == 1
    assert ases._default_instance is None  # shutdown() clears the singleton


def test_shutdown_is_a_no_op_if_monitor_never_called():
    ases._default_instance = None
    shutdown()  # must not raise
    assert ases._default_instance is None


def test_import_ases_does_not_require_crewai():
    """Runs in a fresh subprocess with crewai's import path blocked, so this
    genuinely proves the Core has no hard dependency on it — not just that
    it happens to already be imported/cached in this test process."""
    script = (
        "import sys\n"
        "class Block:\n"
        "    def find_spec(self, name, path=None, target=None):\n"
        "        if name == 'crewai' or name.startswith('crewai.'):\n"
        "            raise ImportError('blocked for this test')\n"
        "        return None\n"
        "sys.meta_path.insert(0, Block())\n"
        "import ases\n"
        "from ases.adapters import GenericAdapter\n"
        "print('OK')\n"
    )
    result = subprocess.run([sys.executable, "-c", script], capture_output=True, text=True, timeout=30)
    assert result.returncode == 0, result.stderr
    assert "OK" in result.stdout


def test_crewai_adapter_gives_a_clear_error_without_crewai_installed():
    script = (
        "import sys\n"
        "class Block:\n"
        "    def find_spec(self, name, path=None, target=None):\n"
        "        if name == 'crewai' or name.startswith('crewai.'):\n"
        "            raise ImportError('blocked for this test')\n"
        "        return None\n"
        "sys.meta_path.insert(0, Block())\n"
        "from ases.adapters import CrewAIAdapter\n"
    )
    result = subprocess.run([sys.executable, "-c", script], capture_output=True, text=True, timeout=30)
    assert result.returncode != 0
    assert "pip install ases[crewai]" in result.stderr
