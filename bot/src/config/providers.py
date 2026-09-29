"""Factories for the LLM / STT / TTS services, driven by `Settings`.

Verified against pipecat-ai 1.12.0:
- `Service.Settings(...)` is the settings container of every service.
- Deepgram: `pipecat.services.deepgram.stt.DeepgramSTTService`
- ElevenLabs: `pipecat.services.elevenlabs.tts.ElevenLabsTTSService`
- LLMs: google / anthropic / openai / openrouter under `pipecat.services.<x>.llm`
"""

from __future__ import annotations

from src.config.settings import LLM_PROVIDERS, Settings


class ProviderConfigError(ValueError):
    """A provider is selected but its credentials/config are missing."""


def build_llm(s: Settings):
    provider = s.llm_provider
    if provider == "google":
        from pipecat.services.google.llm import GoogleLLMService, GoogleThinkingConfig

        _require(s.google_api_key, "GOOGLE_API_KEY", provider)
        return GoogleLLMService(
            api_key=s.google_api_key,
            settings=GoogleLLMService.Settings(
                model=s.llm_model_id,
                temperature=s.llm_temperature,
                # Minimal thinking keeps latency low for voice.
                thinking=GoogleThinkingConfig(thinking_level="minimal"),
            ),
        )
    if provider == "anthropic":
        from pipecat.services.anthropic.llm import AnthropicLLMService

        _require(s.anthropic_api_key, "ANTHROPIC_API_KEY", provider)
        return AnthropicLLMService(
            api_key=s.anthropic_api_key,
            settings=AnthropicLLMService.Settings(
                model=s.llm_model_id, temperature=s.llm_temperature
            ),
        )
    if provider == "openrouter":
        from pipecat.services.openrouter.llm import OpenRouterLLMService

        _require(s.openrouter_api_key, "OPENROUTER_API_KEY", provider)
        return OpenRouterLLMService(
            api_key=s.openrouter_api_key,
            settings=OpenRouterLLMService.Settings(
                model=s.llm_model_id, temperature=s.llm_temperature
            ),
        )
    if provider == "openai":
        from pipecat.services.openai.llm import OpenAILLMService

        _require(s.openai_api_key, "OPENAI_API_KEY", provider)
        return OpenAILLMService(
            api_key=s.openai_api_key,
            settings=OpenAILLMService.Settings(
                model=s.llm_model_id, temperature=s.llm_temperature
            ),
        )
    raise ProviderConfigError(
        f"LLM_PROVIDER '{provider}' no soportado. Opciones: {', '.join(LLM_PROVIDERS)}"
    )


def build_stt(s: Settings):
    from pipecat.services.deepgram.stt import DeepgramSTTService

    _require(s.deepgram_api_key, "DEEPGRAM_API_KEY", "deepgram")
    return DeepgramSTTService(
        api_key=s.deepgram_api_key,
        settings=DeepgramSTTService.Settings(
            model=s.stt_model_id,
            language=s.stt_language,
            punctuate=True,
            interim_results=True,
            smart_format=False,
        ),
    )


def build_tts(s: Settings):
    from pipecat.services.elevenlabs.tts import ElevenLabsTTSService

    _require(s.eleven_labs_api_key, "ELEVEN_LABS_API_KEY", "elevenlabs")
    return ElevenLabsTTSService(
        api_key=s.eleven_labs_api_key,
        settings=ElevenLabsTTSService.Settings(
            model=s.tts_model_id,
            voice=s.tts_voice_id,
            language=s.tts_language,
        ),
    )


def _require(value: str, env_name: str, provider: str) -> None:
    if not value:
        raise ProviderConfigError(f"{env_name} es obligatorio para el proveedor '{provider}'.")
