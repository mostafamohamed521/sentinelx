"""Shared pytest fixtures.

configure() enforces "at most once per process" via module-level state in
ases.config.settings — this fixture resets that state before and after every
test so tests remain independent of execution order.
"""

import pytest


@pytest.fixture(autouse=True)
def _reset_ases_global_settings():
    import ases.config.settings as settings_module

    settings_module._global_settings = None
    settings_module._configure_called = False
    yield
    settings_module._global_settings = None
    settings_module._configure_called = False
