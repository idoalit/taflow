import {
    activateAccount,
    admin,
    lecturers,
    PASSWORD,
    signInAs,
    signOutOf,
    student,
} from './support/actors';
import { expect, test } from './support/app';

/**
 * Bagian 2 — Akun hasil impor mengaktifkan dirinya sendiri.
 *
 * Impor tidak memberi kata sandi kepada siapa pun: berkasnya hanya berisi nama,
 * surel dan nomor induk. Setiap orang menyetel kata sandinya melalui tautan
 * "lupa kata sandi", jalan yang sama yang akan mereka pakai sungguhan.
 */
const everyone = [
    { label: 'mahasiswa', ...student },
    { label: 'pembimbing utama', ...lecturers.pembimbingUtama },
    { label: 'pembimbing pendamping', ...lecturers.pembimbingPendamping },
    { label: 'ketua penguji', ...lecturers.ketuaPenguji },
    { label: 'anggota penguji', ...lecturers.anggotaPenguji },
];

test.describe.serial('Bagian 2 · Akun baru mengaktifkan diri', () => {
    test('akun hasil impor belum bisa masuk dengan kata sandi apa pun', async ({
        app,
    }) => {
        await signOutOf(app);

        await app.goto('/login');
        await app.getByLabel('Email address').fill(student.email);
        await app.getByLabel('Password', { exact: true }).fill(PASSWORD);
        await app.getByRole('button', { name: /log in/i }).click();

        await expect(app).toHaveURL(/login/);
        await expect(
            app.getByText(/surel atau kata sandi .* salah/i),
        ).toBeVisible();
    });

    for (const person of everyone) {
        test(`${person.label} menyetel kata sandi lewat tautan reset`, async ({
            app,
        }) => {
            await signOutOf(app);
            await activateAccount(app, person.email);

            await signInAs(app, person.email);
            await expect(app.getByText(person.name).first()).toBeVisible();
        });
    }

    test('admin kembali memegang jendela untuk bagian berikutnya', async ({
        app,
    }) => {
        await signInAs(app, admin.email, admin.password);
        await expect(app.getByText(admin.name).first()).toBeVisible();
    });
});
