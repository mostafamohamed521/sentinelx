import pytest

from ases.config.settings import Settings, configure_global, get_global_settings
from ases.shared.exceptions import ConfigurationError


def test_resolve_with_explicit_api_key():
    settings = Settings.resolve(api_key="ases_test_key")
    assert settings.api_key == "ases_test_key"
    assert settings.environment == "production"


def test_resolve_missing_api_key_raises(monkeypatch):
    monkeypatch.delenv("ASES_API_KEY", raising=False)
    with pytest.raises(ConfigurationError):
        Settings.resolve()


def test_resolve_from_environment_variable(monkeypatch):
    monkeypatch.setenv("ASES_API_KEY", "ases_env_key")
    settings = Settings.resolve()
    assert settings.api_key == "ases_env_key"


def test_explicit_argument_outranks_environment_variable(monkeypatch):
    monkeypatch.setenv("ASES_API_KEY", "ases_env_key")
    settings = Settings.resolve(api_key="ases_explicit_key")
    assert settings.api_key == "ases_explicit_key"


def test_endpoint_and_environment_fall_back_to_defaults():
    settings = Settings.resolve(api_key="ases_key")
    assert settings.endpoint == "https://api.sentinelx.example.com"
    assert settings.environment == "production"


def test_configure_global_sets_global_settings():
    configure_global(api_key="ases_configured_key")
    settings = get_global_settings()
    assert settings is not None
    assert settings.api_key == "ases_configured_key"


def test_configure_global_can_only_be_called_once():
    configure_global(api_key="ases_once")
    with pytest.raises(ConfigurationError):
        configure_global(api_key="ases_twice")


def test_resolve_for_instance_prefers_explicit_over_configure():
    configure_global(api_key="ases_configured_key")
    settings = Settings.resolve_for_instance(api_key="ases_explicit_key")
    assert settings.api_key == "ases_explicit_key"


def test_resolve_for_instance_uses_configure_when_no_explicit_key():
    configure_global(api_key="ases_configured_key")
    settings = Settings.resolve_for_instance(api_key=None)
    assert settings.api_key == "ases_configured_key"
