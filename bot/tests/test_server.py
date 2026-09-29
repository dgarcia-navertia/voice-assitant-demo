import pytest
from fastapi.testclient import TestClient
from twilio.request_validator import RequestValidator

from src.server.main import create_app

AUTH = {"Authorization": "Bearer secret-token"}


@pytest.fixture
def app(settings, php, twilio):
    return create_app(settings, php=php, twilio=twilio, enable_webrtc=False)


@pytest.fixture
def client(app):
    return TestClient(app)


def test_health(client):
    assert client.get("/health").json() == {"status": "ok"}


def test_dial_out_requires_token(client, fake_rest):
    assert client.post("/dial-out", json={"to": "+34611222333"}).status_code == 401
    assert client.post("/dial-out", json={"to": "+34611222333"}, headers={"Authorization": "Bearer bad"}).status_code == 401
    assert fake_rest.created == []


@pytest.mark.parametrize("bad", ["611222333", "+0123", "+34 611 222 333", "abc", "+3461122233344455"])
def test_dial_out_validates_e164(client, fake_rest, bad):
    assert client.post("/dial-out", json={"to": bad}, headers=AUTH).status_code == 422
    assert fake_rest.created == []


def test_dial_out_creates_call_with_stream_twiml(client, fake_rest):
    r = client.post("/dial-out", json={"to": "+34611222333"}, headers=AUTH)
    assert r.status_code == 200
    assert r.json() == {"call_sid": "CAtest0000000000000000000000000001", "status": "queued", "to": "+34611222333"}
    kw = fake_rest.created[0]
    assert kw["to"] == "+34611222333" and kw["from_"] == "+34910000000"
    assert kw["status_callback"] == "https://voice.example.com/twilio/status"
    assert kw["status_callback_event"] == ["initiated", "ringing", "answered", "completed"]
    assert '<Stream url="wss://voice.example.com/ws">' in kw["twiml"]
    assert 'name="phone"' in kw["twiml"] and 'value="+34611222333"' in kw["twiml"]
    assert 'value="outbound"' in kw["twiml"]


def test_dial_out_twilio_error_is_502(client, fake_rest):
    def boom(**kw):
        raise RuntimeError("Twilio said no")

    fake_rest.calls.create = boom
    assert client.post("/dial-out", json={"to": "+34611222333"}, headers=AUTH).status_code == 502


def test_dial_out_unconfigured_is_503(settings, php):
    from src.clients.twilio_client import TwilioGateway

    s = type(settings)(**{**settings.__dict__, "twilio_phone_number": ""})
    app = create_app(s, php=php, twilio=TwilioGateway("", ""), enable_webrtc=False)
    assert TestClient(app).post("/dial-out", json={"to": "+34611222333"}, headers=AUTH).status_code == 503


def test_inbound_twiml(client):
    r = client.post("/twilio/voice", data={"CallSid": "CA1", "From": "+34611222333", "To": "+34910000000"})
    assert r.status_code == 200 and r.headers["content-type"].startswith("application/xml")
    assert '<Stream url="wss://voice.example.com/ws">' in r.text
    assert 'value="+34611222333"' in r.text and 'value="inbound"' in r.text


def test_status_callback_relayed_to_php(client, php_recorder):
    php_recorder.add("POST", "/mcp/calls/status", 200, {"call": {}})
    r = client.post("/twilio/status", data={
        "CallSid": "CA1", "CallStatus": "initiated", "To": "+34611222333", "From": "+34910000000",
        "Direction": "outbound-api"})
    assert r.json()["relayed"] is True
    assert php_recorder.body() == {"call_sid": "CA1", "status": "queued", "to": "+34611222333",
                                   "from": "+34910000000", "direction": "outbound-api"}
    client.post("/twilio/status", data={"CallSid": "CA1", "CallStatus": "completed", "CallDuration": "42"})
    assert php_recorder.body()["duration"] == 42 and php_recorder.body()["status"] == "completed"


def test_status_callback_rejects_unknown_status_and_survives_php_down(client):
    assert client.post("/twilio/status", data={"CallSid": "CA1", "CallStatus": "weird"}).status_code == 422
    r = client.post("/twilio/status", data={"CallSid": "CA1", "CallStatus": "ringing"})  # PHP route missing -> 404
    assert r.status_code == 200 and r.json()["relayed"] is False


def test_signature_validation(settings, php, twilio):
    s = type(settings)(**{**settings.__dict__, "twilio_validate_signature": True})
    client = TestClient(create_app(s, php=php, twilio=twilio, enable_webrtc=False))
    data = {"CallSid": "CA1", "From": "+34611222333", "To": "+34910000000"}
    assert client.post("/twilio/voice", data=data).status_code == 403
    assert client.post("/twilio/voice", data=data, headers={"X-Twilio-Signature": "bad"}).status_code == 403
    sig = RequestValidator("auth-token").compute_signature("https://voice.example.com/twilio/voice", data)
    assert client.post("/twilio/voice", data=data, headers={"X-Twilio-Signature": sig}).status_code == 200


def test_ws_starts_twilio_pipeline_with_call_data(app, monkeypatch):
    """/ws parses the Twilio handshake and hands custom params to the pipeline (mocked)."""
    from pipecat.runner.types import CallData

    import src.server.twilio_routes as routes

    async def fake_parse(ws):
        return "twilio", CallData.model_validate({
            "stream_id": "MZ1", "call_id": "CA" + "2" * 32,
            "body": {"phone": "+34611222333", "direction": "outbound"}})

    seen = {}

    async def fake_run(ws, *, stream_sid, info, **kw):
        seen.update(stream_sid=stream_sid, info=info)
        await ws.close()

    monkeypatch.setattr("pipecat.runner.utils.parse_telephony_websocket", fake_parse)
    monkeypatch.setattr(routes, "run_twilio_call", fake_run)
    with TestClient(app).websocket_connect("/ws"):
        pass
    assert seen["stream_sid"] == "MZ1"
    assert seen["info"].customer_phone == "+34611222333" and seen["info"].direction == "outbound"
