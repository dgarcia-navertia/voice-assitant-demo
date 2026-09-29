# Escalar la conversación: `pipecat-flows` (recomendado)

Esta demo usa **un único prompt + function calling**. Es lo más sencillo para un caso acotado (reservar,
informar, traspasar). Cuando la conversación crezca, **`pipecat-flows` es la vía recomendada para escalar**.

## Cuándo migrar

- Aparecen varias etapas claramente distintas (identificar cliente → elegir servicio → elegir hueco → confirmar →
  datos opcionales → cierre) y el prompt único se vuelve largo o frágil.
- Quieres **herramientas distintas por etapa** (que el LLM solo pueda "reservar" cuando ya tiene tienda, hueco y
  nombre) y transiciones explícitas y testeables.
- Necesitas menos alucinaciones de flujo, mensajes de sistema específicos por etapa, o reencuadrar el contexto
  al cambiar de tarea (p. ej. reprogramar / cancelar citas, resolver incidencias).
- Quieres medir el embudo (en qué nodo se abandona) o cambiar el flujo sin tocar un prompt monolítico.

## Cómo encajaría aquí

1. Añadir `pipecat-ai-flows` (versión compatible con Pipecat 1.12) a `bot/pyproject.toml`.
2. Convertir las herramientas de `bot/src/tools/` en funciones de nodo: los *handlers* ya son independientes del
   LLM y devuelven `{"ok": …}`, así que se reutilizan tal cual; solo cambia quién las registra.
3. Trocear `bot/src/prompts/system.py` en un mensaje de rol común y una *task message* por nodo
   (`inicio`, `tienda`, `hueco`, `datos`, `confirmar`, `traspaso`, `despedida`).
4. Sustituir en `bot/src/pipeline.py` el registro directo de herramientas por un `FlowManager`
   que inicializa el primer nodo en `on_client_connected` (el saludo se mantiene).
5. Mantener sin cambios el transporte, STT/TTS, la inactividad, el traspaso real por Twilio y el guardado de
   transcripciones: la API interna de PHP no cambia.

## Qué no cambia

El contrato con el panel (`/mcp/*`), Dial Out, los estados de llamada, las transcripciones y el despliegue.
La migración es local a `bot/src/`.

> El proyecto de referencia `webrtc` ya usa `pipecat-flows` y sirve de modelo de estructura de nodos; aquí se ha
> evitado a propósito para mantener la demo mínima.
