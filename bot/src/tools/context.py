"""Per-call state shared by all tool handlers."""

from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime
from zoneinfo import ZoneInfo

from src.clients.php_api import PhpApiClient
from src.clients.twilio_client import TwilioGateway
from src.config.settings import Settings


@dataclass
class ToolContext:
    settings: Settings
    php: PhpApiClient
    twilio: TwilioGateway
    call_sid: str
    customer_phone: str | None = None
    direction: str = "inbound"  # inbound | outbound | webrtc
    transferred: bool = False
    booked_appointment_id: int | None = None
    # User turns seen so far, and the turn in which a tool last failed. A handoff
    # in that same turn is refused: the caller must be asked first (see handlers).
    user_turns: int = 0
    tool_failed_turn: int | None = None
    _now: datetime | None = field(default=None, repr=False)

    def now(self) -> datetime:
        return self._now or datetime.now(ZoneInfo(self.settings.timezone))

    @property
    def is_telephony(self) -> bool:
        return self.call_sid.startswith("CA")
