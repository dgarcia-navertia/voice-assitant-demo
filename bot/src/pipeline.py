"""Voice pipeline: transport -> Deepgram STT -> LLM -> ElevenLabs TTS.

Written for pipecat-ai 1.12.0 (PipelineWorker / WorkerRunner, LLMContext +
LLMContextAggregatorPair, user idle via LLMUserAggregatorParams). No pipecat-flows.
"""

from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from loguru import logger
from pipecat.audio.vad.silero import SileroVADAnalyzer
from pipecat.frames.frames import EndFrame, TTSSpeakFrame
from pipecat.pipeline.pipeline import Pipeline
from pipecat.pipeline.worker import PipelineParams, PipelineWorker
from pipecat.processors.aggregators.llm_context import LLMContext
from pipecat.processors.aggregators.llm_response_universal import (
    LLMContextAggregatorPair,
    LLMUserAggregatorParams,
)
from pipecat.transports.base_transport import BaseTransport
from pipecat.workers.runner import WorkerRunner

from src.clients.php_api import PhpApiClient, PhpApiError
from src.clients.twilio_client import TwilioGateway
from src.config.providers import build_llm, build_stt, build_tts
from src.config.settings import Settings
from src.idle import IdleController
from src.prompts import CALL_START_MARKER, build_system_prompt
from src.tools import ToolContext, build_tools_schema, register_tools
from src.transcripts import extract_turns


@dataclass
class CallInfo:
    call_sid: str
    customer_phone: str | None = None
    direction: str = "inbound"  # inbound | outbound | webrtc

    @property
    def is_telephony(self) -> bool:
        return self.call_sid.startswith("CA")


def build_context(settings: Settings, info: CallInfo) -> LLMContext:
    prompt = build_system_prompt(
        timezone=settings.timezone,
        customer_phone=info.customer_phone,
        direction=info.direction,
    )
    return LLMContext(
        messages=[
            {"role": "system", "content": prompt},
            {"role": "user", "content": CALL_START_MARKER},
        ],
        tools=build_tools_schema(),
    )


def build_aggregators(context: LLMContext, settings: Settings):
    """User/assistant aggregators with VAD and the 1.12 idle timeout."""
    return LLMContextAggregatorPair(
        context,
        user_params=LLMUserAggregatorParams(
            vad_analyzer=SileroVADAnalyzer(),
            user_idle_timeout=settings.idle_timeout_secs,
        ),
    )


async def run_pipeline(
    transport: BaseTransport,
    *,
    settings: Settings,
    php: PhpApiClient,
    twilio: TwilioGateway,
    info: CallInfo,
    sample_rate: int | None = None,
    llm: Any = None,
    stt: Any = None,
    tts: Any = None,
) -> ToolContext:
    """Run one call until the transport disconnects or the idle logic hangs up."""
    ctx = ToolContext(
        settings=settings, php=php, twilio=twilio, call_sid=info.call_sid,
        customer_phone=info.customer_phone, direction=info.direction,
    )
    stt = stt or build_stt(settings)
    llm = llm or build_llm(settings)
    tts = tts or build_tts(settings)
    register_tools(llm, ctx)

    context = build_context(settings, info)
    user_agg, assistant_agg = build_aggregators(context, settings)

    pipeline = Pipeline([
        transport.input(), stt, user_agg, llm, tts, transport.output(), assistant_agg,
    ])
    params = PipelineParams(
        **({"audio_in_sample_rate": sample_rate, "audio_out_sample_rate": sample_rate} if sample_rate else {})
    )
    worker = PipelineWorker(pipeline, params=params)

    async def say(text: str) -> None:
        await worker.queue_frame(TTSSpeakFrame(text))

    async def say_and_hang_up(text: str) -> None:
        # EndFrame is queued behind the speech, so the goodbye is fully played.
        await worker.queue_frames([TTSSpeakFrame(text), EndFrame()])

    idle = IdleController(max_retries=settings.idle_max_retries, say=say, say_and_hang_up=say_and_hang_up)

    @user_agg.event_handler("on_user_turn_started")
    async def _on_turn_started(aggregator, *args):
        idle.reset()

    @user_agg.event_handler("on_user_turn_idle")
    async def _on_idle(aggregator, *args):
        await idle.on_idle()

    @transport.event_handler("on_client_connected")
    async def _on_connected(transport, client):
        logger.info("Client connected (call {})", info.call_sid)
        await say(settings.greeting)  # the bot speaks first

    @transport.event_handler("on_client_disconnected")
    async def _on_disconnected(transport, client):
        logger.info("Client disconnected (call {})", info.call_sid)
        await worker.cancel()

    runner = WorkerRunner(handle_sigint=False)
    await runner.add_workers(worker)
    try:
        await runner.run()
    finally:
        await _finish_call(ctx, context, php, twilio)
    return ctx


async def _finish_call(ctx: ToolContext, context: LLMContext, php: PhpApiClient, twilio: TwilioGateway) -> None:
    turns = extract_turns(context.get_messages())
    if turns:
        try:
            await php.save_transcripts(ctx.call_sid, turns)
            logger.info("Saved {} transcript turns for {}", len(turns), ctx.call_sid)
        except PhpApiError as exc:
            logger.error("Transcript not saved for {}: {}", ctx.call_sid, exc.message)
    # We don't use the serializer's auto hang-up: it would also kill a call we
    # just transferred to a human. Hang up ourselves, unless transferred.
    if ctx.is_telephony and not ctx.transferred:
        try:
            await twilio.hangup(ctx.call_sid)
        except Exception as exc:  # already ended is fine
            logger.debug("Hangup skipped: {}", exc)
