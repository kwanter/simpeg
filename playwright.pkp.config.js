import { defineConfig, devices } from '@playwright/test';
import { spawnSync } from 'node:child_process';
import { closeSync, existsSync, mkdirSync, openSync } from 'node:fs';
import path, { isAbsolute } from 'node:path';

const databasePath = path.resolve('storage/framework/testing-pkp.sqlite');
const baseURL = process.env.PKP_E2E_BASE_URL || 'http://127.0.0.1:8001';
const qpdfBinary = process.env.PKP_QPDF_BINARY;

if (!qpdfBinary || !isAbsolute(qpdfBinary) || !existsSync(qpdfBinary)) {
  throw new Error('PKP_E2E requires an absolute, existing PKP_QPDF_BINARY path.');
}

const qpdfVersion = spawnSync(qpdfBinary, ['--version'], { encoding: 'utf8' });
const qpdfVersionMatch = `${qpdfVersion.stdout}${qpdfVersion.stderr}`.match(/qpdf version (\d+)\./i);
if (qpdfVersion.status !== 0 || !qpdfVersionMatch || Number(qpdfVersionMatch[1]) < 11) {
  throw new Error('PKP_E2E requires qpdf version 11 or newer in the Playwright runner.');
}

mkdirSync(path.dirname(databasePath), { recursive: true });
if (!existsSync(databasePath)) {
  closeSync(openSync(databasePath, 'w'));
}

export default defineConfig({
  testDir: './e2e',
  testMatch: 'pkp.spec.js',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL,
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        launchOptions: process.env.PKP_CHROMIUM_EXECUTABLE
          ? { executablePath: process.env.PKP_CHROMIUM_EXECUTABLE }
          : undefined,
      },
    },
  ],
  webServer: {
    command: 'php -r "if (!is_executable(getenv(\'PKP_QPDF_BINARY\'))) exit(1);" && qpdf --version && php artisan migrate:fresh --force --seeder=PkpE2EFixtureSeeder && php artisan serve --host=127.0.0.1 --port=8001',
    url: baseURL,
    reuseExistingServer: false,
    timeout: 120 * 1000,
    env: {
      APP_ENV: 'testing',
      APP_URL: baseURL,
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: databasePath,
      PKP_QPDF_BINARY: qpdfBinary,
      CACHE_STORE: 'file',
      CACHE_DRIVER: 'file',
      SESSION_DRIVER: 'file',
      QUEUE_CONNECTION: 'sync',
    },
  },
});
