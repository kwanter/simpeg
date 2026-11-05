import { expect, test } from '@playwright/test';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

async function signIn(page, email) {
  await page.addInitScript(() => {
    const style = document.createElement('style');
    style.textContent = '* { animation-duration: 0s !important; transition-duration: 0s !important; }';
    document.documentElement.append(style);
  });
  await page.goto('/login');
  await page.getByLabel('Email atau NIP').fill(email);
  await page.getByLabel('Kata Sandi').fill('pkp-testing-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await expect(page).not.toHaveURL(/\/login$/);
}

test('ordinary owner can open the monthly PKP form on desktop', async ({ page }) => {
  await signIn(page, 'pkp-owner@example.test');
  await expect(page.getByRole('link', { name: 'PKP Bulanan' })).toBeVisible();
  await page.getByRole('link', { name: 'PKP Bulanan' }).click();
  await expect(page.getByRole('heading', { name: 'Daftar PKP Bulanan', level: 1 })).toBeVisible();
  await page.getByRole('link', { name: 'Buat PKP Bulanan' }).click();
  await expect(page.getByRole('heading', { name: 'Buat PKP Bulanan', level: 1 })).toBeVisible();
});

test('ordinary owner can open the monthly PKP form from mobile navigation', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await signIn(page, 'pkp-owner@example.test');
  await page.getByRole('button', { name: 'Buka navigasi' }).click();
  await expect(page.getByRole('link', { name: 'PKP Bulanan' })).toBeVisible();
  await page.getByRole('link', { name: 'PKP Bulanan' }).click();
  await expect(page.getByRole('heading', { name: 'Daftar PKP Bulanan', level: 1 })).toBeVisible();
  await page.getByRole('link', { name: 'Buat PKP Bulanan' }).click();
  await expect(page.getByRole('heading', { name: 'Buat PKP Bulanan', level: 1 })).toBeVisible();
});

test('unlinked administrator sees the all-employee PKP entry', async ({ page }) => {
  await signIn(page, 'pkp-admin@example.test');
  await expect(page.getByRole('link', { name: 'PKP Semua Pegawai' })).toHaveAttribute('href', /pkp\?scope=all/);
  await page.getByRole('link', { name: 'PKP Semua Pegawai' }).click();
  await expect(page.getByRole('heading', { name: 'Daftar PKP Bulanan', level: 1 })).toBeVisible();
  await expect(page.getByLabel('Cakupan')).toHaveValue('all');
  await page.getByRole('link', { name: 'Batas Waktu PKP' }).click();
  await expect(page.getByRole('heading', { name: /^Batas Waktu PKP \d{2}\/\d{4}$/, level: 1 })).toBeVisible();
  const period = Object.fromEntries(new Intl.DateTimeFormat('en', {
    timeZone: 'Asia/Makassar',
    year: 'numeric',
    month: '2-digit',
  }).formatToParts(new Date()).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
  const deadline = `${period.year}-${period.month}-28T23:59:59`;
  await page.getByLabel('Batas waktu lokal').fill(deadline);
  await page.getByRole('button', { name: 'Simpan batas waktu' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Batas waktu PKP berhasil disimpan.' })).toBeVisible();
  await expect(page.getByLabel('Batas waktu lokal')).toHaveValue(deadline);
});

test('owner can create and edit an ordered activity tree', async ({ page }) => {
  const period = Object.fromEntries(new Intl.DateTimeFormat('en', {
    timeZone: 'Asia/Makassar',
    year: 'numeric',
    month: 'numeric',
  }).formatToParts(new Date()).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));

  await signIn(page, 'pkp-owner@example.test');
  await page.getByRole('link', { name: 'PKP Bulanan' }).click();
  await page.getByRole('link', { name: 'Buat PKP Bulanan' }).click();
  await page.getByLabel('Tahun').fill(period.year);
  await page.getByLabel('Bulan').fill(period.month);
  await page.getByRole('button', { name: 'Buat draf' }).click();
  await expect(page.getByRole('heading', { name: 'Ubah draf PKP', level: 1 })).toBeVisible();

  await page.getByRole('button', { name: 'Tambah indikator' }).click();
  await page.getByLabel('Judul indikator').fill('Kualitas pelayanan');
  await page.getByRole('button', { name: 'Tambah aktivitas' }).click();
  await page.getByLabel('Uraian aktivitas').fill('Menyelesaikan target kerja');
  await page.getByLabel('Satuan target dan realisasi').fill('dokumen');
  await page.getByLabel('Target kredit').fill('1.250000');
  await page.getByLabel('Target kuantitas').fill('3.0000');
  await page.getByLabel('Target kualitas').fill('95.0000');
  await page.getByLabel('Realisasi kuantitas').fill('2.0000');
  await page.getByRole('button', { name: 'Simpan draf' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Perubahan tersimpan' })).toBeVisible();
  await expect(page.getByText('66,666666...', { exact: true })).toBeVisible();
  await expect(page.getByText('200/3', { exact: true })).toHaveCount(3);
  for (const label of ['Nilai rasional eksak keseluruhan', 'Nilai rasional eksak indikator', 'Nilai rasional eksak aktivitas']) {
    await page.getByText(label, { exact: true }).click();
    await expect(page.locator('details').filter({ has: page.getByText(label, { exact: true }) }).getByText('200/3', { exact: true })).toBeVisible();
  }

  await page.getByRole('button', { name: 'Ajukan ke Pejabat Penilai' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'PKP berhasil diajukan' })).toBeVisible();

  await page.getByRole('button', { name: 'PKP Browser Owner' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-assessor@example.test');
  await expect(page.getByRole('link', { name: 'Penilaian PKP' })).toBeVisible();
  await page.getByRole('link', { name: 'Penilaian PKP' }).click();
  await expect(page.getByRole('heading', { name: 'Antrean penilaian konten' })).toBeVisible();
  await page.getByRole('article').filter({ hasText: `Periode ${period.month}/${period.year}` })
    .getByRole('link', { name: 'Buka PKP untuk dinilai' }).click();
  await expect(page.getByRole('button', { name: 'Setujui PKP' })).toBeVisible();
  await page.getByLabel('Alasan pengembalian').fill('Please update the performance details.');
  await page.getByRole('button', { name: 'Kembalikan untuk perbaikan' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Keputusan PKP berhasil disimpan.' })).toBeVisible();
  await expect(page.getByText('Tidak ada PKP yang menunggu penilaian.')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Buka riwayat keputusan' })).toBeVisible();
  await page.getByRole('link', { name: 'Buka riwayat keputusan' }).click();
  await expect(page.getByRole('heading', { name: 'Riwayat PKP' })).toBeVisible();
  await expect(page.getByText('Snapshot pengajuan historis · baca saja.')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Setujui PKP' })).not.toBeVisible();
});

test('owner can print the exact snapshot after assessor approval', async ({ page }) => {
  const period = Object.fromEntries(new Intl.DateTimeFormat('en', {
    timeZone: 'Asia/Makassar',
    year: 'numeric',
    month: 'numeric',
  }).formatToParts(new Date()).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
  let year = Number(period.year);
  let month = Number(period.month) - 1;
  if (month === 0) {
    month = 12;
    year -= 1;
  }

  await signIn(page, 'pkp-owner@example.test');
  await page.getByRole('link', { name: 'PKP Bulanan' }).click();
  await page.getByRole('link', { name: 'Buat PKP Bulanan' }).click();
  await page.getByLabel('Tahun').fill(String(year));
  await page.getByLabel('Bulan').fill(String(month));
  await page.getByRole('button', { name: 'Buat draf' }).click();
  await page.getByRole('button', { name: 'Tambah indikator' }).click();
  await page.getByLabel('Judul indikator').fill('Kualitas pelayanan');
  await page.getByRole('button', { name: 'Tambah aktivitas' }).click();
  await page.getByLabel('Uraian aktivitas').fill('Menyelesaikan target kerja');
  await page.getByLabel('Satuan target dan realisasi').fill('dokumen');
  await page.getByLabel('Target kredit').fill('1.000000');
  await page.getByLabel('Target kuantitas').fill('3.0000');
  await page.getByLabel('Target kualitas').fill('100.0000');
  await page.getByLabel('Realisasi kuantitas').fill('2.0000');
  await page.getByRole('button', { name: 'Simpan draf' }).click();
  await expect(page.getByText('66,666666...', { exact: true })).toBeVisible();
  const detailUrl = page.url();

  await page.getByRole('button', { name: 'Ajukan ke Pejabat Penilai' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'PKP berhasil diajukan' })).toBeVisible();
  await page.getByRole('button', { name: 'PKP Browser Owner' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-assessor@example.test');
  await page.getByRole('link', { name: 'Penilaian PKP' }).click();
  await page.getByRole('article').filter({ hasText: `Periode ${month}/${year}` })
    .getByRole('link', { name: 'Buka PKP untuk dinilai' }).click();
  await page.getByRole('button', { name: 'Setujui PKP' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Keputusan PKP berhasil disimpan.' })).toBeVisible();
  await page.getByRole('button', { name: 'PKP Browser Assessor' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-owner@example.test');
  await page.goto(detailUrl);
  await expect(page.getByRole('link', { name: 'Cetak PKP' })).toBeVisible();

  const popupPromise = page.waitForEvent('popup');
  await page.getByRole('link', { name: 'Cetak PKP' }).click();
  const pdfPage = await popupPromise;
  await expect(pdfPage).toHaveURL(/\/revisions\/[^/]+\/pdf$/);
  const response = await page.request.get(pdfPage.url());
  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toContain('application/pdf');
  const pdfBytes = await response.body();
  expect(pdfBytes.subarray(0, 5).toString()).toBe('%PDF-');
  await pdfPage.close();

  const directory = await mkdtemp(path.join(os.tmpdir(), 'pkp-browser-upload-'));
  const printedPdfPath = path.join(directory, 'approved-pkp.pdf');
  await writeFile(printedPdfPath, pdfBytes);
  try {
    await page.goto(detailUrl);
    await page.locator('input[name="file"]').setInputFiles(printedPdfPath);
    await page.getByRole('button', { name: 'Unggah dokumen' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Dokumen berhasil diunggah dan menunggu pemeriksaan.' })).toBeVisible();
    const candidateDetailUrl = page.url();

    await page.getByRole('button', { name: 'PKP Browser Owner' }).click();
    await page.getByRole('link', { name: 'Log Out' }).click();
    await signIn(page, 'pkp-admin@example.test');
    await page.goto(candidateDetailUrl);
    await expect(page.getByRole('heading', { name: 'Alihkan pemeriksaan dokumen' })).toBeVisible();
    await page.getByLabel('Pemeriksa dokumen pengganti').selectOption({ label: 'PKP Browser Replacement (PKP-E2E-REPLACEMENT)' });
    await page.getByLabel('Alasan reassignment').fill('The assigned inspector is unavailable for document review.');
    await page.getByRole('button', { name: 'Alihkan Pemeriksa Dokumen' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Pemeriksa dokumen PKP berhasil dialihkan.' })).toBeVisible();

    await page.getByRole('button', { name: 'PKP Browser Admin' }).click();
    await page.getByRole('link', { name: 'Log Out' }).click();
    await signIn(page, 'pkp-assessor@example.test');
    await page.goto('/pkp/assessments?kind=document');
    await expect(page.getByText('Tidak ada PKP yang menunggu penilaian.')).toBeVisible();

    await page.getByRole('button', { name: 'PKP Browser Assessor' }).click();
    await page.getByRole('link', { name: 'Log Out' }).click();
    await signIn(page, 'pkp-replacement@example.test');
    await page.goto('/pkp/assessments?kind=document');
    await expect(page.getByRole('heading', { name: 'Antrean penilaian dokumen' })).toBeVisible();
    await page.getByRole('article').filter({ hasText: `Periode ${month}/${year}` })
      .getByRole('link', { name: 'Buka PKP untuk dinilai' }).click();
    await page.getByLabel('Saya mengonfirmasi dokumen ini sesuai dengan konten PKP yang disetujui.').check();
    await page.getByRole('button', { name: 'Terima dokumen final' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Dokumen final diterima.' })).toBeVisible();
    await expect(page.getByText('Tidak ada PKP yang menunggu penilaian.')).toBeVisible();

    await page.getByRole('button', { name: 'PKP Browser Replacement' }).click();
    await page.getByRole('link', { name: 'Log Out' }).click();
    await signIn(page, 'pkp-owner@example.test');
    await page.goto(candidateDetailUrl);
    const finalDownloadLink = page.getByRole('link', { name: 'Unduh dokumen final' });
    await expect(finalDownloadLink).toBeVisible();
    const downloadResponse = await page.request.get(await finalDownloadLink.getAttribute('href'));
    expect(downloadResponse.status()).toBe(200);
    expect(downloadResponse.headers()['content-type']).toContain('application/pdf');
    expect(downloadResponse.headers()['cache-control']).toContain('private');
    expect((await downloadResponse.body()).subarray(0, 5).toString()).toBe('%PDF-');

    const replacementPdfPath = path.join(directory, 'approved-pkp-replacement.pdf');
    await writeFile(replacementPdfPath, pdfBytes);
    await page.goto(candidateDetailUrl);
    await page.locator('input[name="file"]').setInputFiles(replacementPdfPath);
    await page.getByLabel('Saya mengonfirmasi konten PKP tidak berubah.').check();
    await page.getByRole('button', { name: 'Unggah dokumen' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Dokumen berhasil diunggah dan menunggu pemeriksaan.' })).toBeVisible();
    const replacementDetailUrl = page.url();

    await page.getByRole('button', { name: 'PKP Browser Owner' }).click();
    await page.getByRole('link', { name: 'Log Out' }).click();
    await signIn(page, 'pkp-replacement@example.test');
    await page.goto('/pkp/assessments?kind=document');
    await page.getByRole('article').filter({ hasText: `Periode ${month}/${year}` })
      .getByRole('link', { name: 'Buka PKP untuk dinilai' }).click();
    await page.getByLabel('Saya mengonfirmasi dokumen ini sesuai dengan konten PKP yang disetujui.').check();
    await page.getByRole('button', { name: 'Terima dokumen final' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Dokumen final diterima.' })).toBeVisible();

    await page.getByRole('button', { name: 'PKP Browser Replacement' }).click();
    await page.getByRole('link', { name: 'Log Out' }).click();
    await signIn(page, 'pkp-owner@example.test');
    await page.goto(replacementDetailUrl);
    const replacedDownloadLink = page.getByRole('link', { name: 'Unduh dokumen final' });
    await expect(replacedDownloadLink).toBeVisible();
    const replacedDownloadResponse = await page.request.get(await replacedDownloadLink.getAttribute('href'));
    expect(replacedDownloadResponse.status()).toBe(200);
    expect((await replacedDownloadResponse.body()).subarray(0, 5).toString()).toBe('%PDF-');
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('administrator can reassign a submitted assessment to an allowed replacement', async ({ page }) => {
  const period = Object.fromEntries(new Intl.DateTimeFormat('en', {
    timeZone: 'Asia/Makassar',
    year: 'numeric',
    month: 'numeric',
  }).formatToParts(new Date()).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
  let year = Number(period.year);
  let month = Number(period.month) - 3;
  while (month <= 0) {
    month += 12;
    year -= 1;
  }

  await signIn(page, 'pkp-owner@example.test');
  await page.getByRole('link', { name: 'PKP Bulanan' }).click();
  await page.getByRole('link', { name: 'Buat PKP Bulanan' }).click();
  await page.getByLabel('Tahun').fill(String(year));
  await page.getByLabel('Bulan').fill(String(month));
  await page.getByRole('button', { name: 'Buat draf' }).click();
  await page.getByRole('button', { name: 'Tambah indikator' }).click();
  await page.getByLabel('Judul indikator').fill('Kualitas pelayanan');
  await page.getByRole('button', { name: 'Tambah aktivitas' }).click();
  await page.getByLabel('Uraian aktivitas').fill('Menyelesaikan target kerja');
  await page.getByLabel('Satuan target dan realisasi').fill('dokumen');
  await page.getByLabel('Target kredit').fill('1.000000');
  await page.getByLabel('Target kuantitas').fill('3.0000');
  await page.getByLabel('Target kualitas').fill('100.0000');
  await page.getByLabel('Realisasi kuantitas').fill('2.0000');
  await page.getByRole('button', { name: 'Simpan draf' }).click();
  await page.getByRole('button', { name: 'Ajukan ke Pejabat Penilai' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'PKP berhasil diajukan' })).toBeVisible();

  await page.getByRole('button', { name: 'PKP Browser Owner' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-admin@example.test');
  await page.goto(`/pkp?scope=all&year=${year}`);
  await page.getByRole('row').filter({ hasText: `${String(month).padStart(2, '0')}/${year}` })
    .getByRole('link', { name: 'Buka PKP' }).click();
  await expect(page.getByRole('heading', { name: 'Alihkan penilaian konten' })).toBeVisible();
  await page.getByLabel('Pejabat Penilai pengganti').selectOption({ label: 'PKP Browser Replacement (PKP-E2E-REPLACEMENT)' });
  await page.getByLabel('Alasan reassignment').fill('The assigned assessor is unavailable for content review.');
  await page.getByRole('button', { name: 'Alihkan Pejabat Penilai' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Pejabat Penilai PKP berhasil dialihkan.' })).toBeVisible();

  await page.getByRole('button', { name: 'PKP Browser Admin' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-assessor@example.test');
  await page.goto('/pkp/assessments?kind=content');
  await expect(page.getByText('Tidak ada PKP yang menunggu penilaian.')).toBeVisible();

  await page.getByRole('button', { name: 'PKP Browser Assessor' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-replacement@example.test');
  await page.goto('/pkp/assessments?kind=content');
  await page.getByRole('article').filter({ hasText: `Periode ${month}/${year}` })
    .getByRole('link', { name: 'Buka PKP untuk dinilai' }).click();
  await page.getByRole('button', { name: 'Setujui PKP' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Keputusan PKP berhasil disimpan.' })).toBeVisible();
});

test('owner can edit metadata and start a correction from approved details', async ({ page }) => {
  const period = Object.fromEntries(new Intl.DateTimeFormat('en', {
    timeZone: 'Asia/Makassar', year: 'numeric', month: 'numeric',
  }).formatToParts(new Date()).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
  let year = Number(period.year);
  let month = Number(period.month) - 4;
  while (month <= 0) { month += 12; year -= 1; }

  await signIn(page, 'pkp-owner@example.test');
  await page.goto('/pkp/create');
  await page.getByLabel('Tahun').fill(String(year));
  await page.getByLabel('Bulan').fill(String(month));
  await page.getByRole('button', { name: 'Buat draf' }).click();
  await page.getByLabel('Pejabat Penilai', { exact: true }).selectOption({ label: 'PKP Browser Replacement (PKP-E2E-REPLACEMENT)' });
  await page.getByLabel('Catatan', { exact: true }).fill('Catatan pengajuan pertama');
  await page.getByRole('button', { name: 'Tambah indikator' }).click();
  await page.getByLabel('Judul indikator').fill('Kualitas pelayanan');
  await page.getByRole('button', { name: 'Tambah aktivitas' }).click();
  await page.getByLabel('Uraian aktivitas').fill('Aktivitas asli');
  await page.getByLabel('Satuan target dan realisasi').fill('dokumen');
  await page.getByLabel('Target kredit').fill('1.000000');
  await page.getByLabel('Target kuantitas').fill('3.0000');
  await page.getByLabel('Target kualitas').fill('100.0000');
  await page.getByLabel('Realisasi kuantitas').fill('2.0000');
  await page.getByRole('button', { name: 'Simpan draf' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Perubahan tersimpan' })).toBeVisible();
  await expect(page.getByLabel('Catatan', { exact: true })).toHaveValue('Catatan pengajuan pertama');
  const detailUrl = new URL(page.url()).pathname;
  const initial = await (await page.request.get(detailUrl, { headers: { Accept: 'application/json' } })).json();
  const firstRevision = initial.data.revision_uuid;
  await page.getByRole('button', { name: 'Ajukan ke Pejabat Penilai' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'PKP berhasil diajukan' })).toBeVisible();
  await page.getByRole('button', { name: 'PKP Browser Owner' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-replacement@example.test');
  await page.goto(detailUrl);
  await page.getByRole('button', { name: 'Setujui PKP' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Keputusan PKP berhasil disimpan.' })).toBeVisible();
  await page.getByRole('button', { name: 'PKP Browser Replacement' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-owner@example.test');
  await page.goto(detailUrl);
  await expect(page.getByRole('button', { name: 'Buat revisi koreksi' })).toBeVisible();
  await page.getByLabel('Cara membuat revisi').selectOption('application');
  await page.getByRole('button', { name: 'Buat revisi koreksi' }).click();
  await expect(page.getByRole('heading', { name: 'Ubah draf PKP', level: 1 })).toBeVisible();
  await expect(page.getByLabel('Catatan', { exact: true })).toHaveValue('Catatan pengajuan pertama');
  await page.getByLabel('Catatan', { exact: true }).fill('Catatan koreksi');
  await page.getByLabel('Uraian aktivitas').fill('Aktivitas koreksi');
  await page.getByLabel('Realisasi kuantitas').fill('3.0000');
  await page.getByRole('button', { name: 'Simpan draf' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Perubahan tersimpan' })).toBeVisible();
  const pending = await (await page.request.get(detailUrl, { headers: { Accept: 'application/json' } })).json();
  expect(pending.data.official_revision_uuid).toBe(firstRevision);
  expect(pending.data.pending_revision_uuid).not.toBe(firstRevision);
  expect((await page.request.get(`${detailUrl}/revisions/${firstRevision}/pdf`)).status()).toBe(200);
  await page.getByRole('button', { name: 'Ajukan ke Pejabat Penilai' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'PKP berhasil diajukan' })).toBeVisible();
  await page.getByRole('button', { name: 'PKP Browser Owner' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-assessor@example.test');
  await page.goto(detailUrl);
  await page.getByRole('button', { name: 'Setujui PKP' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Keputusan PKP berhasil disimpan.' })).toBeVisible();
  await page.getByRole('button', { name: 'PKP Browser Assessor' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-owner@example.test');
  await page.goto(detailUrl);
  const approved = await (await page.request.get(detailUrl, { headers: { Accept: 'application/json' } })).json();
  expect(approved.data.official_revision_uuid).toBe(pending.data.pending_revision_uuid);
  expect(approved.data.pending_revision_uuid).toBeNull();
  expect(approved.data.notes).toBe('Catatan koreksi');
  await expect(page.getByRole('region', { name: 'Konten revisi PKP', exact: true }).getByText('Catatan koreksi', { exact: false })).toBeVisible();
});

test('official inspector reviews R1 while a different assessor owns the private R2 draft', async ({ page }) => {
  const period = Object.fromEntries(new Intl.DateTimeFormat('en', {
    timeZone: 'Asia/Makassar', year: 'numeric', month: 'numeric',
  }).formatToParts(new Date()).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
  let year = Number(period.year);
  let month = Number(period.month) - 5;
  while (month <= 0) { month += 12; year -= 1; }
  await signIn(page, 'pkp-owner@example.test');
  await page.goto('/pkp/create');
  await page.getByLabel('Tahun').fill(String(year));
  await page.getByLabel('Bulan').fill(String(month));
  await page.getByRole('button', { name: 'Buat draf' }).click();
  await page.getByLabel('Catatan', { exact: true }).fill('Official R1 notes');
  await page.getByRole('button', { name: 'Tambah indikator' }).click();
  await page.getByLabel('Judul indikator').fill('Kinerja R1');
  await page.getByRole('button', { name: 'Tambah aktivitas' }).click();
  await page.getByLabel('Uraian aktivitas').fill('Approved R1 activity');
  await page.getByLabel('Satuan target dan realisasi').fill('dokumen');
  await page.getByLabel('Target kredit').fill('1');
  await page.getByLabel('Target kuantitas').fill('3');
  await page.getByLabel('Target kualitas').fill('100');
  await page.getByLabel('Realisasi kuantitas').fill('2');
  await page.getByRole('button', { name: 'Simpan draf' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Perubahan tersimpan' })).toBeVisible();
  const detailUrl = new URL(page.url()).pathname;
  await page.getByRole('button', { name: 'Ajukan ke Pejabat Penilai' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'PKP berhasil diajukan' })).toBeVisible();
  await page.getByRole('button', { name: 'PKP Browser Owner' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-assessor@example.test');
  await page.goto(detailUrl);
  await page.getByRole('button', { name: 'Setujui PKP' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Keputusan PKP berhasil disimpan.' })).toBeVisible();
  await page.getByRole('button', { name: 'PKP Browser Assessor' }).click();
  await page.getByRole('link', { name: 'Log Out' }).click();
  await signIn(page, 'pkp-owner@example.test');
  await page.goto(detailUrl);
  const pdfResponse = await page.request.get(await page.getByRole('link', { name: 'Cetak PKP', exact: true }).getAttribute('href'));
  expect(pdfResponse.status()).toBe(200);
  const directory = await mkdtemp(path.join(os.tmpdir(), 'pkp-dual-context-'));
  try {
    const file = path.join(directory, 'official-r1.pdf');
    await writeFile(file, await pdfResponse.body());
    await page.locator('input[name="file"]').setInputFiles(file);
    await page.getByRole('button', { name: 'Unggah dokumen' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Dokumen berhasil diunggah' })).toBeVisible();
    await page.getByRole('button', { name: 'Buat revisi koreksi' }).click();
    await expect(page.getByRole('heading', { name: 'Ubah draf PKP', level: 1 })).toBeVisible();
    await page.getByLabel('Pejabat Penilai', { exact: true }).selectOption({ label: 'PKP Browser Replacement (PKP-E2E-REPLACEMENT)' });
    await page.getByLabel('Catatan', { exact: true }).fill('PRIVATE R2 DRAFT');
    await page.getByRole('button', { name: 'Simpan draf' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Perubahan tersimpan' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Buka PKP resmi' })).toBeVisible();
    await page.getByRole('button', { name: 'PKP Browser Owner' }).click();
    await page.getByRole('link', { name: 'Log Out' }).click();
    await signIn(page, 'pkp-assessor@example.test');
    await page.goto('/pkp/assessments?kind=document');
    await page.getByRole('article').filter({ hasText: `Periode ${month}/${year}` }).getByRole('link', { name: 'Buka PKP untuk dinilai' }).click();
    await expect(page.getByRole('region', { name: 'Konten revisi PKP', exact: true }).getByText('Official R1 notes', { exact: false })).toBeVisible();
    await expect(page.getByText('PRIVATE R2 DRAFT', { exact: false })).not.toBeVisible();
    await page.getByLabel('Saya mengonfirmasi dokumen ini sesuai dengan konten PKP yang disetujui.').check();
    await page.getByRole('button', { name: 'Terima dokumen final' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Dokumen final diterima.' })).toBeVisible();
    await page.getByRole('button', { name: 'PKP Browser Assessor' }).click();
    await page.getByRole('link', { name: 'Log Out' }).click();
    await signIn(page, 'pkp-owner@example.test');
    await page.goto(detailUrl);
    const detail = await (await page.request.get(detailUrl, { headers: { Accept: 'application/json' } })).json();
    expect(detail.data.official.documents.final.original_name).toBe('official-r1.pdf');
    expect(detail.data.pending.notes).toBe('PRIVATE R2 DRAFT');
    await expect(page.getByRole('link', { name: 'Unduh final PKP resmi' })).toBeVisible();
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('owner can submit an externally signed PKP and print its original PDF after approval', async ({ page }) => {
  const period = Object.fromEntries(new Intl.DateTimeFormat('en', {
    timeZone: 'Asia/Makassar',
    year: 'numeric',
    month: 'numeric',
  }).formatToParts(new Date()).filter((part) => part.type !== 'literal').map((part) => [part.type, part.value]));
  let year = Number(period.year);
  let month = Number(period.month) - 2;
  while (month <= 0) {
    month += 12;
    year -= 1;
  }

  await signIn(page, 'pkp-owner@example.test');
  await page.getByRole('link', { name: 'PKP Bulanan' }).click();
  await page.getByRole('link', { name: 'Buat PKP Bulanan' }).click();
  await page.getByLabel('Tahun').fill(String(year));
  await page.getByLabel('Bulan').fill(String(month));
  await page.getByLabel('Cara membuat PKP').selectOption('external');
  const createResponsePromise = page.waitForResponse((response) => response.request().method() === 'POST' && new URL(response.url()).pathname === '/pkp');
  await page.getByRole('button', { name: 'Buat draf' }).click();
  await createResponsePromise;
  await expect(page.getByText('PKP eksternal menggunakan dokumen PDF')).toBeVisible();
  await page.getByLabel('Catatan', { exact: true }).fill('Catatan sumber eksternal');
  await page.getByRole('button', { name: 'Simpan draf' }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Perubahan tersimpan' })).toBeVisible();
  await expect(page.getByLabel('Catatan', { exact: true })).toHaveValue('Catatan sumber eksternal');

  const sourcePage = await page.context().newPage();
  await sourcePage.setContent('<html><body><h1>External signed PKP source</h1><p>Original approved bytes.</p></body></html>');
  const sourceBytes = await sourcePage.pdf({ format: 'A4' });
  await sourcePage.close();
  const directory = await mkdtemp(path.join(os.tmpdir(), 'pkp-external-source-'));
  const sourcePath = path.join(directory, 'external-signed.pdf');

  try {
    await writeFile(sourcePath, sourceBytes);
    await page.locator('input[name="file"]').setInputFiles(sourcePath);
    await page.getByLabel('Dokumen sumber ini telah ditandatangani').check();
    await page.getByRole('button', { name: 'Unggah PDF sumber' }).click();
    await expect(page.getByText('Dokumen sumber: external-signed.pdf')).toBeVisible();
    await page.getByRole('button', { name: 'Ajukan ke Pejabat Penilai' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'PKP berhasil diajukan' })).toBeVisible();
    const detailUrl = page.url();

    await page.getByRole('button', { name: 'PKP Browser Owner' }).click();
    await page.getByRole('link', { name: 'Log Out' }).click();
    await signIn(page, 'pkp-assessor@example.test');
    await expect(page.getByRole('link', { name: 'Penilaian PKP' })).toBeVisible();
    await page.getByRole('link', { name: 'Penilaian PKP' }).click();
    await page.getByRole('article').filter({ hasText: `Periode ${month}/${year}` })
      .getByRole('link', { name: 'Buka PKP untuk dinilai' }).click();
    await page.getByRole('button', { name: 'Setujui PKP' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'Keputusan PKP berhasil disimpan.' })).toBeVisible();

    await page.getByRole('button', { name: 'PKP Browser Assessor' }).click();
    await page.getByRole('link', { name: 'Log Out' }).click();
    await signIn(page, 'pkp-owner@example.test');
    await page.goto(detailUrl);
    await expect(page.getByText('Nilai kinerja belum tersedia')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Cetak PKP' })).toBeVisible();

    const popupPromise = page.waitForEvent('popup');
    await page.getByRole('link', { name: 'Cetak PKP' }).click();
    const pdfPage = await popupPromise;
    await expect(pdfPage).toHaveURL(/\/revisions\/[^/]+\/pdf$/);
    const response = await page.request.get(pdfPage.url());
    expect(response.status()).toBe(200);
    expect((await response.body()).equals(sourceBytes)).toBe(true);
    await pdfPage.close();
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});


test('owner reloads and saves all 500 distinct activities without losing order or values', async ({ page }) => {
  test.setTimeout(120000);
  await signIn(page, 'pkp-owner@example.test');
  const csrf = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrf };
  const created = await page.request.post('/pkp', { headers, data: { year: 2035, month: 1, source: 'application' } });
  expect(created.status()).toBe(201);
  const { data } = await created.json();
  const indicators = Array.from({ length: 20 }, (_, indicator) => ({
    title: `Indicator ${indicator + 1}`,
    activities: Array.from({ length: 25 }, (_, activity) => ({
      description: `Distinct activity ${indicator * 25 + activity + 1}`,
      unit: `unit-${indicator * 25 + activity + 1}`,
      target_credit: '0.000001', target_quantity: '3', target_quality: '100', realized_quantity: '2',
    })),
  }));
  const saved = await page.request.put(`/pkp/${data.pkp_uuid}/revisions/${data.revision_uuid}`, {
    headers, data: { expected_version: 1, indicators },
  });
  expect(saved.status()).toBe(200);
  await page.goto(`/pkp/${data.pkp_uuid}`);
  await expect(page.getByLabel('Uraian aktivitas', { exact: true })).toHaveCount(500);
  const readTree = async () => page.locator('[id^="activity-description-"]').evaluateAll(elements => elements.map(element => ({
    description: element.value,
    unit: document.getElementById(element.id.replace('activity-description-', 'activity-unit-')).value,
  })));
  const expected = indicators.flatMap(indicator => indicator.activities.map(({ description, unit }) => ({ description, unit })));
  expect(await readTree()).toEqual(expected);
  await expect(page.getByRole('button', { name: 'Tambah aktivitas', exact: true }).first()).toBeDisabled();
  await page.getByRole('button', { name: 'Simpan draf', exact: true }).click();
  await expect(page.getByRole('status').filter({ hasText: 'Perubahan tersimpan' })).toBeVisible();
  expect(await readTree()).toEqual(expected);
  const persisted = await page.request.get(`/pkp/${data.pkp_uuid}`, { headers: { Accept: 'application/json' } });
  expect(persisted.status()).toBe(200);
  const body = await persisted.json();
  expect(body.data.indicators.map(({ title, activities }) => ({ title, activities: activities.map(({ description, unit, target_credit, target_quantity, target_quality, realized_quantity }) => ({ description, unit, target_credit, target_quantity, target_quality, realized_quantity })) }))).toEqual(indicators);
});

test('identity refresh resubmits a frozen late record through real owner and admin sessions', async ({ page, browser }) => {
  await signIn(page, 'pkp-owner@example.test');
  const adminContext = await browser.newContext({ baseURL: new URL(page.url()).origin });
  const admin = await adminContext.newPage();
  try {
    await signIn(admin, 'pkp-admin@example.test');
    const adminHeaders = { Accept: 'application/json', 'X-CSRF-TOKEN': await admin.locator('meta[name="csrf-token"]').getAttribute('content') };
    const configured = await admin.request.put('/pkp/deadlines/2020/2', { headers: adminHeaders, data: { expected_version: 0, deadline_local: '2020-02-10T17:00:00' } });
    expect(configured.status()).toBe(200);
    const ownerHeaders = { Accept: 'application/json', 'X-CSRF-TOKEN': await page.locator('meta[name="csrf-token"]').getAttribute('content') };
    const created = await page.request.post('/pkp', { headers: ownerHeaders, data: { year: 2020, month: 2, source: 'application' } });
    expect(created.status()).toBe(201);
    const { data } = await created.json();
    const saved = await page.request.put(`/pkp/${data.pkp_uuid}/revisions/${data.revision_uuid}`, { headers: ownerHeaders, data: {
      expected_version: 1, notes: 'Frozen identity attempt', indicators: [{ title: 'Refresh evidence', activities: [{ description: 'Identity refresh activity', unit: 'dokumen', target_credit: '1', target_quantity: '3', target_quality: '100', realized_quantity: '2' }] }],
    } });
    expect(saved.status()).toBe(200);
    await page.goto(`/pkp/${data.pkp_uuid}`);
    await page.getByRole('button', { name: 'Ajukan ke Pejabat Penilai' }).click();
    await expect(page.getByRole('button', { name: 'Refresh identitas dan kembalikan draf' })).toBeVisible();
    const before = (await (await page.request.get(`/pkp/${data.pkp_uuid}`, { headers: { Accept: 'application/json' } })).json()).data;
    expect(before.lateness).toBe('late');
    await admin.goto(`/riwayat_jabatan/create/${before.identity_snapshot.employee.pegawai_uuid}`);
    await admin.locator('[name="jabatan_uuid"]').selectOption({ label: 'PKP E2E Hakim' });
    await admin.locator('[name="satuan_kerja"]').fill('PN Updated Browser Unit');
    await admin.locator('[name="tanggal_mulai"]').fill('2020-01-02');
    await admin.getByRole('button', { name: 'Simpan', exact: true }).click();
    const futureDeadline = await admin.request.put('/pkp/deadlines/2020/2', { headers: adminHeaders, data: { expected_version: 1, deadline_local: '2090-02-10T17:00:00' } });
    expect(futureDeadline.status()).toBe(200);
    await page.getByLabel('Alasan refresh identitas').fill('Applicable work unit changed after original submission.');
    await page.getByRole('button', { name: 'Refresh identitas dan kembalikan draf' }).click();
    await expect(page.getByRole('button', { name: 'Ajukan ke Pejabat Penilai' })).toBeVisible();
    await page.getByRole('button', { name: 'Ajukan ke Pejabat Penilai' }).click();
    const after = (await (await page.request.get(`/pkp/${data.pkp_uuid}`, { headers: { Accept: 'application/json' } })).json()).data;
    expect(after.identity_snapshot.work_unit.value).toBe('PN Updated Browser Unit');
    expect(after.first_submitted_at).toBe(before.first_submitted_at);
    expect(after.deadline_snapshot_at).toBe(before.deadline_snapshot_at);
    expect(after.lateness).toBe('late');
    expect(after.history.attempts).toHaveLength(2);
    expect(after.history.attempts.find(attempt => attempt.uuid === before.submission_uuid).identity_snapshot).toEqual(before.identity_snapshot);
    await expect(page.getByText('Terlambat', { exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: /Ditutup karena perubahan identitas/ })).toBeVisible();
  } finally {
    await adminContext.close();
  }
});

test('normal motion PKP form remains readable on desktop and mobile', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email atau NIP').fill('pkp-owner@example.test');
  await page.getByLabel('Kata Sandi').fill('pkp-testing-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await expect(page).not.toHaveURL(/\/login$/);
  await page.goto('/pkp/create');
  for (const [name, width, height] of [['desktop', 1280, 800], ['mobile', 375, 812]]) {
    await page.setViewportSize({ width, height });
    await expect(page.getByRole('heading', { name: 'Buat PKP Bulanan', level: 1 })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Buat draf', exact: true })).toBeVisible();
    await page.screenshot({ path: `test-results/pkp-normal-motion-${name}.png`, fullPage: true });
  }
});
