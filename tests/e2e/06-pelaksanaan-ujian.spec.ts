import type { Page } from '@playwright/test';
import { admin, lecturers, signInAs, student } from './support/actors';
import { expect, test } from './support/app';

/**
 * Bagian 6 — Pelaksanaan ujian proposal.
 *
 * Sidang berjalan dari presensi sampai berita acara. Tidak ada satu langkah pun
 * yang bisa mendahului: berita acara menolak ditetapkan sebelum penilaian
 * lengkap, dan hasil menolak dirilis sebelum berita acara ada.
 */
async function openExam(page: Page): Promise<void> {
    await page.goto('/exams');
    await page.getByRole('row', { name: new RegExp(student.name) }).click();
    await page.waitForURL(/\/exams\/proposal\/\d+/);
}

/** One stroke across the pad, the way a hand would make it. */
async function drawOnPad(page: Page): Promise<void> {
    const canvas = page.getByRole('dialog').locator('canvas');
    const box = await canvas.boundingBox();

    if (box === null) {
        throw new Error('Kanvas tanda tangan tidak ditemukan.');
    }

    const y = box.y + box.height / 2;
    await page.mouse.move(box.x + 30, y);
    await page.mouse.down();

    for (let step = 1; step <= 20; step++) {
        await page.mouse.move(
            box.x + 30 + (step * (box.width - 60)) / 20,
            y + (step % 2 === 0 ? 28 : -28),
            { steps: 3 },
        );
    }

    await page.mouse.up();
}

/**
 * Sign the presensi.
 *
 * Drawn twice if need be: while a headed run is being watched, a stray click
 * anywhere on the page dismisses the modal mid-stroke, and starting over costs
 * far less than failing the whole story over it.
 */
async function signHere(page: Page): Promise<void> {
    const dialog = page.getByRole('dialog');
    const save = page.getByRole('button', { name: 'Simpan Tanda Tangan' });

    for (let attempt = 1; attempt <= 3; attempt++) {
        if ((await dialog.count()) === 0) {
            await page.getByRole('button', { name: /Tanda Tangani/ }).click();
            await expect(dialog).toBeVisible();
        }

        await drawOnPad(page);

        if ((await save.count()) > 0 && (await save.isEnabled())) {
            await save.click();
            await expect(dialog).toHaveCount(0);

            return;
        }
    }

    throw new Error(
        'Tanda tangan tidak terekam setelah tiga percobaan — kanvas atau modalnya bermasalah.',
    );
}

/** Everyone who sits in the room, in the order they would sign the sheet. */
const attendees = [
    lecturers.ketuaPenguji.email,
    lecturers.anggotaPenguji.email,
    lecturers.pembimbingUtama.email,
    lecturers.pembimbingPendamping.email,
    student.email,
];

test.describe.serial('Bagian 6 · Pelaksanaan ujian', () => {
    test('berita acara menolak ditetapkan sebelum penilaian masuk', async ({
        app,
    }) => {
        await signInAs(app, lecturers.ketuaPenguji.email);
        await openExam(app);

        await expect(app.getByText(/belum dapat ditetapkan/i)).toBeVisible();
        await expect(
            app.getByRole('button', { name: 'Tetapkan Berita Acara' }),
        ).toBeDisabled();
    });

    test('seluruh yang hadir menandai presensi dan menandatanganinya', async ({
        app,
    }) => {
        // The mahasiswa signs the daftar hadir too: a verdict on someone not
        // recorded as present is refused, which is the point of the sheet.
        for (const email of attendees) {
            await signInAs(app, email);
            await openExam(app);

            const mine = app.getByRole('row', { name: /\(Anda\)/ });

            // The box is only ticked once the server has recorded it, so this
            // is a click followed by waiting for the row to say so — check()
            // would fail on the state not flipping within the same tick.
            await mine.getByRole('checkbox').click();

            // Exact: "Hadir" is a substring of the "Belum hadir" these rows
            // show beforehand, and a loose match would pass either way.
            await expect(
                mine.getByText('Hadir', { exact: true }),
            ).toBeVisible();

            await signHere(app);
            await expect(
                mine.getByText('Sudah', { exact: true }),
            ).toBeVisible();
        }
    });

    test('kedua penguji mengisi penilaian ujian', async ({ app }) => {
        const verdicts: [string, number][] = [
            [lecturers.ketuaPenguji.email, 0.9],
            [lecturers.anggotaPenguji.email, 0.8],
        ];

        for (const [email, fraction] of verdicts) {
            await signInAs(app, email);
            await openExam(app);

            await app
                .getByRole('link', { name: 'Isi Evaluasi', exact: true })
                .click();
            await app.waitForURL(/\/evaluate/);

            const marks = app.locator('input[type="number"]');
            const count = await marks.count();

            for (let index = 0; index < count; index++) {
                const max = Number(await marks.nth(index).getAttribute('max'));
                await marks.nth(index).fill(String(Math.round(max * fraction)));
            }

            await app.getByRole('button', { name: 'Simpan Evaluasi' }).click();
            await app.waitForURL(/\/exams\/proposal\/\d+$/);
        }

        await expect(
            app.getByText('2 dari 2 anggota panel telah menilai'),
        ).toBeVisible();
    });

    test('kedua pembimbing mengisi penilaian proses bimbingan', async ({
        app,
    }) => {
        const sheets: [string, number][] = [
            [lecturers.pembimbingUtama.email, 18],
            [lecturers.pembimbingPendamping.email, 17],
        ];

        for (const [email, mark] of sheets) {
            await signInAs(app, email);
            await openExam(app);

            await app
                // Once a document becomes downloadable its "Unduh Form
                // Penilaian Proses Bimbingan" link matches loosely too.
                .getByRole('link', {
                    name: 'Penilaian Proses Bimbingan',
                    exact: true,
                })
                .click();
            await app.waitForURL(/\/supervision/);

            const marks = app.locator('input[type="number"]');
            const count = await marks.count();

            for (let index = 0; index < count; index++) {
                await marks.nth(index).fill(String(mark));
            }

            await app.getByRole('button', { name: 'Simpan Penilaian' }).click();
            await app.waitForURL(/\/exams\/proposal\/\d+$/);
        }
    });

    test('ketua penguji menulis lembar revisi', async ({ app }) => {
        await signInAs(app, lecturers.ketuaPenguji.email);
        await openExam(app);

        const points = [
            'Perbaiki rumusan masalah pada Bab I agar selaras dengan tujuan.',
            'Lengkapi Bab III dengan skema evaluasi yang lebih rinci.',
        ];

        for (const [index, body] of points.entries()) {
            if (index > 0) {
                await app.getByRole('button', { name: 'Tambah Poin' }).click();
            }

            await app
                .locator('input[placeholder^="Perbaiki rumusan"]')
                .nth(index)
                .fill(body);
        }

        await app.getByRole('button', { name: 'Simpan Lembar Revisi' }).click();

        await expect(app.getByText(/lembar revisi tersimpan/i)).toBeVisible();
    });

    test('ketua penguji menetapkan berita acara', async ({ app }) => {
        await expect(
            app.getByRole('button', { name: 'Tetapkan Berita Acara' }),
        ).toBeEnabled();

        await app
            .getByLabel('Keputusan Dewan Penguji')
            .selectOption({ label: 'Lulus Bersyarat' });
        await app.getByLabel('Lama Perbaikan (hari kerja)').fill('14');
        await app
            .getByLabel('Catatan')
            .fill('Lulus dengan perbaikan sesuai lembar revisi.');

        await app
            .getByRole('button', { name: 'Tetapkan Berita Acara' })
            .click();

        await expect(app.getByText(/berita acara tersimpan/i)).toBeVisible();
    });

    test('admin merilis hasil ujian', async ({ app }) => {
        await signInAs(app, admin.email, admin.password);
        await openExam(app);

        await app.getByRole('button', { name: 'Rilis Hasil Ujian' }).click();

        await expect(app.getByText(/hasil ujian dirilis/i)).toBeVisible();
        await expect(app.getByText('Hasil Terkunci')).toBeVisible();
    });
});
