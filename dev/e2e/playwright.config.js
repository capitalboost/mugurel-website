// Teste in browser — doar local. Ruleaza prin dev/e2e/run.sh (copiaza testele intr-un
// director din afara Google Drive, unde sunt instalate node_modules).
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  timeout: 30000,
  retries: 0,
  workers: 1, // serverul PHP incorporat raspunde la o cerere pe rand
  reporter: [['list']],
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8080',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'mobil', use: { viewport: { width: 375, height: 800 }, isMobile: true, hasTouch: true } },
    { name: 'tableta', use: { viewport: { width: 768, height: 1024 } } },
    { name: 'desktop', use: { viewport: { width: 1280, height: 900 } } },
  ],
});
