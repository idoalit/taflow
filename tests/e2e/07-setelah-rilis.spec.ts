import { lecturers, signInAs, student } from './support/actors';
import { expect, test } from './support/app';

/**
 * Bagian 7 — Setelah hasil dirilis.
 *
 * Nilai dan daftar revisi baru terlihat oleh mahasiswa sekarang, dokumen resmi
 * sudah bisa diunduh, dan penilaian terkunci bagi penguji.
 */
test.describe.serial('Bagian 7 · Setelah hasil dirilis', () => {
    test('mahasiswa melihat nilai akhir dan berita acara', async ({ app }) => {
        await signInAs(app, student.email);

        await app.goto('/exams');
        await app.getByRole('row', { name: new RegExp(student.name) }).click();
        await app.waitForURL(/\/exams\/proposal\/\d+/);

        await expect(app.getByText('Hasil Dirilis').first()).toBeVisible();
        await expect(app.getByText('NILAI AKHIR').first()).toBeVisible();
        await expect(app.getByText('Lulus Bersyarat').first()).toBeVisible();
    });

    test('mahasiswa menandai satu poin revisi selesai', async ({ app }) => {
        const first = app
            .getByRole('row')
            .filter({ hasText: 'Perbaiki rumusan masalah' });

        await first.getByRole('checkbox').click();

        // Exact: "Selesai" is a substring of the "Belum Selesai" the row shows
        // before it is ticked, so a loose match would pass either way.
        await expect(first.getByText('Selesai', { exact: true })).toBeVisible();

        await expect(
            app.getByText('1 dari 2 poin revisi telah diselesaikan.'),
        ).toBeVisible();
    });

    test('dokumen resmi sudah lengkap dan dapat diunduh', async ({ app }) => {
        const documents = [
            'Daftar Hadir Ujian',
            'Form Penilaian Ujian',
            'Lembar Revisi',
            'Form Penilaian Proses Bimbingan',
            'Daftar Nilai Ujian',
            'Berita Acara Ujian',
        ];

        for (const name of documents) {
            await expect(
                app.getByRole('row', { name: new RegExp(name) }),
            ).toContainText('Siap');
        }

        const download = app.waitForEvent('download');
        await app
            .getByRole('link', { name: 'Unduh Berita Acara Ujian' })
            .click();

        expect((await download).suggestedFilename()).toContain('.pdf');
    });

    test('penilaian terkunci untuk penguji setelah rilis', async ({ app }) => {
        await signInAs(app, lecturers.ketuaPenguji.email);

        await app.goto('/exams');
        await app.getByRole('row', { name: new RegExp(student.name) }).click();
        await app.waitForURL(/\/exams\/proposal\/\d+/);

        await expect(
            app.getByRole('link', { name: 'Isi Evaluasi', exact: true }),
        ).toHaveCount(0);
        await expect(
            app.getByRole('button', { name: 'Tetapkan Berita Acara' }),
        ).toHaveCount(0);
    });
});
