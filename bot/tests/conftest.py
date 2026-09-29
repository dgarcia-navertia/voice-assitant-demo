import json

import httpx
import pytest

from src.clients.php_api import PhpApiClient
from src.clients.twilio_client import TwilioGateway
from src.config.settings import Settings


class FakeRest:
    """Stands in for twilio.rest.Client. Records calls, never touches the network."""

    def __init__(self):
        self.created: list[dict] = []
        self.updates: list[tuple[str, dict]] = []

        outer = self

        class _Calls:
            def create(self, **kw):
                outer.created.append(kw)
                return type("Call", (), {"sid": "CAtest0000000000000000000000000001", "status": "queued"})()

            def __call__(self, sid):
                class _One:
                    def update(_self, **kw):
                        outer.updates.append((sid, kw))

                return _One()

        self.calls = _Calls()


@pytest.fixture
def settings():
    return Settings(
        internal_api_token="secret-token",
        php_api_base_url="http://php",
        public_base_url="https://voice.example.com",
        twilio_account_sid="ACtest",
        twilio_auth_token="auth-token",
        twilio_phone_number="+34910000000",
        handoff_phone_number="+34600999888",
        twilio_validate_signature=False,
        google_api_key="g", deepgram_api_key="d", eleven_labs_api_key="e",
    )


@pytest.fixture
def fake_rest():
    return FakeRest()


@pytest.fixture
def twilio(fake_rest):
    return TwilioGateway("ACtest", "auth-token", rest_client=fake_rest)


class PhpRecorder:
    """httpx.MockTransport handler with routes registered per test."""

    def __init__(self):
        self.requests: list[httpx.Request] = []
        self.routes: dict[tuple[str, str], object] = {}

    def add(self, method, path, status=200, body=None):
        self.routes[(method, path)] = (status, body if body is not None else {})

    def __call__(self, request: httpx.Request) -> httpx.Response:
        self.requests.append(request)
        route = self.routes.get((request.method, request.url.path))
        if route is None:
            return httpx.Response(404, json={"error": "not found"})
        status, body = route
        return httpx.Response(status, json=body)

    def body(self, idx=-1):
        return json.loads(self.requests[idx].content or b"{}")


@pytest.fixture
def php_recorder():
    return PhpRecorder()


@pytest.fixture
def php(php_recorder):
    return PhpApiClient("http://php", "secret-token", transport=httpx.MockTransport(php_recorder))


@pytest.fixture(autouse=True)
def _reset_handoff_cache():
    from src.tools import handlers
    handlers.reset_handoff_cache()
    yield
    handlers.reset_handoff_cache()
