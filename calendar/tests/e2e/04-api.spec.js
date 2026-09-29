const { test } = require('@playwright/test');
const { expect } = require('./helpers');

const TOKEN = process.env.INTERNAL_API_TOKEN;
const auth = { Authorization: `Bearer ${TOKEN}` };

test.describe('API interna /mcp', () => {
    test('sin token -> 401', async ({ request }) => {
        const r = await request.get('/mcp/stores');
        expect(r.status()).toBe(401);
        expect((await r.json()).error).toBe('UNAUTHORIZED');
    });

    test('token invalido -> 401', async ({ request }) => {
        const r = await request.get('/mcp/stores', { headers: { Authorization: 'Bearer nope' } });
        expect(r.status()).toBe(401);
    });

    test('tiendas y horarios', async ({ request }) => {
        const r = await request.get('/mcp/stores', { headers: auth });
        expect(r.status()).toBe(200);
        const { stores } = await r.json();
        expect(stores.length).toBeGreaterThanOrEqual(3);
        expect(stores[0].schedule).toHaveProperty('mon_to_friday');
    });

    test('disponibilidad, cliente, cita, lead, transcripcion y estado de llamada', async ({ request }) => {
        // proximo lunes
        const d = new Date(); d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7));
        const date = d.toISOString().slice(0, 10);
        const av = await request.get(`/mcp/availability?store_id=1&date=${date}`, { headers: auth });
        expect(av.status()).toBe(200);
        const { slots } = await av.json();
        expect(slots.length).toBeGreaterThan(0);

        const phone = '+3461' + Math.floor(1000000 + Math.random() * 8999999);
        const cl = await request.post('/mcp/clients', { headers: auth, data: { client_name: 'API E2E', client_phone: phone } });
        expect(cl.status()).toBe(201);
        const client = (await cl.json()).client;
        const found = await request.get(`/mcp/clients/by-phone?phone=${encodeURIComponent(phone)}`, { headers: auth });
        expect((await found.json()).client.id).toBe(client.id);

        // hueco libre: se elige el ultimo de la lista para evitar choques entre ejecuciones
        const time = slots[slots.length - 1].time;
        const ap = await request.post('/mcp/appointments', { headers: auth, data: { store_id: 1, client_id: client.id, starts_at: `${date} ${time}:00` } });
        expect([201, 409]).toContain(ap.status());

        const past = await request.post('/mcp/appointments', { headers: auth, data: { store_id: 1, client_id: client.id, starts_at: '2020-01-06 10:00:00' } });
        expect(past.status()).toBe(422);

        const lead = await request.post('/mcp/leads', { headers: auth, data: { phone, reason: 'e2e', transferred: true } });
        expect(lead.status()).toBe(201);

        const sid = 'CAe2e' + Date.now();
        const tr = await request.post('/mcp/transcripts/batch', { headers: auth, data: { sid, turns: [{ role: 'user', transcript_text: 'hola', turn_index: 0 }] } });
        expect(tr.status()).toBe(201);
        const st = await request.post('/mcp/calls/status', { headers: auth, data: { call_sid: sid, status: 'ringing', to: phone } });
        expect((await st.json()).call.status).toBe('ringing');
        const done = await request.post('/mcp/calls/status', { headers: auth, data: { call_sid: sid, status: 'completed' } });
        expect((await done.json()).call.status).toBe('completed');
        // un estado no terminal no pisa uno terminal
        const late = await request.post('/mcp/calls/status', { headers: auth, data: { call_sid: sid, status: 'ringing' } });
        expect((await late.json()).call.status).toBe('completed');
        const bad = await request.post('/mcp/calls/status', { headers: auth, data: { call_sid: sid, status: 'weird' } });
        expect(bad.status()).toBe(422);
    });
});
