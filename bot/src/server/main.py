"""FastAPI app: `uvicorn src.server.main:app --host 0.0.0.0 --port 7860`."""

from __future__ import annotations

import os
from contextlib import asynccontextmanager

from fastapi import FastAPI
from loguru import logger

from src.clients.php_api import PhpApiClient
from src.clients.twilio_client import TwilioGateway
from src.config.settings import Settings, get_settings
from src.observability import configure_logging
from src.server import twilio_routes, webrtc_routes


def create_app(
    settings: Settings | None = None,
    *,
    php: PhpApiClient | None = None,
    twilio: TwilioGateway | None = None,
    enable_webrtc: bool | None = None,
) -> FastAPI:
    settings = settings or get_settings()
    configure_logging(settings.log_level)
    if enable_webrtc is None:
        enable_webrtc = os.environ.get("ENABLE_WEBRTC", "true").lower() in ("1", "true", "yes")

    @asynccontextmanager
    async def lifespan(app: FastAPI):
        logger.info("Navertia voice bot starting (llm={} model={})", settings.llm_provider, settings.llm_model_id)
        yield
        await app.state.php.aclose()

    app = FastAPI(title="Navertia Voice Bot", lifespan=lifespan)
    app.state.settings = settings
    app.state.php = php or PhpApiClient(settings.php_api_base_url, settings.internal_api_token)
    app.state.twilio = twilio or TwilioGateway(settings.twilio_account_sid, settings.twilio_auth_token)

    @app.get("/health")
    async def health() -> dict[str, str]:
        return {"status": "ok"}

    app.include_router(twilio_routes.router)

    if enable_webrtc:
        try:
            from pipecat.transports.smallwebrtc.request_handler import SmallWebRTCRequestHandler

            app.state.webrtc_handler = SmallWebRTCRequestHandler()
            app.include_router(webrtc_routes.router)
            try:
                from pipecat_ai_prebuilt.frontend import PipecatPrebuiltUI

                app.mount("/client", PipecatPrebuiltUI)
            except ImportError:
                logger.warning("Prebuilt WebRTC UI not installed; /webrtc UI unavailable")
        except ImportError as exc:
            logger.warning("SmallWebRTC disabled: {}", exc)

    return app


app = create_app()
