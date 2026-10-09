import { inDays, lecturers, signInAs, student } from './support/actors';
import { expect, test } from './support/app';

/**
 * Bagian 4 — Bimbingan proposal.
 *
 * Program studi mensyaratkan dua sesi bimbingan proposal yang sudah selesai
 * sebelum ujian boleh diajukan, dan bimbingan tahap hasil penelitian tetap
 * terkunci sampai ujian proposal dilewati.
 */
const sessions = [
    {
        title: 'Bimbingan Bab 1 — Pendahuluan',
        notes: 'Latar belakang dipertajam dengan data keluhan dua semester terakhir.',
    },
    {
        title: 'Bimbingan Bab 3 — Metodologi',
        notes: 'Skema praproses teks dan pembagian data latih/uji disepakati.',
    },
];

test.describe.serial('Bagian 4 · Bimbingan proposal', () => {
    test('ujian belum boleh diajukan sebelum kuota bimbingan terpenuhi', async ({
        app,
    }) => {
        await signInAs(app, student.email);

        await app.goto('/proposal-exam-requests/create');

        await expect(
            app.getByText('0 dari 2 bimbingan selesai.').first(),
        ).toBeVisible();
        await expect(
            app.getByRole('button', { name: 'Kirim Pengajuan' }),
        ).toBeDisabled();
    });

    test('tahap bimbingan hasil penelitian masih terkunci', async ({ app }) => {
        await app.goto('/guidance-sessions/create');

        await expect(
            app.getByRole('button', { name: 'Hasil Penelitian' }),
        ).toHaveAttribute('aria-disabled', 'true');
    });

    for (const [index, session] of sessions.entries()) {
        test(`mahasiswa membuat sesi bimbingan ${index + 1}`, async ({
            app,
        }) => {
            await signInAs(app, student.email);

            await app.goto('/guidance-sessions/create');
            await app.getByLabel('Judul Sesi').fill(session.title);

            // "Tatap Muka" names an option in two groups, so the group it
            // belongs to is named as well.
            await app
                .getByRole('group', { name: 'Tipe bimbingan' })
                .getByRole('button', { name: 'Tatap Muka' })
                .click();
            await app.getByLabel('Lokasi Pertemuan').fill('Ruang Dosen 301');
            await app
                .getByLabel('Jadwal Pertemuan')
                .fill(inDays(index + 1, '10:00'));
            await app
                .getByLabel('Catatan Awal')
                .fill('Mohon arahan untuk bagian ini.');

            await app
                .getByRole('button', { name: 'Buat Sesi Bimbingan' })
                .click();

            await app.waitForURL(/\/guidance-sessions/);
            await expect(app.getByText(session.title).first()).toBeVisible();
        });

        test(`pembimbing menyelesaikan sesi bimbingan ${index + 1}`, async ({
            app,
        }) => {
            await signInAs(app, lecturers.pembimbingUtama.email);

            await app.goto('/guidance-sessions');
            await app
                .getByRole('row', { name: new RegExp(session.title) })
                .click();
            await app.waitForURL(/\/guidance-sessions\/\d+/);

            await app.getByRole('button', { name: 'Tandai Selesai' }).click();

            const dialog = app.getByRole('dialog');
            await dialog.locator('textarea').first().fill(session.notes);
            await dialog
                .getByRole('button', { name: 'Tandai Selesai' })
                .click();

            await expect(app.getByText(/selesai/i).first()).toBeVisible();
        });
    }

    test('kuota bimbingan terpenuhi membuka pengajuan ujian', async ({
        app,
    }) => {
        await signInAs(app, student.email);

        await app.goto('/proposal-exam-requests/create');

        await expect(app.getByText(/2 dari 2/).first()).toBeVisible();
        await expect(
            app.getByRole('button', { name: 'Kirim Pengajuan' }),
        ).toBeEnabled();
    });
});
