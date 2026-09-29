"""Turn extraction from the LLM context, for saving transcripts at call end."""

from __future__ import annotations

from typing import Any

from src.prompts import CALL_START_MARKER


def _text_of(content: Any) -> str:
    if isinstance(content, str):
        return content.strip()
    if isinstance(content, list):
        parts = [p.get("text", "") for p in content if isinstance(p, dict) and p.get("type") in (None, "text")]
        return " ".join(p for p in parts if p).strip()
    return ""


def extract_turns(messages: list[Any]) -> list[dict[str, Any]]:
    """Only spoken user/assistant text; drops system, tool calls and empty turns."""
    turns: list[dict[str, Any]] = []
    for msg in messages:
        if not isinstance(msg, dict):
            continue
        role = msg.get("role")
        if role not in ("user", "assistant"):
            continue
        text = _text_of(msg.get("content"))
        if not text or text == CALL_START_MARKER:
            continue
        turns.append(
            {"role": role, "transcript_text": text, "turn_index": len(turns), "interrupted": False}
        )
    return turns
