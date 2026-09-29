"""Thin, injectable wrapper around the Twilio REST client.

Every Twilio interaction of the bot goes through `TwilioGateway`, so tests
swap it for a fake and no real call is ever placed. The Twilio SDK is
synchronous: calls run in a worker thread (`asyncio.to_thread`).
"""

from __future__ import annotations

import asyncio
from typing import Any

from twilio.request_validator import RequestValidator
from twilio.rest import Client as TwilioRestClient
from twilio.twiml.voice_response import Connect, VoiceResponse


class TwilioError(Exception):
    pass


def stream_twiml(ws_url: str, parameters: dict[str, str]) -> str:
    """<Response><Connect><Stream url=... ><Parameter .../></Stream></Connect></Response>"""
    response = VoiceResponse()
    connect = Connect()
    stream = connect.stream(url=ws_url)
    for name, value in parameters.items():
        stream.parameter(name=name, value=value)
    response.append(connect)
    return str(response)


def handoff_twiml(
    number: str,
    *,
    caller_id: str | None = None,
    announcement: str | None = None,
    timeout: int = 30,
) -> str:
    """TwiML that (optionally) announces and then <Dial>s the human number.

    If nobody answers, the trailing <Say> plays and the call ends.
    """
    response = VoiceResponse()
    if announcement:
        response.say(announcement, language="es-ES")
    dial_kwargs: dict[str, Any] = {"timeout": timeout}
    if caller_id:
        dial_kwargs["caller_id"] = caller_id
    response.dial(number, **dial_kwargs)
    response.say("No hemos podido contactar con un asesor. Inténtalo de nuevo más tarde. Hasta pronto.", language="es-ES")
    return str(response)


class TwilioGateway:
    def __init__(self, account_sid: str, auth_token: str, *, rest_client: Any | None = None):
        self._account_sid = account_sid
        self._auth_token = auth_token
        self._rest = rest_client
        self._validator = RequestValidator(auth_token) if auth_token else None

    @property
    def configured(self) -> bool:
        return bool(self._account_sid and self._auth_token) or self._rest is not None

    def _client(self) -> Any:
        if self._rest is None:
            if not (self._account_sid and self._auth_token):
                raise TwilioError("Twilio no está configurado (TWILIO_ACCOUNT_SID / TWILIO_AUTH_TOKEN).")
            self._rest = TwilioRestClient(self._account_sid, self._auth_token)
        return self._rest

    async def create_call(
        self, *, to: str, from_: str, twiml: str, status_callback: str | None = None
    ) -> dict[str, str]:
        def _create() -> Any:
            kwargs: dict[str, Any] = {"to": to, "from_": from_, "twiml": twiml}
            if status_callback:
                kwargs.update(
                    status_callback=status_callback,
                    status_callback_method="POST",
                    status_callback_event=["initiated", "ringing", "answered", "completed"],
                )
            return self._client().calls.create(**kwargs)

        try:
            call = await asyncio.to_thread(_create)
        except TwilioError:
            raise
        except Exception as exc:  # twilio.base.exceptions.TwilioRestException et al.
            raise TwilioError(str(exc)) from exc
        return {"sid": call.sid, "status": getattr(call, "status", "queued") or "queued"}

    async def redirect_call(self, call_sid: str, twiml: str) -> None:
        """Replace the TwiML of a live call (used for the real transfer)."""

        def _update() -> None:
            self._client().calls(call_sid).update(twiml=twiml)

        try:
            await asyncio.to_thread(_update)
        except TwilioError:
            raise
        except Exception as exc:
            raise TwilioError(str(exc)) from exc

    async def hangup(self, call_sid: str) -> None:
        def _hangup() -> None:
            self._client().calls(call_sid).update(status="completed")

        try:
            await asyncio.to_thread(_hangup)
        except Exception as exc:
            raise TwilioError(str(exc)) from exc

    def validate_signature(self, url: str, params: dict[str, str], signature: str | None) -> bool:
        if not self._validator or not signature:
            return False
        return bool(self._validator.validate(url, params, signature))


__all__ = ["TwilioGateway", "TwilioError", "stream_twiml", "handoff_twiml"]
