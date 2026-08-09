import urllib.error
from unittest.mock import MagicMock, patch

from ases.config.settings import Settings
from ases.shared.constants import HTTP_REQUEST_TIMEOUT_SECONDS
from ases.transport.client import APIClient


def _settings():
    return Settings(api_key="ases_test_key", endpoint="https://api.example.com", environment="production")


def test_send_succeeds_on_202():
    client = APIClient(_settings())
    mock_response = MagicMock()
    mock_response.getcode.return_value = 202
    mock_response.__enter__.return_value = mock_response

    with patch("ases.transport.client.urllib.request.urlopen", return_value=mock_response):
        result = client.send('{"events": []}')

    assert result is True


def test_send_fails_fast_on_4xx_without_retrying():
    client = APIClient(_settings())
    call_count = {"n": 0}

    def raise_http_error(*args, **kwargs):
        call_count["n"] += 1
        raise urllib.error.HTTPError(
            url="https://api.example.com/api/v1/observations",
            code=401,
            msg="Unauthorized",
            hdrs=None,
            fp=None,
        )

    with patch("ases.transport.client.urllib.request.urlopen", side_effect=raise_http_error):
        result = client.send('{"events": []}')

    assert result is False
    assert call_count["n"] == 1  # no retry on a 4xx — it will not succeed on retry


def test_send_applies_the_configured_per_request_timeout():
    # RC-9, RELIABILITY-001: proves the per-request send timeout (a
    # previously-undocumented policy) is actually wired to the real HTTP
    # call, not merely a constant that exists but is never used.
    client = APIClient(_settings())
    mock_response = MagicMock()
    mock_response.getcode.return_value = 202
    mock_response.__enter__.return_value = mock_response

    with patch("ases.transport.client.urllib.request.urlopen", return_value=mock_response) as mock_urlopen:
        client.send('{"events": []}')

    _, kwargs = mock_urlopen.call_args
    assert kwargs["timeout"] == HTTP_REQUEST_TIMEOUT_SECONDS


def test_send_aborts_rather_than_hangs_when_a_single_attempt_times_out(monkeypatch):
    # A slow/unresponsive Backend must not hang the Agent indefinitely — an
    # attempt that exceeds HTTP_REQUEST_TIMEOUT_SECONDS surfaces as a
    # TimeoutError (exactly what urllib raises when its own `timeout=`
    # kwarg is exceeded) and is treated as the existing transient-failure
    # path: retried up to the standard budget, then dropped — never left
    # hanging, never raised into the host Agent.
    monkeypatch.setattr("ases.transport.client.time.sleep", lambda seconds: None)
    client = APIClient(_settings())

    def raise_timeout(*args, **kwargs):
        raise TimeoutError("timed out")

    with patch("ases.transport.client.urllib.request.urlopen", side_effect=raise_timeout) as mock_urlopen:
        result = client.send('{"events": []}')

    assert result is False
    assert mock_urlopen.call_count == 4  # 1 initial attempt + TRANSPORT_MAX_RETRIES (3)


def test_send_retries_on_network_error_then_gives_up(monkeypatch):
    monkeypatch.setattr("ases.transport.client.time.sleep", lambda seconds: None)
    client = APIClient(_settings())

    def raise_url_error(*args, **kwargs):
        raise urllib.error.URLError("connection refused")

    with patch("ases.transport.client.urllib.request.urlopen", side_effect=raise_url_error) as mock_urlopen:
        result = client.send('{"events": []}')

    assert result is False
    assert mock_urlopen.call_count == 4  # 1 initial attempt + TRANSPORT_MAX_RETRIES (3)


def test_headers_use_x_api_key_scheme():
    client = APIClient(_settings())
    headers = client._build_headers()
    assert headers["X-API-Key"] == "ases_test_key"
    assert "Authorization" not in headers
    assert headers["Content-Type"] == "application/json"


def test_headers_request_json_error_responses():
    # Discovered during Phase 8's own live verification: without this, the
    # real Backend's default unauthenticated() handler assumes a browser
    # request and tries to redirect to a non-existent "login" route,
    # producing a 500/503 instead of a clean 401 — masking the very
    # authentication failure this client is supposed to classify (RC-8).
    client = APIClient(_settings())
    headers = client._build_headers()
    assert headers["Accept"] == "application/json"


def test_send_fails_fast_on_401_without_retrying_and_logs_distinctly(caplog):
    client = APIClient(_settings())
    call_count = {"n": 0}

    def raise_http_error(*args, **kwargs):
        call_count["n"] += 1
        raise urllib.error.HTTPError(
            url="https://api.example.com/api/v1/observations",
            code=401,
            msg="Unauthorized",
            hdrs=None,
            fp=None,
        )

    with caplog.at_level("WARNING", logger="ases.transport.client"):
        with patch("ases.transport.client.urllib.request.urlopen", side_effect=raise_http_error):
            result = client.send('{"events": []}')

    assert result is False
    assert call_count["n"] == 1  # no retry -- a bad credential cannot succeed on attempt 2/3
    assert any("authentication failed" in record.message for record in caplog.records)
    assert "ases_test_key" not in caplog.text  # IDENTITY-004: the raw key must never be logged


def test_send_retries_on_429_honoring_retry_after_header(monkeypatch):
    sleep_calls = []
    monkeypatch.setattr("ases.transport.client.time.sleep", lambda seconds: sleep_calls.append(seconds))
    client = APIClient(_settings())

    call_count = {"n": 0}

    def raise_rate_limited(*args, **kwargs):
        call_count["n"] += 1
        raise urllib.error.HTTPError(
            url="https://api.example.com/api/v1/observations",
            code=429,
            msg="Too Many Requests",
            hdrs={"Retry-After": "7"},
            fp=None,
        )

    with patch("ases.transport.client.urllib.request.urlopen", side_effect=raise_rate_limited):
        result = client.send('{"events": []}')

    assert result is False
    assert call_count["n"] == 4  # retried up to the standard budget, unlike 401/403
    assert sleep_calls == [7.0, 7.0, 7.0]  # honored Retry-After, not the exponential backoff


def test_send_retries_on_429_without_retry_after_uses_standard_backoff(monkeypatch):
    sleep_calls = []
    monkeypatch.setattr("ases.transport.client.time.sleep", lambda seconds: sleep_calls.append(seconds))
    client = APIClient(_settings())

    def raise_rate_limited(*args, **kwargs):
        raise urllib.error.HTTPError(
            url="https://api.example.com/api/v1/observations",
            code=429,
            msg="Too Many Requests",
            hdrs=None,
            fp=None,
        )

    with patch("ases.transport.client.urllib.request.urlopen", side_effect=raise_rate_limited):
        result = client.send('{"events": []}')

    assert result is False
    assert sleep_calls == [1, 2, 4]  # falls back to the same backoff as a transient failure
