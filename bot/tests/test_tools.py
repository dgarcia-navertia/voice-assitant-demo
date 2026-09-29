from datetime import datetime
from zoneinfo import ZoneInfo

import pytest

from src.tools import TOOL_HANDLERS, build_tools_schema, handlers
from src.tools.context import ToolContext

NOW = datetime(2026, 9, 29, 11, 8, tzinfo=ZoneInfo("Europe/Madrid"))
SID = "CA" + "0" * 32


@pytest.fixture
def ctx(settings, php, twilio):
    return ToolContext(settings=settings, php=php, twilio=twilio, call_sid=SID,
                       customer_phone="+34611222333", direction="outbound", _now=NOW)


def test_schema_exposes_all_tools():
    names = {t.name for t in build_tools_schema().standard_tools}
    assert names == set(TOOL_HANDLERS) == {
        "get_company_info", "check_availability", "book_appointment", "commercial_handoff", "end_call"}


async def test_company_info(ctx, php_recorder):
    php_recorder.add("GET", "/mcp/stores", body={"stores": [
        {"id": 1, "name": "Navertia Ruzafa", "address": "C/ Ruzafa 1", "phone_number": "960000001",
         "schedule": {"mon_to_friday": "09:00-20:00", "saturday": None}}]})
    res = await handlers.get_company_info(ctx)
    assert res["ok"] and res["stores"][0]["name"] == "Navertia Ruzafa"
    assert res["stores"][0]["schedule_saturday"] == "cerrado"
    assert php_recorder.requests[0].headers["authorization"] == "Bearer secret-token"


async def test_company_info_php_down(ctx):
    # no route registered on the mock transport -> 404 from PHP
    res = await handlers.get_company_info(ctx)
    assert res["ok"] is False


async def test_check_availability_dedupes_and_filters_past(ctx, php_recorder):
    php_recorder.add("GET", "/mcp/availability", body={"date": "2026-09-29", "slots": [
        {"time": "10:00", "commercial_id": 2}, {"time": "12:00", "commercial_id": 2},
        {"time": "12:00", "commercial_id": 3}, {"time": "16:30", "commercial_id": 2}]})
    res = await handlers.check_availability(ctx, store_id=1, date="2026-09-29")
    assert res["available_times"] == ["12:00", "16:30"]  # 10:00 already passed


async def test_check_availability_validation(ctx):
    assert (await handlers.check_availability(ctx, store_id="x", date="2026-10-01"))["ok"] is False
    assert (await handlers.check_availability(ctx, store_id=1, date="mañana"))["ok"] is False
    assert (await handlers.check_availability(ctx, store_id=1, date="2026-01-01"))["ok"] is False


async def test_book_creates_new_client_and_appointment(ctx, php_recorder):
    php_recorder.add("GET", "/mcp/clients/by-phone", 404, {"error": "Client not found"})
    php_recorder.add("POST", "/mcp/clients", 201, {"client": {"id": 7, "client_name": "Ana"}})
    php_recorder.add("POST", "/mcp/appointments", 201, {"id": 55, "store_name": "Navertia Ruzafa"})
    res = await handlers.book_appointment(ctx, client_name="Ana Pérez", store_id=1,
                                          date="2026-10-01", time="10:30", client_email="ana@x.es")
    assert res["ok"] and res["appointment_id"] == 55 and res["client_created"] is True
    create_client_body = php_recorder.body(1)
    assert create_client_body["client_phone"] == "+34611222333"
    assert create_client_body["client_email"] == "ana@x.es"
    assert php_recorder.body(2) == {"store_id": 1, "client_id": 7, "starts_at": "2026-10-01 10:30:00"}


async def test_book_reuses_existing_client_without_email(ctx, php_recorder):
    php_recorder.add("GET", "/mcp/clients/by-phone", body={"client": {"id": 3}})
    php_recorder.add("POST", "/mcp/appointments", 201, {"id": 9})
    res = await handlers.book_appointment(ctx, client_name="Luis", store_id=2, date="2026-10-02", time="9:00")
    assert res["ok"] and res["client_created"] is False and res["time"] == "09:00"
    assert not any(r.method == "POST" and r.url.path == "/mcp/clients" for r in php_recorder.requests)


async def test_book_slot_taken_and_validation(ctx, php_recorder):
    php_recorder.add("GET", "/mcp/clients/by-phone", body={"client": {"id": 3}})
    php_recorder.add("POST", "/mcp/appointments", 409, {"error": "No commercial is available"})
    res = await handlers.book_appointment(ctx, client_name="Luis", store_id=2, date="2026-10-02", time="10:00")
    assert res["ok"] is False and res["slot_taken"] is True
    assert (await handlers.book_appointment(ctx, client_name="", store_id=2, date="2026-10-02", time="10:00"))["ok"] is False
    assert (await handlers.book_appointment(ctx, client_name="Luis", store_id=2, date="2026-10-02", time="25:00"))["ok"] is False
    assert (await handlers.book_appointment(ctx, client_name="Luis", store_id=2, date="2026-09-29", time="08:00"))["ok"] is False
    assert (await handlers.book_appointment(ctx, client_name="Luis", store_id=2, date="2026-10-02", time="10:00",
                                            client_email="nope"))["ok"] is False


async def test_handoff_redirects_live_call_and_records_lead(ctx, php_recorder, fake_rest):
    php_recorder.add("POST", "/mcp/leads", 201, {"lead": {"id": 12}})
    res = await handlers.commercial_handoff(ctx, reason="Presupuesto reforma", caller_name="Ana", store_id=1)
    assert res["ok"] and res["transferred"] and res["lead_id"] == 12
    assert ctx.transferred is True
    lead = php_recorder.body()
    assert lead["call_sid"] == SID and lead["phone"] == "+34611222333"
    assert lead["transferred"] is True and lead["source"] == "handoff" and lead["reason"] == "Presupuesto reforma"
    sid, kwargs = fake_rest.updates[0]
    assert sid == SID
    assert "<Dial" in kwargs["twiml"] and "+34600999888" in kwargs["twiml"]
    assert "<Say" in kwargs["twiml"]
    assert 'callerId="+34910000000"' in kwargs["twiml"]


async def test_handoff_cold_has_no_announcement_before_dial(ctx, php_recorder, fake_rest):
    php_recorder.add("POST", "/mcp/leads", 201, {"lead": {"id": 1}})
    await handlers.commercial_handoff(ctx, reason="x", mode="cold")
    twiml = fake_rest.updates[0][1]["twiml"]
    assert twiml.index("<Dial") < twiml.index("<Say")


async def test_handoff_twilio_failure_keeps_lead_and_not_transferred(ctx, php_recorder, fake_rest):
    php_recorder.add("POST", "/mcp/leads", 201, {"lead": {"id": 4}})

    def boom(sid):
        raise RuntimeError("twilio down")

    fake_rest.calls = boom
    res = await handlers.commercial_handoff(ctx, reason="x")
    assert res["ok"] is False and res["lead_id"] == 4 and ctx.transferred is False


async def test_handoff_requires_number_and_telephony(ctx, settings):
    ctx.call_sid = "webrtc-abc"
    assert (await handlers.commercial_handoff(ctx, reason="x"))["ok"] is False
    ctx.call_sid = SID
    ctx.settings = type(settings)(**{**settings.__dict__, "handoff_phone_number": ""})
    assert (await handlers.commercial_handoff(ctx, reason="x"))["ok"] is False


async def test_handoff_uses_number_from_php(ctx, php_recorder, fake_rest):
    php_recorder.add("GET", "/mcp/settings/handoff", 200, {"handoff_phone_number": "+34611000111"})
    php_recorder.add("POST", "/mcp/leads", 201, {"lead": {"id": 1}})
    res = await handlers.commercial_handoff(ctx, reason="x")
    assert res["ok"] and res["transferred"]
    twiml = fake_rest.updates[0][1]["twiml"]
    assert "+34611000111" in twiml and "+34600999888" not in twiml


async def test_handoff_falls_back_to_env_when_php_fails(ctx, php_recorder, fake_rest):
    php_recorder.add("GET", "/mcp/settings/handoff", 500, {"error": "boom"})
    php_recorder.add("POST", "/mcp/leads", 201, {"lead": {"id": 1}})
    res = await handlers.commercial_handoff(ctx, reason="x")
    assert res["ok"] and "+34600999888" in fake_rest.updates[0][1]["twiml"]


async def test_handoff_number_is_cached_briefly(ctx, php_recorder, fake_rest):
    php_recorder.add("GET", "/mcp/settings/handoff", 200, {"handoff_phone_number": "+34611000111"})
    php_recorder.add("POST", "/mcp/leads", 201, {"lead": {"id": 1}})
    await handlers.commercial_handoff(ctx, reason="x")
    await handlers.commercial_handoff(ctx, reason="y")
    fetches = [r for r in php_recorder.requests if r.url.path == "/mcp/settings/handoff"]
    assert len(fetches) == 1
    # after the TTL it is fetched again (picks up an admin edit)
    handlers._handoff_cache = (handlers._handoff_cache[0], 0.0)
    php_recorder.add("GET", "/mcp/settings/handoff", 200, {"handoff_phone_number": "+34622000222"})
    await handlers.commercial_handoff(ctx, reason="z")
    assert "+34622000222" in fake_rest.updates[-1][1]["twiml"]


class _Params:
    def __init__(self, arguments, messages):
        self.arguments = arguments
        self.context = type("Ctx", (), {"get_messages": lambda _s: messages})()
        self.result = None
        self.properties = None

    async def result_callback(self, result, properties=None):
        self.result = result
        self.properties = properties


async def test_handoff_refused_in_same_turn_as_tool_failure(ctx, php_recorder, fake_rest):
    from src.tools import _dispatch

    php_recorder.add("POST", "/mcp/leads", 201, {"lead": {"id": 7}})
    turn1 = [{"role": "user", "content": "inicio"}, {"role": "user", "content": "Quiero cita"}]
    info = _Params({}, turn1)
    await _dispatch(handlers.get_company_info, ctx, info)  # PHP 404 -> ok False
    assert info.result["ok"] is False

    blocked = _Params({"reason": "fallo"}, turn1)
    await _dispatch(handlers.commercial_handoff, ctx, blocked)
    assert blocked.result["ok"] is False and not fake_rest.updates and ctx.transferred is False

    # The caller answers "sí" -> new user turn -> the transfer goes through.
    ok = _Params({"reason": "fallo"}, turn1 + [{"role": "user", "content": "Sí, pásame"}])
    await _dispatch(handlers.commercial_handoff, ctx, ok)
    assert ok.result["ok"] is True and fake_rest.updates and ctx.transferred is True


async def test_handoff_on_request_without_failure_is_immediate(ctx, php_recorder, fake_rest):
    from src.tools import _dispatch

    php_recorder.add("POST", "/mcp/leads", 201, {"lead": {"id": 8}})
    p = _Params({"reason": "quiere hablar con una persona"}, [{"role": "user", "content": "Pásame con alguien"}])
    await _dispatch(handlers.commercial_handoff, ctx, p)
    assert p.result["ok"] is True and ctx.transferred is True


async def test_end_call_speaks_goodbye_and_hangs_up_without_new_llm_turn(ctx):
    from src.tools import _dispatch

    spoken = []

    async def say_and_hang_up(text):
        spoken.append(text)

    ctx.say_and_hang_up = say_and_hang_up
    p = _Params({}, [{"role": "user", "content": "No, nada más, gracias"}])
    await _dispatch(handlers.end_call, ctx, p)
    assert p.result["ok"] is True and ctx.ended is True
    assert p.properties is not None and p.properties.run_llm is False
    assert spoken == [handlers.FAREWELL]


async def test_end_call_after_transfer_does_nothing(ctx):
    from src.tools import _dispatch

    spoken = []

    async def say_and_hang_up(text):
        spoken.append(text)

    ctx.say_and_hang_up = say_and_hang_up
    ctx.transferred = True
    p = _Params({}, [{"role": "user", "content": "Adiós"}])
    await _dispatch(handlers.end_call, ctx, p)
    assert p.result["ok"] is False and not spoken and ctx.ended is False
