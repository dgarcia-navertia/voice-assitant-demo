"""Idle-user handling: 20 s of silence -> "¿Sigues ahí?" (max 2 retries) -> goodbye + hang up.

pipecat 1.12 mechanism: `LLMUserAggregatorParams(user_idle_timeout=...)` makes the user
aggregator fire the `on_user_turn_idle` event when the user has been quiet for that long
after the bot finished speaking (the timer is suppressed during function calls and
active user turns, and cancelled when either side starts speaking). The event fires
repeatedly, so this controller counts the attempts.
"""

from __future__ import annotations

from collections.abc import Awaitable, Callable

from loguru import logger

REMINDERS = (
    "¿Sigues ahí?",
    "¿Sigues ahí? No te oigo.",
)
GOODBYE = "Parece que no puedes hablar ahora. Gracias por llamar a Navertia. ¡Hasta pronto!"


class IdleController:
    def __init__(
        self,
        *,
        max_retries: int,
        say: Callable[[str], Awaitable[None]],
        say_and_hang_up: Callable[[str], Awaitable[None]],
    ):
        self.max_retries = max_retries
        self._say = say
        self._say_and_hang_up = say_and_hang_up
        self.attempts = 0
        self.ended = False

    def reset(self) -> None:
        """The user spoke: start counting from zero again."""
        self.attempts = 0

    async def on_idle(self) -> None:
        if self.ended:
            return
        if self.attempts < self.max_retries:
            text = REMINDERS[min(self.attempts, len(REMINDERS) - 1)]
            self.attempts += 1
            logger.info("User idle: reminder {}/{}", self.attempts, self.max_retries)
            await self._say(text)
        else:
            self.ended = True
            logger.info("User idle after {} reminders: hanging up", self.max_retries)
            await self._say_and_hang_up(GOODBYE)
