"""Tool handlers. Each returns a JSON-serialisable dict for the LLM.

Errors never raise into the pipeline: they are returned as
`{"ok": False, "message": "..."}` with text the LLM can relay in Spanish.
"""

from __future__ import annotations

import re
import time
from datetime import datetime
from typing import Any
from zoneinfo import ZoneInfo

from loguru import logger

from src.clients.php_api import PhpApiError
from src.clients.twilio_client import TwilioError, handoff_twiml
from src.tools.context import ToolContext

_DATE_RE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
_TIME_RE = re.compile(r"^([01]?\d|2[0-3]):([0-5]\d)$")
_EMAIL_RE = re.compile(r"^[^@\s]+@[^@\s]+\.[^@\s]+$")
_PHONE_RE = re.compile(r"^\+[1-9]\d{6,14}$")
MAX_SLOTS_OFFERED = 12


def _fail(message: str, **extra: Any) -> dict[str, Any]:
    return {"ok": False, "message": message, **extra}


def _parse_date(value: Any, ctx: ToolContext) -> str | None:
    if not isinstance(value, str) or not _DATE_RE.match(value.strip()):
        return None
    try:
        datetime.strptime(value.strip(), "%Y-%m-%d")
    except ValueError:
        return None
    return value.strip()


def _normalize_time(value: Any) -> str | None:
    if not isinstance(value, str):
        return None
    m = _TIME_RE.match(value.strip())
    return f"{int(m.group(1)):02d}:{m.group(2)}" if m else None


def _store_summary(store: dict[str, Any]) -> dict[str, Any]:
    schedule = store.get("schedule") or {}
    return {
        "store_id": store.get("id"),
        "name": store.get("name"),
        "address": store.get("address"),
        "phone": store.get("phone_number"),
        "schedule_monday_to_friday": schedule.get("mon_to_friday") or "cerrado",
        "schedule_saturday": schedule.get("saturday") or "cerrado",
        "schedule_sunday": "cerrado",
    }


async def get_company_info(ctx: ToolContext, **_: Any) -> dict[str, Any]:
    try:
        stores = await ctx.php.stores()
    except PhpApiError as exc:
        return _fail(f"No he podido consultar las tiendas: {exc.message}")
    return {"ok": True, "company": "Navertia", "stores": [_store_summary(s) for s in stores]}


async def check_availability(ctx: ToolContext, store_id: Any = None, date: Any = None, **_: Any) -> dict[str, Any]:
    try:
        store_id = int(store_id)
    except (TypeError, ValueError):
        return _fail("Falta la tienda (store_id). Llama antes a get_company_info.")
    day = _parse_date(date, ctx)
    if day is None:
        return _fail("La fecha debe tener formato AAAA-MM-DD.")
    if day < ctx.now().strftime("%Y-%m-%d"):
        return _fail("Esa fecha ya ha pasado. Pide otra fecha.")
    try:
        data = await ctx.php.availability(store_id, day)
    except PhpApiError as exc:
        return _fail(f"No he podido consultar la disponibilidad: {exc.message}")
    times = sorted({s["time"] for s in data.get("slots", []) if s.get("time")})
    if day == ctx.now().strftime("%Y-%m-%d"):
        current = ctx.now().strftime("%H:%M")
        times = [t for t in times if t > current]
    if not times:
        return {"ok": True, "date": day, "store_id": store_id, "available_times": [],
                "message": "No hay huecos libres ese día. Ofrece otro día."}
    return {
        "ok": True,
        "date": day,
        "store_id": store_id,
        "available_times": times[:MAX_SLOTS_OFFERED],
        "total_free": len(times),
    }


async def _find_or_create_client(
    ctx: ToolContext, name: str, phone: str, email: str | None
) -> tuple[dict[str, Any], bool]:
    existing = await ctx.php.client_by_phone(phone)
    if existing:
        return existing, False
    return await ctx.php.create_client(name, phone, email), True


async def book_appointment(
    ctx: ToolContext,
    client_name: Any = None,
    store_id: Any = None,
    date: Any = None,
    time: Any = None,
    client_email: Any = None,
    phone: Any = None,
    **_: Any,
) -> dict[str, Any]:
    name = client_name.strip() if isinstance(client_name, str) else ""
    if len(name) < 2:
        return _fail("Falta el nombre de la persona.")
    try:
        store_id = int(store_id)
    except (TypeError, ValueError):
        return _fail("Falta la tienda (store_id).")
    day = _parse_date(date, ctx)
    hhmm = _normalize_time(time)
    if day is None or hhmm is None:
        return _fail("Fecha (AAAA-MM-DD) u hora (HH:MM) no válidas.")
    if datetime.strptime(f"{day} {hhmm}", "%Y-%m-%d %H:%M") <= ctx.now().replace(tzinfo=None):
        return _fail("Esa hora ya ha pasado. Pide otra.")

    email = client_email.strip() if isinstance(client_email, str) and client_email.strip() else None
    if email and not _EMAIL_RE.match(email):
        return _fail("El correo electrónico no parece válido. Pídelo de nuevo o continúa sin él.")

    contact = (ctx.customer_phone or "").strip()
    if not contact and isinstance(phone, str):
        contact = phone.strip().replace(" ", "")
    if not _PHONE_RE.match(contact):
        return _fail("No tengo un teléfono válido de la persona. Pídelo con prefijo internacional (+34...).")

    try:
        client, created = await _find_or_create_client(ctx, name, contact, email)
        appointment = await ctx.php.create_appointment(store_id, int(client["id"]), f"{day} {hhmm}:00")
    except PhpApiError as exc:
        if exc.status == 409:
            return _fail("Ese hueco ya no está disponible. Consulta la disponibilidad y ofrece otra hora.",
                         slot_taken=True)
        if exc.status in (404, 422):
            return _fail(f"No he podido reservar: {exc.message}")
        return _fail(f"No he podido completar la reserva: {exc.message}")
    except (KeyError, TypeError, ValueError):
        return _fail("Respuesta inesperada del sistema de agenda.")

    ctx.booked_appointment_id = appointment.get("id")
    logger.info("Appointment {} booked (new client: {})", appointment.get("id"), created)
    return {
        "ok": True,
        "appointment_id": appointment.get("id"),
        "client_created": created,
        "store": appointment.get("store_name"),
        "date": day,
        "time": hhmm,
        "message": "Cita reservada. Confírmasela a la persona.",
    }


HANDOFF_CACHE_TTL = 45.0
_handoff_cache: tuple[str, float] | None = None


def reset_handoff_cache() -> None:
    global _handoff_cache
    _handoff_cache = None


async def resolve_handoff_number(ctx: ToolContext) -> str:
    """Handoff number at handoff time: PHP value (short cache), else HANDOFF_PHONE_NUMBER."""
    global _handoff_cache
    now = time.monotonic()
    if _handoff_cache and _handoff_cache[1] > now:
        return _handoff_cache[0]
    try:
        number = await ctx.php.handoff_number()
        if number:
            _handoff_cache = (number, now + HANDOFF_CACHE_TTL)
            return number
        logger.warning("PHP returned an empty handoff number; using HANDOFF_PHONE_NUMBER from env")
    except PhpApiError as exc:
        logger.warning("Could not fetch handoff number from PHP ({}); using HANDOFF_PHONE_NUMBER from env", exc.message)
    return ctx.settings.handoff_phone_number


async def commercial_handoff(
    ctx: ToolContext,
    reason: Any = None,
    caller_name: Any = None,
    store_id: Any = None,
    mode: Any = "warm",
    **_: Any,
) -> dict[str, Any]:
    """Real transfer: update the live Twilio call with <Dial> + record a lead."""
    if ctx.tool_failed_turn is not None and ctx.tool_failed_turn == ctx.user_turns:
        # The LLM tends to transfer straight after a tool error, without asking.
        logger.info("Handoff refused: a tool failed in this same turn and the caller was not asked")
        return _fail("No transfieras todavía. Di con naturalidad que ahora mismo hay un problema técnico y "
                     "pregunta si quiere que le pases con un asesor. Transfiere solo si responde que sí.")
    number = await resolve_handoff_number(ctx)
    if not number:
        return _fail("No hay un número de asesor configurado. Ofrece que le llamarán más tarde.")
    if not ctx.is_telephony:
        return _fail("La transferencia solo está disponible en llamadas telefónicas.")

    reason_text = reason.strip() if isinstance(reason, str) else None
    try:
        sid = int(store_id) if store_id is not None else None
    except (TypeError, ValueError):
        sid = None

    # 1) Lead first: it must be recorded even if the transfer fails.
    lead_id = None
    try:
        lead = await ctx.php.create_lead(
            call_sid=ctx.call_sid,
            name=caller_name if isinstance(caller_name, str) and caller_name.strip() else None,
            phone=ctx.customer_phone,
            store_id=sid,
            reason=reason_text,
            source="handoff",
            transferred=True,
        )
        lead_id = (lead.get("lead") or {}).get("id")
    except PhpApiError as exc:
        logger.warning("Lead not recorded: {}", exc.message)

    # 2) Real transfer.
    announcement = None if str(mode).lower() == "cold" else "Te paso ahora con un compañero. Un momento, por favor."
    twiml = handoff_twiml(number, caller_id=ctx.settings.twilio_phone_number or None, announcement=announcement)
    try:
        await ctx.twilio.redirect_call(ctx.call_sid, twiml)
    except TwilioError as exc:
        logger.error("Handoff redirect failed: {}", exc)
        return _fail("No he podido transferir la llamada. Discúlpate y ofrece que un asesor le llamará.",
                     lead_id=lead_id)
    ctx.transferred = True
    return {"ok": True, "transferred": True, "lead_id": lead_id,
            "message": "Transferencia en curso. No digas nada más."}
