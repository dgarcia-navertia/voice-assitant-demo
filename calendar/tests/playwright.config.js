// Smoke e2e del panel. Corre en Docker (make test-e2e) contra php-e2e, cuyo
// "bot" es calendar/tests/mock-bot.js: no se hace ninguna llamada real de Twilio.
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './e2e',
    outputDir: './results/artifacts',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL: process.env.BASE_URL || 'http://localhost:8090',
        trace: 'off',
        screenshot: 'off',
        ...devices['Desktop Chrome'],
        locale: 'es-ES',
    },
});
