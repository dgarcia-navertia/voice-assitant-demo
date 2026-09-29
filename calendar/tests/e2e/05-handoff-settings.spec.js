const { test } = require('@playwright/test');
const { expect, login, watchErrors, shot } = require('./helpers');

const TOKEN = process.env.INTERNAL_API_TOKEN;
const auth = { Authorization: `Bearer ${TOKEN}` };

test.describe('Numero de traspaso (/settings)', () => {
    test('un comercial y un manager no acceden (403) ni ven la pestana', async ({ page }) => {
        for (const who of ['staff1', 'manager']) {
            await login(page, who);
            expect((await page.goto('/settings')).status()).toBe(403);
            const post = await page.request.post('/settings/handoff', { form: { handoff_phone_number: '+34600111222' } });
            expect(post.status()).toBe(403);
            await page.goto('/dashboard');
            await expect(page.locator('body')).not.toContainText('Traspaso');
            await page.context().clearCookies();
        }
    });

    test('sin sesion redirige a /login', async ({ page }) => {
        await page.goto('/settings');
        await expect(page).toHaveURL(/\/login$/);
    });

    test('el admin edita el numero, se audita y la API interna lo devuelve', async ({ page, request }) => {
        const problems = watchErrors(page);
        await login(page, 'admin');
        await page.goto('/settings');
        await expect(page.locator('h1')).toContainText('Traspaso');
        const original = (await page.getByTestId('handoff-current').textContent()).trim();
        const csrf = /name="_csrf" value="([0-9a-f]{64})"/.exec(await page.content())[1];
        await expect(page.getByTestId('dial-country-code')).toBeVisible();
        await shot(page, 'settings-handoff');

        // invalido: boton deshabilitado
        await page.getByTestId('handoff-phone').fill('12');
        await expect(page.getByTestId('handoff-save')).toBeDisabled();
        await expect(page.getByTestId('handoff-hint')).toContainText('no válido');

        // cambia de pais (Portugal) y guarda
        await page.getByTestId('dial-country-toggle').click();
        await page.getByTestId('dial-country-search').fill('portug');
        await page.getByTestId('dial-country-list').locator('button').first().click();
        await page.getByTestId('handoff-phone').fill('912 345 678');
        await expect(page.getByTestId('handoff-e164')).toHaveValue('+351912345678');
        await Promise.all([page.waitForURL('**/settings?saved=1'), page.getByTestId('handoff-save').click()]);
        await expect(page.getByTestId('settings-saved')).toBeVisible();
        await expect(page.getByTestId('handoff-current')).toHaveText('+351912345678');
        await expect(page.getByTestId('handoff-audit')).toContainText('Admin Navertia');

        // recarga: el selector se rellena con el valor guardado
        await expect(page.getByTestId('dial-country-code')).toHaveText('+351');

        const api = await request.get('/mcp/settings/handoff', { headers: auth });
        expect(await api.json()).toMatchObject({ handoff_phone_number: '+351912345678', source: 'db' });

        // restaura el valor original (la BD es compartida con el entorno de desarrollo)
        if (original !== '—') {
            const back = await page.request.post('/settings/handoff', { form: { _csrf: csrf, handoff_phone_number: original } });
            expect(back.ok()).toBeTruthy();
        }
        expect(problems, problems.join('\n')).toEqual([]);
    });

    test('el servidor rechaza un numero no E.164 (422) y exige CSRF', async ({ page }) => {
        await login(page, 'admin');
        await page.goto('/settings');
        const html = await page.content();
        const csrf = /name="_csrf" value="([0-9a-f]{64})"/.exec(html)[1];
        const bad = await page.request.post('/settings/handoff', { form: { _csrf: csrf, handoff_phone_number: '12345' } });
        expect(bad.status()).toBe(422);
        const noCsrf = await page.request.post('/settings/handoff', { form: { handoff_phone_number: '+34600000000' } });
        expect(noCsrf.status()).toBe(403);
    });

    test('la API interna del traspaso exige token', async ({ request }) => {
        expect((await request.get('/mcp/settings/handoff')).status()).toBe(401);
    });
});
