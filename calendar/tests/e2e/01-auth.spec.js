const { test } = require('@playwright/test');
const { expect, login, shot, PASSWORD } = require('./helpers');

test.describe('Autenticacion', () => {
    test('la raiz redirige a /login y muestra la marca Navertia', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveURL(/\/login$/);
        await expect(page.locator('img[alt="Navertia"]').first()).toBeAttached();
        await expect(page.locator('body')).not.toContainText('Amado Salvador');
        await shot(page, 'login-light');
    });

    test('credenciales incorrectas muestran error', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[name=email]', 'admin@navertia.demo');
        await page.fill('input[name=password]', 'incorrecta');
        await page.click('button[type=submit]');
        await expect(page.getByRole('alert')).toContainText('incorrectos');
    });

    test('rutas protegidas redirigen a /login sin sesion', async ({ page }) => {
        for (const path of ['/dashboard', '/dial-out', '/calls', '/leads', '/clients', '/users']) {
            await page.goto(path);
            await expect(page).toHaveURL(/\/login$/);
        }
    });

    test('login admin, y logout', async ({ page }) => {
        await login(page, 'admin');
        await expect(page.locator('h1')).toContainText('Agenda');
        await page.locator('aside form[action="/logout"] button').click();
        await expect(page).toHaveURL(/\/login$/);
    });

    test('roles: un comercial no accede a Usuarios (403)', async ({ page }) => {
        await login(page, 'staff1');
        const res = await page.goto('/users');
        expect(res.status()).toBe(403);
        const ok = await page.goto('/dial-out');
        expect(ok.status()).toBe(200);
    });
});
