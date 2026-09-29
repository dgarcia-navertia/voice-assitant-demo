import pytest

from src.config.providers import ProviderConfigError, build_llm, build_stt, build_tts
from src.config.settings import Settings


def test_defaults_from_empty_env():
    s = Settings.from_env({})
    assert s.llm_provider == "google"
    assert s.llm_model_id == "gemini-3.5-flash-lite"
    assert s.stt_model_id == "nova-3-general" and s.stt_language == "es"
    assert s.tts_model_id == "eleven_flash_v2_5"
    assert s.tts_voice_id == "dNjJKg63Fr5AXwIdkATa"
    assert s.idle_timeout_secs == 20 and s.idle_max_retries == 2
    assert s.greeting == "Soy el asistente virtual de Navertia. ¿En qué te puedo ayudar?"


def test_env_overrides_and_ws_url():
    s = Settings.from_env({"LLM_PROVIDER": "Anthropic", "PUBLIC_BASE_URL": "https://x.io/", "IDLE_TIMEOUT_SECS": "5"})
    assert s.llm_provider == "anthropic"
    assert s.public_ws_url == "wss://x.io/ws"
    assert s.idle_timeout_secs == 5


@pytest.mark.parametrize(
    "provider,key_field,cls",
    [
        ("google", "google_api_key", "GoogleLLMService"),
        ("anthropic", "anthropic_api_key", "AnthropicLLMService"),
        ("openrouter", "openrouter_api_key", "OpenRouterLLMService"),
        ("openai", "openai_api_key", "OpenAILLMService"),
    ],
)
def test_llm_factory_per_provider(provider, key_field, cls):
    s = Settings(llm_provider=provider, llm_model_id="some-model", **{key_field: "k"})
    assert type(build_llm(s)).__name__ == cls


def test_llm_factory_requires_key_and_known_provider():
    with pytest.raises(ProviderConfigError):
        build_llm(Settings(llm_provider="google"))
    with pytest.raises(ProviderConfigError, match="no soportado"):
        build_llm(Settings(llm_provider="nope"))


def test_stt_tts_use_configured_models():
    s = Settings(deepgram_api_key="d", eleven_labs_api_key="e")
    stt, tts = build_stt(s), build_tts(s)
    assert type(stt).__name__ == "DeepgramSTTService"
    assert type(tts).__name__ == "ElevenLabsTTSService"
    assert stt._settings.model == "nova-3-general"
    assert tts._settings.model == "eleven_flash_v2_5"
    assert tts._settings.voice == "dNjJKg63Fr5AXwIdkATa"
