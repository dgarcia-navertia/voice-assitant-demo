const { test } = require('@playwright/test');
const { expect, login, watchErrors, shot } = require('./helpers');

// Todas las vistas cableadas: cada una carga sin 5xx ni errores de JS.
const VIEWS = [
    ['/dashboard', 'Agenda'],
    ['/dashboard?view=calendar', 'Agenda'],
    ['/appointments', 'Citas'],
    ['/appointments/create', null],
    ['/appointments/1', 'Cita #1'],
    ['/clients', 'Clientes'],
    ['/leads', 'Leads'],
    ['/calls', 'Llamadas'],
    ['/calls/CAdemo00000000000000000000000000001', 'Transcripción'],
    ['/dial-out', 'Dial Out'],
    ['/users', 'Usuarios'],
    ['/users/create', null],
    ['/commercials', 'Comerciales'],
    ['/stores', 'Tiendas'],
    ['/stores/create', null],
    ['/holidays', 'Festivos'],
    ['/account', null],
];

test.describe('Vistas (admin)', () => {
    let problems;
    test.beforeEach(async ({ page }) => {
        problems = watchErrors(page);
        await login(page, 'admin');
    });
    test.afterEach(() => {
        expect(problems, problems.join('\n')).toEqual([]);
    });

    for (const [path, heading] of VIEWS) {
        test(`GET ${path}`, async ({ page }) => {
            const res = await page.goto(path);
            expect(res.status()).toBe(200);
            if (heading) { await expect(page.locator('h1').first()).toContainText(heading); }
            await expect(page.locator('body')).not.toContainText('Amado');
            await expect(page.locator('body')).not.toContainText('Mailpit');
            const name = path.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '');
            await shot(page, `view-${name}`);
        });
    }

    test('la navegacion lateral enlaza Agenda, Citas, Dial Out, Llamadas, Leads, Clientes, Ajustes', async ({ page }) => {
        await page.goto('/dashboard');
        for (const label of ['Agenda', 'Citas', 'Dial Out', 'Llamadas', 'Leads', 'Clientes', 'Ajustes']) {
            await expect(page.locator('aside nav').getByText(label, { exact: true })).toBeVisible();
        }
    });

    test('tiendas sembradas: 3 en Valencia', async ({ page }) => {
        await page.goto('/stores');
        for (const n of ['Navertia Ruzafa', 'Navertia Campanar', 'Navertia Puerto']) {
            await expect(page.locator('body')).toContainText(n);
        }
    });

    test('alta de cliente', async ({ page }) => {
        await page.goto('/clients');
        const phone = '+3460' + Math.floor(1000000 + Math.random() * 8999999);
        await page.fill('#client_name', 'Cliente E2E');
        await page.fill('#client_phone', phone);
        await Promise.all([page.waitForURL('**/clients'), page.click('[data-testid=client-form] button[type=submit]')]);
        await expect(page.locator('[data-testid=clients-table]')).toContainText(phone);
    });

    test('alta de cliente con telefono invalido muestra error', async ({ page }) => {
        await page.goto('/clients');
        await page.fill('#client_name', 'Sin telefono');
        await page.fill('#client_phone', '12345');
        await page.click('[data-testid=client-form] button[type=submit]');
        await expect(page.locator('body')).toContainText('formato internacional');
    });

    test('la transcripcion sembrada se muestra', async ({ page }) => {
        await page.goto('/calls/CAdemo00000000000000000000000000001');
        await expect(page.locator('[data-testid=transcript]')).toContainText('asistente virtual de Navertia');
    });

    test('404 para rutas inexistentes', async ({ page }) => {
        const res = await page.goto('/no-existe');
        expect(res.status()).toBe(404);
    });
});

test.describe('Modo claro/oscuro', () => {
    test('el interruptor cambia data-theme y persiste', async ({ page }) => {
        await login(page, 'admin');
        const theme = () => page.evaluate(() => document.documentElement.dataset.theme);
        const start = await theme();
        await page.locator('aside [data-theme-toggle]').click();
        const flipped = await theme();
        expect(flipped).not.toBe(start);
        await page.reload();
        expect(await theme()).toBe(flipped);
        await shot(page, `dashboard-${flipped}`);
        // los tokens de marca existen como variables CSS
        const primary = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--primary').trim());
        expect(primary).not.toBe('');
    });

    test('paleta clara: primary #075056', async ({ page }) => {
        await page.emulateMedia({ colorScheme: 'light' });
        await page.goto('/login');
        const v = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--primary').trim());
        expect(v).toBe('7 80 86');
    });
});
