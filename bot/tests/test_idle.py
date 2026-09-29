import pytest

from src.config.settings import Settings
from src.idle import GOODBYE, IdleController
from src.pipeline import CallInfo, build_aggregators, build_context


@pytest.fixture
def spoken():
    return {"say": [], "bye": []}


@pytest.fixture
def controller(spoken):
    async def say(t):
        spoken["say"].append(t)

    async def bye(t):
        spoken["bye"].append(t)

    return IdleController(max_retries=2, say=say, say_and_hang_up=bye)


async def test_two_reminders_then_goodbye(controller, spoken):
    for _ in range(3):
        await controller.on_idle()
    assert len(spoken["say"]) == 2 and spoken["say"][0] == "¿Sigues ahí?"
    assert spoken["bye"] == [GOODBYE]
    await controller.on_idle()  # already ended: no more speech
    assert len(spoken["bye"]) == 1


async def test_user_speech_resets_counter(controller, spoken):
    await controller.on_idle()
    await controller.on_idle()
    controller.reset()
    await controller.on_idle()
    assert len(spoken["say"]) == 3 and spoken["bye"] == []


def test_aggregator_uses_pipecat_idle_timeout():
    s = Settings()
    ctx = build_context(s, CallInfo("CA" + "1" * 32, "+34611222333", "outbound"))
    user_agg, _ = build_aggregators(ctx, s)
    assert user_agg._params.user_idle_timeout == 20.0
    assert "+34611222333" in ctx.get_messages()[0]["content"]
