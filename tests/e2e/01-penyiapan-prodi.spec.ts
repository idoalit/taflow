import { expect, test } from './support/app';
import {
    admin,
    lecturers,
    signInAs,
    student,
    writeCsv,
} from './support/actors';

/**
 * Bagian 1 — Admin Prodi menyiapkan program studi.
 *
 * Tidak ada peran lain yang bisa berbuat apa pun sebelum ini: belum ada akun,
 * belum ada aturan, dan belum ada rubrik untuk menilai.
 */
/**
 * Urutannya disusun agar bagian yang menopang cerita berikutnya — akun dan
 * pembimbing — selesai lebih dulu. Rubrik baru dibutuhkan saat penjadwalan
 * ujian, jadi ia diletakkan terakhir dan kegagalan di sana tidak menjatuhkan
 * bagian proposal maupun bimbingan.
 */
test.describe.serial('Bagian 1 · Admin Prodi menyiapkan program studi', () => {
    test.beforeEach(async ({ app }) => {
        await signInAs(app, admin.email, admin.password);
    });

    test('prodi masih kosong sebelum apa pun disiapkan', async ({ app }) => {
        await app.goto('/admin/students');
        await expect(
            app.getByText(/tidak ada mahasiswa yang cocok/i),
        ).toBeVisible();

        await app.goto('/admin/lecturers');
        await expect(
            app.getByText(/tidak ada dosen yang cocok/i),
        ).toBeVisible();

        await app.goto('/admin/rubrics');
        await expect(app.getByText(/belum ada rubrik/i)).toBeVisible();
    });

    test('admin menetapkan aturan tugas akhir program studi', async ({
        app,
    }) => {
        await app.goto('/admin/thesis-settings');

        await app.getByLabel('Jumlah Minimum Bimbingan Proposal').fill('2');
        await app.getByLabel('Jumlah Minimum Bimbingan Hasil').fill('1');
        await app.getByLabel('Jumlah Pembimbing').fill('2');
        await app.getByLabel('Progres Logbook Minimum (%)').fill('100');

        await app.getByRole('button', { name: 'Simpan Pengaturan' }).click();

        await expect(app.getByText(/tersimpan/i).first()).toBeVisible();
    });

    test('admin mengimpor akun dosen', async ({ app }) => {
        const file = writeCsv('dosen.csv', [
            ['nama', 'email', 'nip', 'jabatan', 'kuota_bimbingan'],
            ...Object.values(lecturers).map((lecturer) => [
                lecturer.name,
                lecturer.email,
                lecturer.nip,
                'Lektor',
                '8',
            ]),
        ]);

        await app.goto('/admin/import');
        await app.getByRole('tab', { name: 'Dosen' }).click();
        await app.locator('input[type="file"]').setInputFiles(file);
        await app.getByRole('button', { name: 'Proses Impor' }).click();

        await expect(app.getByText('Hasil Impor')).toBeVisible();

        await app.goto('/admin/lecturers');

        for (const lecturer of Object.values(lecturers)) {
            await expect(app.getByText(lecturer.name)).toBeVisible();
        }
    });

    test('admin mengimpor akun mahasiswa', async ({ app }) => {
        const file = writeCsv('mahasiswa.csv', [
            ['nama', 'email', 'nim', 'angkatan'],
            [student.name, student.email, student.nim, student.angkatan],
        ]);

        await app.goto('/admin/import');
        await app.getByRole('tab', { name: 'Mahasiswa' }).click();
        await app.locator('input[type="file"]').setInputFiles(file);
        await app.getByRole('button', { name: 'Proses Impor' }).click();

        await expect(app.getByText('Hasil Impor')).toBeVisible();

        await app.goto('/admin/students');
        await expect(app.getByText(student.name)).toBeVisible();
        await expect(app.getByText(student.nim)).toBeVisible();
    });

    test('admin menetapkan dua pembimbing untuk mahasiswa', async ({ app }) => {
        await app.goto('/admin/students');
        // The row is clickable rather than a link, so it is reached as a row.
        await app.getByRole('row', { name: new RegExp(student.nim) }).click();
        await app.waitForURL(/\/admin\/students\/\d+/);

        // The list starts empty — a mahasiswa has no pembimbing until one is
        // put there, which is the whole point of this screen.
        await expect(app.locator('button[role="combobox"]')).toHaveCount(0);

        const chosen = [
            lecturers.pembimbingUtama.name,
            lecturers.pembimbingPendamping.name,
        ];

        for (const name of chosen) {
            await app.getByRole('button', { name: 'Tambah Dosen' }).click();

            // The Peran control is a native select and answers to the combobox
            // role too; only the dosen picker is a button, and the unchosen one
            // is always the row just added.
            await app
                .locator('button[role="combobox"]', { hasText: 'Pilih dosen' })
                .first()
                .click();
            await app.getByRole('option', { name }).click();
        }

        await app.getByRole('button', { name: 'Simpan', exact: true }).click();

        await expect(
            app.getByText(lecturers.pembimbingUtama.name).first(),
        ).toBeVisible();
        await expect(
            app.getByText(lecturers.pembimbingPendamping.name).first(),
        ).toBeVisible();
    });

    test('admin membuat dan menerbitkan rubrik ujian', async ({ app }) => {
        await app.goto('/admin/rubrics/create');

        await app.getByLabel('Nama Rubrik').fill('Rubrik Ujian Proposal E2E');
        await app
            .getByLabel('Deskripsi')
            .fill('Rubrik berbobot untuk ujian proposal.');

        const criteria = [
            ['Kualitas Proposal', '40', '100'],
            ['Penguasaan Materi', '35', '100'],
            ['Presentasi', '25', '10'],
        ];

        const rows = app.locator('input[placeholder="Penguasaan Materi"]');

        // The form opens with rows of its own; add only what is still short of
        // the three this rubric needs, or the spare blank row fails validation.
        while ((await rows.count()) < criteria.length) {
            await app.getByRole('button', { name: 'Tambah Kriteria' }).click();
        }

        expect(await rows.count()).toBe(criteria.length);

        for (const [index, [label, weight, max]] of criteria.entries()) {
            await rows.nth(index).fill(label);

            const numbers = app.locator('input[type="number"]');
            await numbers.nth(index * 2).fill(weight);
            await numbers.nth(index * 2 + 1).fill(max);
        }

        await expect(app.getByText('Total bobot: 100%')).toBeVisible();

        await app.getByRole('button', { name: 'Simpan', exact: true }).click();
        await app.waitForURL(/\/admin\/rubrics\/\d+$/);

        await app.getByRole('button', { name: 'Terbitkan Rubrik' }).click();
        await expect(app.getByText('Terbit').first()).toBeVisible();
    });

    test('admin menerapkan rubrik pada tahap ujian proposal', async ({
        app,
    }) => {
        await app.goto('/admin/rubrics');
        await app
            .getByRole('tab', { name: 'Penerapan per Tahap Ujian' })
            .click();

        const proposal = app
            .locator('.ind-card')
            .filter({ hasText: 'Ujian Proposal' });

        await proposal.locator('button[role="combobox"]').click();
        await app
            .getByRole('option', { name: 'Rubrik Ujian Proposal E2E' })
            .click();
        await proposal.getByLabel('Ambang Kelulusan (%)').fill('65');
        await proposal.getByRole('button', { name: 'Terapkan Rubrik' }).click();

        // The name now appears twice — as the card's title and as the value
        // still chosen in the picker — so the title is named exactly.
        await expect(proposal.locator('.ind-card-title')).toHaveText(
            'Rubrik Ujian Proposal E2E',
        );
    });
});
