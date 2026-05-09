// @ts-check
import { test, expect } from '@playwright/test';

test('guest can see login page', async ({ page }) => {
  await page.goto('/login');

  await expect(page).toHaveTitle(/Sistem Informasi Pegawai|Simpeg/i);
  await expect(page.getByLabel(/Email atau NIP/i)).toBeVisible();
  await expect(page.getByLabel(/Kata Sandi/i)).toBeVisible();
  await expect(page.getByRole('button', { name: /Masuk/i })).toBeVisible();
});

test('guest is redirected from dashboard to login', async ({ page }) => {
  await page.goto('/dashboard');

  await expect(page).toHaveURL(/\/login$/);
});
