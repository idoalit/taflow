import { admin, inDays, lecturers, signInAs, student } from './support/actors';
import { expect, test } from './support/app';

/**
 * Bagian 5 — Pengajuan dan penjadwalan ujian proposal.
 *
 * Tiga tanda tangan berurutan: mahasiswa mengajukan, pembimbing memverifikasi,
 * lalu program studi memeriksa berkas dan menjadwalkan. Admin tidak bisa
 * mendahului pembimbing.
 */
test.describe.serial('Bagian 5 · Pengajuan dan penjadwalan ujian', () => {
    test('pembimbing belum punya pengajuan untuk diverifikasi', async ({
        app,
    }) => {
        await signInAs(app, lecturers.pembimbingUtama.email);

        await app.goto('/exam-verifications');
        await expect(
            app.getByText(/belum ada|tidak ada/i).first(),
        ).toBeVisible();
    });

    test('mahasiswa mengirim pengajuan ujian proposal', async ({ app }) => {
        await signInAs(app, student.email);

        await app.goto('/proposal-exam-requests/create');
        await app
            .locator('textarea')
            .first()
            .fill(
                'Seluruh bimbingan proposal telah selesai, mohon dijadwalkan.',
            );

        await app.getByRole('button', { name: 'Kirim Pengajuan' }).click();

        await app.waitForURL(/\/proposal-exam-requests/);
        await expect(app.getByText(/menunggu|diajukan/i).first()).toBeVisible();
    });

    test('admin belum bisa menjadwalkan sebelum pembimbing memverifikasi', async ({
        app,
    }) => {
        await signInAs(app, admin.email, admin.password);

        await app.goto('/admin/exams');
        await expect(
            app.getByRole('link', { name: /Buat Jadwal/ }),
        ).toHaveCount(0);
    });

    test('pembimbing memverifikasi pengajuan', async ({ app }) => {
        await signInAs(app, lecturers.pembimbingUtama.email);

        await app.goto('/exam-verifications');
        await app.getByRole('row', { name: new RegExp(student.name) }).click();
        await app.waitForURL(/\/exam-verifications\/\w+\/\d+/);

        await app
            .getByLabel('Catatan Verifikasi')
            .fill('Mahasiswa siap mengikuti ujian proposal.');
        await app.getByRole('button', { name: 'Setujui Pengajuan' }).click();

        await expect(
            app.getByText(/disetujui|terverifikasi/i).first(),
        ).toBeVisible();
    });

    test('admin memeriksa berkas pengajuan', async ({ app }) => {
        await signInAs(app, admin.email, admin.password);

        await app.goto('/admin/exam-requests');
        await app.getByRole('row', { name: new RegExp(student.name) }).click();
        await app.waitForURL(/\/admin\/exam-requests\/\w+\/\d+/);

        await app
            .getByLabel('Catatan Admin')
            .fill('Berkas lengkap, dijadwalkan pada periode berjalan.');
        await app.getByRole('button', { name: 'Setujui Pengajuan' }).click();

        await expect(
            app.getByText(/disetujui|terverifikasi/i).first(),
        ).toBeVisible();
    });

    test('admin menjadwalkan ujian dan menyusun panel penguji', async ({
        app,
    }) => {
        await app.goto('/admin/exams');
        await app.getByRole('link', { name: /Buat Jadwal/ }).click();
        await app.waitForURL(/\/schedule/);

        await app
            .getByLabel('Judul Ujian')
            .fill(`Ujian Proposal — ${student.name}`);
        await app.getByLabel('Tanggal & Waktu').fill(inDays(30, '09:00'));
        await app.getByLabel('Tempat').fill('Ruang Sidang 1');

        // Both pembimbing are seated as well: the final grade cannot be
        // composed without a pembimbing on the panel to mark the supervision
        // form, so a panel of examiners alone would dead-end.
        const panel: [string, string][] = [
            [lecturers.ketuaPenguji.name, 'Ketua Penguji'],
            [lecturers.anggotaPenguji.name, 'Anggota Penguji'],
            [lecturers.pembimbingUtama.name, 'Pembimbing'],
            [lecturers.pembimbingPendamping.name, 'Pembimbing'],
        ];

        // One Peran select per seat, so the rows are counted by those; the
        // form opens with seats of its own and a blank one fails validation.
        const seats = app.locator('select');

        while ((await seats.count()) > panel.length) {
            await app
                .getByRole('button', { name: 'Hapus penguji' })
                .last()
                .click();
        }

        while ((await seats.count()) < panel.length) {
            await app.getByRole('button', { name: 'Tambah Penguji' }).click();
        }

        for (const [index, [name, role]] of panel.entries()) {
            await app
                .locator('button[role="combobox"]', { hasText: 'Pilih dosen' })
                .first()
                .click();
            await app.getByRole('option', { name }).click();
            await seats.nth(index).selectOption({ label: role });
        }

        await app.getByRole('button', { name: 'Simpan Jadwal' }).click();

        await app.waitForURL(/\/admin\/exams/);
        await expect(
            app.getByText(`Ujian Proposal — ${student.name}`).first(),
        ).toBeVisible();
    });
});
