"""Async client for the PHP internal API (`/mcp/*`), Bearer INTERNAL_API_TOKEN."""

from __future__ import annotations

from typing import Any

import httpx
from loguru import logger


class PhpApiError(Exception):
    """The PHP API answered with an error or could not be reached."""

    def __init__(self, message: str, status: int | None = None):
        super().__init__(message)
        self.status = status
        self.message = message


class PhpApiClient:
    def __init__(
        self,
        base_url: str,
        token: str,
        *,
        timeout: float = 10.0,
        transport: httpx.AsyncBaseTransport | None = None,
    ):
        self._client = httpx.AsyncClient(
            base_url=base_url.rstrip("/"),
            headers={"Authorization": f"Bearer {token}", "Accept": "application/json"},
            timeout=timeout,
            transport=transport,
        )

    async def aclose(self) -> None:
        await self._client.aclose()

    async def _request(self, method: str, path: str, **kwargs: Any) -> dict[str, Any]:
        try:
            resp = await self._client.request(method, path, **kwargs)
        except httpx.HTTPError as exc:
            logger.warning("PHP API unreachable: {} {} ({})", method, path, exc.__class__.__name__)
            raise PhpApiError("El servicio de agenda no responde ahora mismo.") from exc
        try:
            data = resp.json()
        except ValueError:
            data = {}
        if resp.status_code >= 400:
            message = (data.get("error") or data.get("message") or f"HTTP {resp.status_code}") if isinstance(data, dict) else f"HTTP {resp.status_code}"
            logger.info("PHP API {} {} -> {} {}", method, path, resp.status_code, message)
            raise PhpApiError(str(message), status=resp.status_code)
        return data if isinstance(data, dict) else {"data": data}

    # --- read ---------------------------------------------------------------
    async def stores(self) -> list[dict[str, Any]]:
        return (await self._request("GET", "/mcp/stores")).get("stores", [])

    async def handoff_number(self) -> str:
        """Current handoff number, editable by an admin in the PHP panel."""
        data = await self._request("GET", "/mcp/settings/handoff")
        return str(data.get("handoff_phone_number") or "").strip()

    async def availability(self, store_id: int, date: str, service_id: int | None = None) -> dict[str, Any]:
        params: dict[str, Any] = {"store_id": store_id, "date": date}
        if service_id:
            params["service_id"] = service_id
        return await self._request("GET", "/mcp/availability", params=params)

    async def client_by_phone(self, phone: str) -> dict[str, Any] | None:
        try:
            data = await self._request("GET", "/mcp/clients/by-phone", params={"phone": phone})
        except PhpApiError as exc:
            if exc.status == 404:
                return None
            raise
        return data.get("client")

    # --- write --------------------------------------------------------------
    async def create_client(
        self, name: str, phone: str, email: str | None = None, client_type: str = "particular"
    ) -> dict[str, Any]:
        body: dict[str, Any] = {"client_name": name, "client_phone": phone, "client_type": client_type}
        if email:
            body["client_email"] = email
        return (await self._request("POST", "/mcp/clients", json=body)).get("client", {})

    async def create_appointment(
        self, store_id: int, client_id: int, starts_at: str, service_id: int | None = None
    ) -> dict[str, Any]:
        body: dict[str, Any] = {"store_id": store_id, "client_id": client_id, "starts_at": starts_at}
        if service_id:
            body["service_id"] = service_id
        return await self._request("POST", "/mcp/appointments", json=body)

    async def save_transcripts(self, sid: str, turns: list[dict[str, Any]]) -> dict[str, Any]:
        return await self._request("POST", "/mcp/transcripts/batch", json={"sid": sid, "turns": turns})

    async def create_lead(self, **fields: Any) -> dict[str, Any]:
        body = {k: v for k, v in fields.items() if v is not None}
        return await self._request("POST", "/mcp/leads", json=body)

    async def post_call_status(self, **fields: Any) -> dict[str, Any]:
        body = {k: v for k, v in fields.items() if v is not None}
        return await self._request("POST", "/mcp/calls/status", json=body)
