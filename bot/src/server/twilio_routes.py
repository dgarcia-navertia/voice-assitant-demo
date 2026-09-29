"""Twilio-facing HTTP/WS routes: inbound TwiML, dial-out, status callback, media stream."""

from __future__ import annotations

import hmac
import re
from typing import Any

from fastapi import APIRouter, Depends, HTTPException, Request, Response, WebSocket
from loguru import logger
from pydantic import BaseModel

from src.clients.php_api import PhpApiClient, PhpApiError
from src.clients.twilio_client import TwilioError, TwilioGateway, stream_twiml
from src.config.settings import Settings
from src.pipeline import CallInfo, run_pipeline

router = APIRouter()

E164_RE = re.compile(r"^\+[1-9]\d{6,14}$")

# Twilio CallStatus -> statuses understood by the PHP UI
STATUS_MAP = {
    "initiated": "queued",
    "queued": "queued",
    "ringing": "ringing",
    "in-progress": "in-progress",
    "answered": "in-progress",
    "completed": "completed",
    "busy": "busy",
    "no-answer": "no-answer",
    "canceled": "canceled",
    "failed": "failed",
}


def get_settings_dep(request: Request) -> Settings:
    return request.app.state.settings


def get_twilio(request: Request) -> TwilioGateway:
    return request.app.state.twilio


def get_php(request: Request) -> PhpApiClient:
    return request.app.state.php


def require_internal_token(request: Request, settings: Settings = Depends(get_settings_dep)) -> None:
    expected = settings.internal_api_token
    header = request.headers.get("authorization", "")
    provided = header[7:] if header.lower().startswith("bearer ") else request.headers.get("x-api-key", "")
    if not expected or not provided or not hmac.compare_digest(provided, expected):
        raise HTTPException(status_code=401, detail="UNAUTHORIZED")


def _public_url(request: Request, settings: Settings) -> str:
    """The URL Twilio signed (public base + path), not the internal one behind the proxy."""
    if settings.public_base_url:
        url = settings.public_base_url + request.url.path
        return f"{url}?{request.url.query}" if request.url.query else url
    return str(request.url)


async def verify_twilio_request(request: Request) -> dict[str, str]:
    """Return the form params after validating X-Twilio-Signature (if enabled)."""
    settings: Settings = request.app.state.settings
    form = await request.form()
    params = {k: str(v) for k, v in form.items()}
    if settings.twilio_validate_signature:
        twilio: TwilioGateway = request.app.state.twilio
        signature = request.headers.get("x-twilio-signature")
        if not twilio.validate_signature(_public_url(request, settings), params, signature):
            logger.warning("Rejected Twilio webhook with invalid signature: {}", request.url.path)
            raise HTTPException(status_code=403, detail="Invalid Twilio signature")
    return params


def _xml(body: str) -> Response:
    return Response(content=body, media_type="application/xml")


# --- Inbound call webhook --------------------------------------------------
@router.post("/twilio/voice")
async def inbound_voice(request: Request, settings: Settings = Depends(get_settings_dep)) -> Response:
    params = await verify_twilio_request(request)
    caller = params.get("From", "")
    logger.info("Inbound call {} from {}", params.get("CallSid"), caller)
    twiml = stream_twiml(
        settings.public_ws_url,
        {"phone": caller, "direction": "inbound", "to": params.get("To", "")},
    )
    return _xml(twiml)


# --- Outbound call ---------------------------------------------------------
class DialOutRequest(BaseModel):
    to: str


@router.post("/dial-out", dependencies=[Depends(require_internal_token)])
async def dial_out(
    body: DialOutRequest,
    settings: Settings = Depends(get_settings_dep),
    twilio: TwilioGateway = Depends(get_twilio),
) -> dict[str, Any]:
    to = body.to.strip()
    if not E164_RE.match(to):
        raise HTTPException(status_code=422, detail="'to' debe estar en formato E.164 (+34600111222)")
    if not settings.public_base_url or not settings.twilio_phone_number or not twilio.configured:
        raise HTTPException(status_code=503, detail="Twilio / PUBLIC_BASE_URL no configurados")
    twiml = stream_twiml(settings.public_ws_url, {"phone": to, "direction": "outbound"})
    try:
        call = await twilio.create_call(
            to=to,
            from_=settings.twilio_phone_number,
            twiml=twiml,
            status_callback=f"{settings.public_base_url}/twilio/status",
        )
    except TwilioError as exc:
        logger.error("Twilio create_call failed: {}", exc)
        raise HTTPException(status_code=502, detail=f"Twilio: {exc}") from exc
    logger.info("Dial-out started: {} -> {}", call["sid"], to)
    return {"call_sid": call["sid"], "status": "queued", "to": to}


# --- Status callback (Twilio -> bot -> PHP) ---------------------------------
@router.post("/twilio/status")
async def call_status(request: Request, php: PhpApiClient = Depends(get_php)) -> dict[str, Any]:
    params = await verify_twilio_request(request)
    sid = params.get("CallSid")
    raw = params.get("CallStatus", "")
    if not sid or raw not in STATUS_MAP:
        raise HTTPException(status_code=422, detail="CallSid/CallStatus inválidos")
    duration = params.get("CallDuration")
    payload = {
        "call_sid": sid,
        "status": STATUS_MAP[raw],
        "to": params.get("To"),
        "from": params.get("From"),
        "direction": params.get("Direction"),
        "duration": int(duration) if duration and duration.isdigit() else None,
        "error": params.get("ErrorMessage") or params.get("ErrorCode"),
    }
    try:
        await php.post_call_status(**payload)
    except PhpApiError as exc:
        # Twilio doesn't retry status callbacks meaningfully; log and acknowledge.
        logger.error("Could not relay call status {} to PHP: {}", sid, exc.message)
        return {"ok": False, "relayed": False}
    return {"ok": True, "relayed": True, "status": payload["status"]}


# --- Twilio Media Streams WebSocket ------------------------------------------
@router.websocket("/ws")
async def twilio_ws(websocket: WebSocket) -> None:
    from pipecat.runner.utils import parse_telephony_websocket

    await websocket.accept()
    try:
        transport_type, call_data = await parse_telephony_websocket(websocket)
    except Exception:
        logger.exception("Invalid telephony websocket handshake")
        await websocket.close()
        return
    if transport_type != "twilio":
        logger.error("Unsupported telephony transport: {}", transport_type)
        await websocket.close()
        return
    body = call_data.get("body", {}) or {}
    info = CallInfo(
        call_sid=call_data["call_id"],
        customer_phone=body.get("phone") or None,
        direction=body.get("direction") or "inbound",
    )
    await run_twilio_call(
        websocket, stream_sid=call_data["stream_id"], info=info,
        settings=websocket.app.state.settings,
        php=websocket.app.state.php, twilio=websocket.app.state.twilio,
    )


async def run_twilio_call(
    websocket: WebSocket, *, stream_sid: str, info: CallInfo,
    settings: Settings, php: PhpApiClient, twilio: TwilioGateway,
) -> None:
    from pipecat.serializers.twilio import TwilioFrameSerializer
    from pipecat.transports.websocket.fastapi import FastAPIWebsocketParams, FastAPIWebsocketTransport

    # auto_hang_up=False: the serializer would also hang up a call we just
    # transferred to a human. The pipeline hangs up itself (see _finish_call).
    serializer = TwilioFrameSerializer(
        stream_sid=stream_sid,
        call_sid=info.call_sid,
        params=TwilioFrameSerializer.InputParams(auto_hang_up=False),
    )
    transport = FastAPIWebsocketTransport(
        websocket=websocket,
        params=FastAPIWebsocketParams(
            audio_in_enabled=True,
            audio_out_enabled=True,
            add_wav_header=False,
            serializer=serializer,
        ),
    )
    logger.info("Starting {} call {} ({})", info.direction, info.call_sid, info.customer_phone)
    await run_pipeline(transport, settings=settings, php=php, twilio=twilio, info=info, sample_rate=8000)
