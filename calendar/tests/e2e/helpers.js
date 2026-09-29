const { expect } = require('@playwright/test');

const PASSWORD = process.env.E2E_PASSWORD || 'Navertia-Dev-2026!';
const USERS = {
    admin: 'admin@navertia.demo',
    manager: 'marta.sanz@navertia.demo',
    staff1: 'carlos.ruiz@navertia.demo',
    staff2: 'elena.torres@navertia.demo',
};

async function login(page, who = 'admin') {
    await page.goto('/login');
    await page.fill('input[name=email]', USERS[who] || who);
    await page.fill('input[name=password]', PASSWORD);
    await Promise.all([
        page.waitForURL('**/dashboard'),
        page.click('button[type=submit]'),
    ]);
}

/** Registra errores de consola/JS y respuestas 5xx para asertarlos al final. */
function watchErrors(page) {
    const problems = [];
    page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
    page.on('console', (m) => {
        // Los 4xx esperados (403/404/422) generan un 'Failed to load resource'; se ignoran.
        if (m.type() === 'error' && !m.text().startsWith('Failed to load resource')) { problems.push(`console: ${m.text()}`); }
    });
    page.on('response', (r) => {
        if (r.status() >= 500) { problems.push(`HTTP ${r.status()} ${r.url()}`); }
    });
    return problems;
}

async function shot(page, name) {
    await page.screenshot({ path: `results/${name}.png`, fullPage: true });
}

module.exports = { expect, PASSWORD, USERS, login, watchErrors, shot };
