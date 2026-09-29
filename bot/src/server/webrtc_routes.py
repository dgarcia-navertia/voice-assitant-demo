"""SmallWebRTC dev endpoints (local browser testing, no Twilio involved).

The prebuilt UI (`pipecat-ai-small-webrtc-prebuilt`) is mounted at /client and
talks to POST/PATCH /api/offer. Open http://localhost:7860/webrtc.
"""

from __future__ import annotations

import asyncio
import uuid
from typing import Any

from fastapi import APIRouter, Request
from fastapi.responses import RedirectResponse
from loguru import logger

from src.pipeline import CallInfo, run_pipeline

router = APIRouter()


@router.get("/webrtc", include_in_schema=False)
async def webrtc_home() -> RedirectResponse:
    return RedirectResponse(url="/client/")


@router.post("/api/offer")
async def offer(request: Request, payload: dict[str, Any]) -> dict[str, Any] | None:
    from pipecat.transports.smallwebrtc.request_handler import SmallWebRTCRequest

    handler = request.app.state.webrtc_handler
    webrtc_request = SmallWebRTCRequest.from_dict(dict(payload))

    async def on_connection(connection: Any) -> None:
        asyncio.create_task(_run_webrtc_bot(request.app, connection, webrtc_request.request_data))

    return await handler.handle_web_request(webrtc_request, on_connection)


@router.patch("/api/offer")
async def ice_candidate(request: Request, payload: dict[str, Any]) -> dict[str, str]:
    from pipecat.transports.smallwebrtc.request_handler import IceCandidate, SmallWebRTCPatchRequest

    patch = SmallWebRTCPatchRequest(
        pc_id=payload["pc_id"],
        candidates=[IceCandidate(**c) for c in payload.get("candidates", [])],
    )
    await request.app.state.webrtc_handler.handle_patch_request(patch)
    return {"status": "success"}


async def _run_webrtc_bot(app: Any, connection: Any, request_data: Any) -> None:
    from pipecat.transports.base_transport import TransportParams
    from pipecat.transports.smallwebrtc.transport import SmallWebRTCTransport

    phone = request_data.get("phone") if isinstance(request_data, dict) else None
    transport = SmallWebRTCTransport(
        webrtc_connection=connection,
        params=TransportParams(audio_in_enabled=True, audio_out_enabled=True),
    )
    info = CallInfo(call_sid=f"webrtc-{uuid.uuid4()}", customer_phone=phone, direction="webrtc")
    try:
        await run_pipeline(
            transport, settings=app.state.settings, php=app.state.php,
            twilio=app.state.twilio, info=info,
        )
    except Exception:
        logger.exception("WebRTC session failed")
