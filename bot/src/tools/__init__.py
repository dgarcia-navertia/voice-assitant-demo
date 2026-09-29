"""Function-calling tools exposed to the LLM (no pipecat-flows)."""

from __future__ import annotations

from functools import partial
from typing import Any

from pipecat.adapters.schemas.function_schema import FunctionSchema
from pipecat.adapters.schemas.tools_schema import ToolsSchema
from pipecat.frames.frames import FunctionCallResultProperties
from pipecat.services.llm_service import FunctionCallParams

from src.tools import handlers
from src.tools.context import ToolContext

TOOL_HANDLERS = {
    "get_company_info": handlers.get_company_info,
    "check_availability": handlers.check_availability,
    "book_appointment": handlers.book_appointment,
    "commercial_handoff": handlers.commercial_handoff,
    "end_call": handlers.end_call,
}


def build_tools_schema() -> ToolsSchema:
    return ToolsSchema(
        standard_tools=[
            FunctionSchema(
                name="get_company_info",
                description=(
                    "Devuelve en vivo las tiendas de Navertia: id, nombre, dirección, teléfono y horarios. "
                    "Úsala para informar de tiendas/horarios y para conocer el store_id antes de reservar."
                ),
                properties={},
                required=[],
            ),
            FunctionSchema(
                name="check_availability",
                description="Consulta las horas libres de una tienda en un día concreto.",
                properties={
                    "store_id": {"type": "integer", "description": "Id de la tienda (de get_company_info)."},
                    "date": {"type": "string", "description": "Día en formato AAAA-MM-DD."},
                },
                required=["store_id", "date"],
            ),
            FunctionSchema(
                name="book_appointment",
                description=(
                    "Reserva una cita. Crea el cliente si es nuevo (el teléfono de la llamada ya se conoce). "
                    "Llámala solo tras confirmar con la persona nombre, tienda, día y hora."
                ),
                properties={
                    "client_name": {"type": "string", "description": "Nombre completo."},
                    "store_id": {"type": "integer", "description": "Id de la tienda."},
                    "date": {"type": "string", "description": "Día AAAA-MM-DD."},
                    "time": {"type": "string", "description": "Hora HH:MM (24 h), una de las devueltas por check_availability."},
                    "client_email": {"type": "string", "description": "Correo electrónico (opcional)."},
                    "phone": {"type": "string", "description": "Teléfono E.164; solo si la llamada no lo trae."},
                },
                required=["client_name", "store_id", "date", "time"],
            ),
            FunctionSchema(
                name="commercial_handoff",
                description=(
                    "Transfiere la llamada en vivo a un asesor comercial humano y registra un lead. "
                    "Úsala si la persona pide hablar con alguien o si no puedes resolver su consulta."
                ),
                properties={
                    "reason": {"type": "string", "description": "Motivo breve de la consulta."},
                    "caller_name": {"type": "string", "description": "Nombre de la persona, si lo dio."},
                    "store_id": {"type": "integer", "description": "Tienda de interés, si la hay."},
                    "mode": {"type": "string", "enum": ["warm", "cold"],
                             "description": "warm avisa antes de transferir (por defecto); cold transfiere directamente."},
                },
                required=["reason"],
            ),
            FunctionSchema(
                name="end_call",
                description=(
                    "Termina la llamada: dice una despedida fija y cuelga. Úsala solo cuando la persona diga "
                    "claramente que no necesita nada más o se despida; nunca tras un \"sí\". "
                    "No digas nada tú: la herramienta ya se despide."
                ),
                properties={},
                required=[],
            ),
        ]
    )


def register_tools(llm: Any, ctx: ToolContext) -> None:
    """Register every handler on the LLM service (pipecat 1.12: register_function)."""

    for name, fn in TOOL_HANDLERS.items():
        llm.register_function(name, partial(_dispatch, fn, ctx))


async def _dispatch(fn: Any, ctx: ToolContext, params: FunctionCallParams) -> None:
    if params.context is not None:
        ctx.user_turns = count_user_turns(params.context.get_messages())
    result = await fn(ctx, **(params.arguments or {}))
    if isinstance(result, dict) and result.get("ok") is False and fn is not handlers.commercial_handoff:
        ctx.tool_failed_turn = ctx.user_turns
    if isinstance(result, dict) and result.get("hang_up"):
        # No new LLM turn: the fixed goodbye is spoken, then EndFrame closes the call.
        await params.result_callback(result, properties=FunctionCallResultProperties(run_llm=False))
        await ctx.say_and_hang_up(handlers.FAREWELL)
        return
    await params.result_callback(result)


def count_user_turns(messages: list[Any]) -> int:
    return sum(1 for m in messages if isinstance(m, dict) and m.get("role") == "user")


__all__ = ["ToolContext", "build_tools_schema", "register_tools", "TOOL_HANDLERS"]
