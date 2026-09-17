import { lecturers, signInAs, student, writePdf } from './support/actors';
import { expect, test } from './support/app';

/**
 * Bagian 3 — Proposal.
 *
 * Dosen tidak bisa meninjau apa pun sebelum mahasiswanya mengajukan, dan
 * Persamaan Persepsi baru terbuka setelah proposal disetujui. Urutan itu yang
 * diuji, bukan hanya hasil akhirnya.
 */
test.describe.serial('Bagian 3 · Proposal diajukan dan disetujui', () => {
    test('pembimbing belum punya apa pun untuk ditinjau', async ({ app }) => {
        await signInAs(app, lecturers.pembimbingUtama.email);

        await app.goto('/proposals');
        await expect(
            app.getByText('Belum ada proposal yang diajukan.'),
        ).toBeVisible();

        await app.goto('/perception-alignments');
        await expect(app.getByText(/belum ada/i).first()).toBeVisible();
    });

    test('mahasiswa mengajukan proposal', async ({ app }) => {
        await signInAs(app, student.email);

        await app.goto('/proposals/create');
        await app.getByLabel('Judul Proposal').fill(student.judul);

        const sections = [
            'Keluhan layanan akademik masuk melalui banyak kanal dan belum terklasifikasi, sehingga penanganannya lambat dan tidak terukur.',
            'Seberapa akurat Naive Bayes mengklasifikasikan keluhan layanan akademik berbahasa Indonesia ke dalam kategori penanganannya?',
            'Membangun dan mengukur model klasifikasi keluhan sebagai dasar penyaluran tiket ke unit yang tepat.',
            'Keluhan tertulis satu tahun akademik pada kanal resmi program studi.',
            'Eksperimen kuantitatif dengan praproses teks, pembobotan TF-IDF, dan evaluasi akurasi serta F1-score.',
            'Selaras dengan roadmap riset program studi pada tema layanan akademik cerdas.',
        ];

        const areas = app.locator('textarea');

        for (const [index, body] of sections.entries()) {
            await areas.nth(index).fill(body);
        }

        await app
            .locator('input[type="file"]')
            .setInputFiles(
                writePdf('proposal-intan.pdf', `PROPOSAL — ${student.judul}`),
            );

        await app.getByRole('button', { name: 'Ajukan Proposal' }).click();

        await app.waitForURL(/\/proposals/);
        await expect(app.getByText(student.judul).first()).toBeVisible();
    });

    test('pembimbing utama menyetujui proposal', async ({ app }) => {
        await signInAs(app, lecturers.pembimbingUtama.email);

        await app.goto('/proposals');

        // The dosen's list is by judul; the mahasiswa's name is on the detail.
        await app.getByRole('row', { name: new RegExp(student.judul) }).click();
        await app.waitForURL(/\/proposals\/\d+/);

        // The detail names the mahasiswa only by NIM, so that is what is
        // checked here.
        await expect(app.getByText(student.nim).first()).toBeVisible();

        await app.getByRole('button', { name: 'Setujui Proposal' }).click();

        await expect(
            app.getByText(/persamaan persepsi dimulai/i),
        ).toBeVisible();
    });

    test('persamaan persepsi baru terbuka setelah proposal disetujui', async ({
        app,
    }) => {
        await app.goto('/perception-alignments');
        await expect(app.getByText(student.name).first()).toBeVisible();
    });

    test('pembimbing pendamping menunggu giliran pembimbing utama', async ({
        app,
    }) => {
        await signInAs(app, lecturers.pembimbingPendamping.email);

        await app.goto('/perception-alignments');
        await app.getByRole('row', { name: new RegExp(student.name) }).click();
        await app.waitForURL(/\/perception-alignments\/\d+/);

        await expect(app.getByText(/menunggu pembimbing utama/i)).toBeVisible();
        await expect(
            app.getByRole('button', { name: 'Simpan Umpan Balik' }),
        ).toHaveCount(0);
    });

    test('kedua pembimbing mengisi persamaan persepsi menurut urutannya', async ({
        app,
    }) => {
        const turns: [string, string][] = [
            [
                lecturers.pembimbingUtama.email,
                'Topik layak dilanjutkan. Fokuskan Bab II pada penelitian klasifikasi teks berbahasa Indonesia.',
            ],
            [
                lecturers.pembimbingPendamping.email,
                'Sependapat. Tambahkan pembanding sederhana agar hasil Naive Bayes punya acuan.',
            ],
        ];

        for (const [email, body] of turns) {
            await signInAs(app, email);

            await app.goto('/perception-alignments');
            await app
                .getByRole('row', { name: new RegExp(student.name) })
                .click();
            await app.waitForURL(/\/perception-alignments\/\d+/);

            await app.locator('textarea').first().fill(body);
            await app
                .getByRole('button', { name: 'Simpan Umpan Balik' })
                .click();

            await expect(
                app.getByText(/tersimpan|disetujui/i).first(),
            ).toBeVisible();
        }
    });
});
