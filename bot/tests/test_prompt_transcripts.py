from datetime import datetime
from zoneinfo import ZoneInfo

from src.prompts import CALL_START_MARKER, build_system_prompt, format_now_es
from src.transcripts import extract_turns

NOW = datetime(2026, 9, 29, 11, 8, tzinfo=ZoneInfo("Europe/Madrid"))


def test_format_now_es():
    assert format_now_es(NOW) == "martes 29 de septiembre de 2026, 11:08 (2026-09-29)"


def test_prompt_outbound_knows_phone_and_tools():
    p = build_system_prompt(now=NOW, customer_phone="+34611222333", direction="outbound")
    assert "+34611222333" in p and "SALIENTE" in p
    assert "martes 29 de septiembre" in p
    for tool in ("get_company_info", "check_availability", "book_appointment", "commercial_handoff"):
        assert tool in p
    assert "flows" not in p.lower()


def test_prompt_inbound_and_unknown():
    assert "ENTRANTE" in build_system_prompt(now=NOW, customer_phone="+34611222333")
    assert "no es conocido" in build_system_prompt(now=NOW)


def test_extract_turns_filters_non_speech():
    msgs = [
        {"role": "system", "content": "x"},
        {"role": "user", "content": CALL_START_MARKER},
        {"role": "assistant", "content": "Hola"},
        {"role": "user", "content": [{"type": "text", "text": "Quiero cita"}]},
        {"role": "assistant", "content": None, "tool_calls": []},
        {"role": "tool", "content": "{}"},
    ]
    turns = extract_turns(msgs)
    assert [(t["role"], t["transcript_text"], t["turn_index"]) for t in turns] == [
        ("assistant", "Hola", 0), ("user", "Quiero cita", 1)
    ]
