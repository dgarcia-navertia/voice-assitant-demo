const { test } = require('@playwright/test');
const { expect, login, watchErrors, shot } = require('./helpers');

test.describe('Dial Out', () => {
    let problems;
    test.beforeEach(async ({ page }) => {
        problems = watchErrors(page);
        await login(page, 'admin');
        await page.goto('/dial-out');
    });
    test.afterEach(() => {
        expect(problems.filter((p) => !p.includes('502')), problems.join('\n')).toEqual([]);
    });

    test('por defecto Espana +34, boton deshabilitado sin numero', async ({ page }) => {
        await expect(page.getByTestId('dial-country-code')).toHaveText('+34');
        await expect(page.getByTestId('dial-submit')).toBeDisabled();
        await shot(page, 'dial-out-default');
    });

    test('el desplegable lista todos los paises con bandera y es buscable', async ({ page }) => {
        await page.getByTestId('dial-country-toggle').click();
        const items = page.getByTestId('dial-country-list').locator('button');
        expect(await items.count()).toBeGreaterThan(200);
        await expect(items.first().locator('img')).toHaveAttribute('src', /\/static\/flags\/[a-z]{2}\.svg/);
        await shot(page, 'dial-out-dropdown');

        await page.getByTestId('dial-country-search').fill('portug');
        await expect(items).toHaveCount(1);
        await items.first().click();
        await expect(page.getByTestId('dial-country-code')).toHaveText('+351');

        // busqueda por prefijo
        await page.getByTestId('dial-country-toggle').click();
        await page.getByTestId('dial-country-search').fill('+49');
        await expect(page.getByTestId('dial-country-list').locator('button[data-iso=DE]')).toBeVisible();
    });

    test('valida E.164: invalido deshabilita, valido habilita', async ({ page }) => {
        await page.getByTestId('dial-phone').fill('123');
        await expect(page.getByTestId('dial-submit')).toBeDisabled();
        await expect(page.getByTestId('dial-hint')).toContainText('no válido');
        await page.getByTestId('dial-phone').fill('612 345 678');
        await expect(page.getByTestId('dial-submit')).toBeEnabled();
        await expect(page.getByTestId('dial-hint')).toContainText('+34612345678');
    });

    test('llamada simulada: estados en vivo y enlace a la transcripcion', async ({ page }) => {
        await page.getByTestId('dial-phone').fill('655 000 111');
        await page.getByTestId('dial-submit').click();

        const status = page.getByTestId('dial-status');
        await expect(status).toBeVisible();
        await expect(status).toHaveAttribute('data-status', /queued|ringing|in-progress|completed/);
        await expect(status).toHaveAttribute('data-status', 'ringing', { timeout: 15_000 });
        await expect(status).toHaveAttribute('data-status', 'in-progress', { timeout: 15_000 });
        await shot(page, 'dial-out-in-progress');
        await expect(status).toHaveAttribute('data-status', 'completed', { timeout: 20_000 });

        const link = page.getByTestId('dial-transcript-link');
        await expect(link).toBeVisible({ timeout: 20_000 });
        await shot(page, 'dial-out-completed');
        await link.click();
        await expect(page.getByTestId('transcript')).toContainText('Quiero reservar una cita');
    });

    test('error del bot se muestra al usuario', async ({ page }) => {
        await page.getByTestId('dial-phone').fill('600 000 999'); // el mock responde 502
        await page.getByTestId('dial-submit').click();
        await expect(page.getByTestId('dial-error')).toContainText('rechazó');
    });

    test('POST /dial-out: sin CSRF 403, con CSRF y numero no E.164 422', async ({ page }) => {
        const noCsrf = await page.request.post('/dial-out', { data: { to: '+34612345678' } });
        expect(noCsrf.status()).toBe(403);

        const html = await (await page.request.get('/dial-out')).text();
        const csrf = /dialOut\('([0-9a-f]{64})'\)/.exec(html)[1];
        const bad = await page.request.post('/dial-out', { data: { to: '612345678' }, headers: { 'X-CSRF-Token': csrf } });
        expect(bad.status()).toBe(422);
    });
});
