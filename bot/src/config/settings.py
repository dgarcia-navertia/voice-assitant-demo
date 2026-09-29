"""Configuration loaded from environment variables.

The repo has a single root `.env`. In Docker it is injected through `env_file`;
when running locally (`uv run ...` inside bot/) it is read from `../.env` if it
exists. Nothing here raises on missing keys, so tests and `/health` work
without real credentials. Providers validate their own keys when built.
"""

from __future__ import annotations

import os
from collections.abc import Mapping
from dataclasses import dataclass
from functools import lru_cache
from pathlib import Path

from dotenv import load_dotenv

DEFAULT_GREETING = "Soy el asistente virtual de Navertia. ¿En qué te puedo ayudar?"
LLM_PROVIDERS = ("google", "anthropic", "openrouter", "openai")

# bot/src/config/settings.py -> repo root is parents[3]
_ROOT_ENV = Path(__file__).resolve().parents[3] / ".env"


def _str(env: Mapping[str, str], key: str, default: str = "") -> str:
    return (env.get(key) or default).strip()


def _int(env: Mapping[str, str], key: str, default: int) -> int:
    try:
        return int(_str(env, key) or default)
    except ValueError:
        return default


def _float(env: Mapping[str, str], key: str, default: float) -> float:
    try:
        return float(_str(env, key) or default)
    except ValueError:
        return default


def _bool(env: Mapping[str, str], key: str, default: bool) -> bool:
    raw = _str(env, key)
    if not raw:
        return default
    return raw.lower() in ("1", "true", "yes", "on")


@dataclass(frozen=True)
class Settings:
    # Service auth / internal addresses
    internal_api_token: str = ""
    php_api_base_url: str = "http://php"
    public_base_url: str = ""

    # Twilio
    twilio_account_sid: str = ""
    twilio_auth_token: str = ""
    twilio_phone_number: str = ""
    handoff_phone_number: str = ""
    twilio_validate_signature: bool = True

    # LLM
    llm_provider: str = "google"
    llm_model_id: str = "gemini-3.5-flash-lite"
    llm_temperature: float = 0.3
    google_api_key: str = ""
    anthropic_api_key: str = ""
    openrouter_api_key: str = ""
    openai_api_key: str = ""

    # STT / TTS
    deepgram_api_key: str = ""
    stt_model_id: str = "nova-3-general"
    stt_language: str = "es"
    eleven_labs_api_key: str = ""
    tts_model_id: str = "eleven_flash_v2_5"
    tts_voice_id: str = "dNjJKg63Fr5AXwIdkATa"
    tts_language: str = "es"

    # Conversation
    greeting: str = DEFAULT_GREETING
    idle_timeout_secs: float = 20.0
    idle_max_retries: int = 2
    timezone: str = "Europe/Madrid"
    log_level: str = "INFO"

    @classmethod
    def from_env(cls, env: Mapping[str, str] | None = None) -> Settings:
        e = os.environ if env is None else env
        return cls(
            internal_api_token=_str(e, "INTERNAL_API_TOKEN"),
            php_api_base_url=_str(e, "PHP_API_BASE_URL", "http://php").rstrip("/"),
            public_base_url=_str(e, "PUBLIC_BASE_URL").rstrip("/"),
            twilio_account_sid=_str(e, "TWILIO_ACCOUNT_SID"),
            twilio_auth_token=_str(e, "TWILIO_AUTH_TOKEN"),
            twilio_phone_number=_str(e, "TWILIO_PHONE_NUMBER"),
            handoff_phone_number=_str(e, "HANDOFF_PHONE_NUMBER"),
            twilio_validate_signature=_bool(e, "TWILIO_VALIDATE_SIGNATURE", True),
            llm_provider=_str(e, "LLM_PROVIDER", "google").lower(),
            llm_model_id=_str(e, "LLM_MODEL_ID", "gemini-3.5-flash-lite"),
            llm_temperature=_float(e, "LLM_TEMPERATURE", 0.3),
            google_api_key=_str(e, "GOOGLE_API_KEY"),
            anthropic_api_key=_str(e, "ANTHROPIC_API_KEY"),
            openrouter_api_key=_str(e, "OPENROUTER_API_KEY"),
            openai_api_key=_str(e, "OPENAI_API_KEY"),
            deepgram_api_key=_str(e, "DEEPGRAM_API_KEY"),
            stt_model_id=_str(e, "STT_MODEL_ID", "nova-3-general"),
            stt_language=_str(e, "STT_LANGUAGE", "es"),
            eleven_labs_api_key=_str(e, "ELEVEN_LABS_API_KEY"),
            tts_model_id=_str(e, "TTS_MODEL_ID", "eleven_flash_v2_5"),
            tts_voice_id=_str(e, "TTS_VOICE_ID", "dNjJKg63Fr5AXwIdkATa"),
            tts_language=_str(e, "TTS_LANGUAGE", "es"),
            greeting=_str(e, "GREETING", DEFAULT_GREETING),
            idle_timeout_secs=_float(e, "IDLE_TIMEOUT_SECS", 20.0),
            idle_max_retries=_int(e, "IDLE_MAX_RETRIES", 2),
            timezone=_str(e, "BOT_TIMEZONE", "Europe/Madrid"),
            log_level=_str(e, "LOG_LEVEL", "INFO").upper(),
        )

    @property
    def public_ws_url(self) -> str:
        """wss:// URL of the Twilio media stream endpoint."""
        base = self.public_base_url
        if base.startswith("https://"):
            base = "wss://" + base[len("https://"):]
        elif base.startswith("http://"):
            base = "ws://" + base[len("http://"):]
        return f"{base}/ws"


@lru_cache(maxsize=1)
def get_settings() -> Settings:
    if _ROOT_ENV.exists():
        # override=False: real process env (Docker env_file) always wins.
        load_dotenv(_ROOT_ENV, override=False)
    return Settings.from_env()


def reset_settings_cache() -> None:
    get_settings.cache_clear()
