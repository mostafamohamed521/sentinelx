"""RC-7 / Phase 7 live verification fixture — NOT a unit test.

Runs the real, installed `ases` SDK package (sdk/ases/, `pip install -e
./sdk`) end to end — GenericAdapter -> Collector -> Builder -> Validator ->
Transport Queue -> Worker -> Serializer — and prints the exact JSON string
the real Worker would have POSTed to the Backend. Only the final network
call itself is mocked (captured instead of sent); every SDK component that
decides the wire-format shape runs for real.

Invoked by backend/tests/Feature/Observation/SdkObservationComplianceTest.php
via a subprocess, so that test proves the real SDK's real output is accepted
by the real, current ObservationValidator.php — not a hand-written fixture
that merely resembles what the SDK is documented to produce.
"""

import time
from unittest.mock import patch

from ases import ASES
from ases.adapters import GenericAdapter

captured = {}


def fake_send(self, serialized):
    captured["json"] = serialized
    return True


with patch("ases.transport.client.APIClient.send", fake_send):
    sdk = ASES(api_key="ases_live_verification_key")
    adapter = GenericAdapter()
    sdk.attach(adapter)
    sdk.start()

    adapter.emit("tool_execution", {"tool": "search", "query": "latest AI security news"})
    adapter.emit("custom", {"model": "gpt-4o"})
    adapter.complete()

    time.sleep(1.5)  # let the background Worker drain the Queue
    sdk.stop()

print(captured["json"])
