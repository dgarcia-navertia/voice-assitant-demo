// Bot simulado para e2e. Imita lo que ve PHP del bot real:
//   POST /dial-out (Bearer INTERNAL_API_TOKEN) -> {call_sid, status:"queued", to}
// y, como haria el callback de Twilio relayado, empuja estados a PHP:
//   queued -> ringing -> in-progress -> (transcripcion) -> completed
// No usa Twilio. Solo Node estandar.
const http = require('http');

const TOKEN = process.env.INTERNAL_API_TOKEN;
const PHP = process.env.PHP_BASE_URL || 'http://php-e2e';
const STEP_MS = Number(process.env.MOCK_STEP_MS || 3500);
const calls = [];

async function pushStatus(sid, status, extra = {}) {
    await fetch(`${PHP}/mcp/calls/status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${TOKEN}` },
        body: JSON.stringify({ call_sid: sid, status, direction: 'outbound', ...extra }),
    }).catch((e) => console.error('mock-bot push failed', e.message));
}

async function pushTranscript(sid) {
    await fetch(`${PHP}/mcp/transcripts/batch`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${TOKEN}` },
        body: JSON.stringify({
            sid,
            turns: [
                { role: 'assistant', transcript_text: 'Soy el asistente virtual de Navertia. ¿En qué te puedo ayudar?', turn_index: 0 },
                { role: 'user', transcript_text: 'Quiero reservar una cita.', turn_index: 1 },
            ],
        }),
    }).catch((e) => console.error('mock-bot transcript failed', e.message));
}

async function simulate(sid, to) {
    const wait = (ms) => new Promise((r) => setTimeout(r, ms));
    await wait(STEP_MS);      await pushStatus(sid, 'ringing', { to });
    await wait(STEP_MS);      await pushStatus(sid, 'in-progress', { to });
    await wait(STEP_MS);      await pushTranscript(sid);
    await pushStatus(sid, 'completed', { to, duration: 7 });
}

http.createServer((req, res) => {
    const send = (code, body) => { res.writeHead(code, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(body)); };
    if (req.method === 'GET' && req.url === '/health') { return send(200, { status: 'ok' }); }
    if (req.method === 'GET' && req.url === '/calls') { return send(200, calls); }
    if (req.method === 'POST' && req.url === '/dial-out') {
        if (req.headers.authorization !== `Bearer ${TOKEN}`) { return send(401, { detail: 'unauthorized' }); }
        let raw = '';
        req.on('data', (c) => (raw += c));
        req.on('end', () => {
            let body = {};
            try { body = JSON.parse(raw); } catch (e) { /* vacio */ }
            if (!/^\+[1-9]\d{6,14}$/.test(body.to || '')) { return send(422, { detail: 'invalid number' }); }
            if (body.to === '+34600000999') { return send(502, { detail: 'Twilio simulado: fallo' }); }
            const sid = 'CAmock' + Date.now().toString(16) + Math.random().toString(16).slice(2, 8);
            calls.push({ sid, to: body.to });
            send(200, { call_sid: sid, status: 'queued', to: body.to });
            simulate(sid, body.to);
        });
        return;
    }
    send(404, { detail: 'not found' });
}).listen(8000, '0.0.0.0', () => console.log('mock-bot escuchando en :8000'));
