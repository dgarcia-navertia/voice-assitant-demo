"""Logging setup (loguru) driven by LOG_LEVEL."""

from __future__ import annotations

import sys

from loguru import logger

_FORMAT = "{time:YYYY-MM-DD HH:mm:ss.SSS} | {level: <7} | {name}:{function}:{line} - {message}"


def configure_logging(level: str = "INFO") -> None:
    logger.remove()
    logger.add(sys.stderr, level=level.upper(), format=_FORMAT, enqueue=False)
