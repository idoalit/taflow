import {
    acceptInvitation,
    admin,
    department,
    signInAs,
    signOutOf,
    superadmin,
} from './support/actors';
import { expect, test } from './support/app';

/**
 * Bagian 0 — Superadmin menyiapkan sistem.
 *
 * Basis data hanya berisi satu akun superadmin. Program studi dan Admin Prodi
 * yang dipakai seluruh cerita berikutnya dibuat di sini, lewat antarmuka.
 */
test.describe.serial('Bagian 0 · Superadmin menyiapkan sistem', () => {
    test('sistem masih kosong: belum ada program studi', async ({ app }) => {
        await signInAs(app, superadmin.email, superadmin.password);

        await expect(app.getByText('Seluruh program studi')).toBeVisible();
        await expect(app.getByText(/belum ada program studi/i)).toBeVisible();
    });

    test('superadmin membuat program studi', async ({ app }) => {
        await app.goto('/superadmin/departments/create');

        await app.getByLabel('Nama Program Studi').fill(department.name);
        await app.getByLabel('Kode').fill(department.code.toLowerCase());
        await app.getByLabel('Fakultas').fill(department.faculty);
        await app.getByRole('button', { name: 'Buat Program Studi' }).click();

        await app.waitForURL(/\/superadmin\/departments\/\d+$/);
        await expect(
            app.getByRole('heading', { name: department.name }),
        ).toBeVisible();

        // Tanpa admin, belum ada yang bisa mengelola prodi ini.
        await expect(
            app.getByText(/belum memiliki admin/i).first(),
        ).toBeVisible();
    });

    test('superadmin membuat akun Admin Prodi untuk program studi itu', async ({
        app,
    }) => {
        await app.getByRole('link', { name: 'Buat Akun Admin Baru' }).click();
        await app.waitForURL(/\/superadmin\/users\/create/);

        // Prodi asalnya sudah tercentang, karena tautannya membawa prodi itu.
        await expect(
            app.getByRole('checkbox', { name: department.name }),
        ).toBeChecked();

        await app.getByLabel('Nama').fill(admin.name);
        await app.getByLabel('Surel').fill(admin.email);
        await app
            .getByRole('button', { name: 'Buat Akun & Kirim Tautan' })
            .click();

        await app.waitForURL(/\/superadmin\/users\/\d+$/);
        await expect(
            app.getByRole('heading', { name: admin.name }),
        ).toBeVisible();
        await expect(app.getByText('Admin Prodi').first()).toBeVisible();
    });

    test('superadmin masuk ke program studi sebagai Admin Prodi, lalu kembali', async ({
        app,
    }) => {
        await app.goto('/superadmin/departments');
        await app
            .getByRole('button', {
                name: `Masuk sebagai Admin Prodi ${department.name}`,
            })
            .click();

        await app.waitForURL(/\/dashboard/);
        await expect(app.getByText('Admin Program Studi')).toBeVisible();

        // Layar admin prodi terbuka, dan layar sistem tertutup selama di sini.
        await app.goto('/admin/students');
        await expect(
            app.getByText(/tidak ada mahasiswa yang cocok/i),
        ).toBeVisible();

        await app.goto('/superadmin/departments');
        await expect(
            app.getByText(/hanya tersedia pada workspace Superadmin/i),
        ).toBeVisible();

        await app.goto('/dashboard');
        await app.getByRole('button', { name: 'Menu akun' }).click();
        await app.getByRole('button', { name: 'Superadmin' }).click();

        await expect(app.getByText('Seluruh program studi')).toBeVisible();
    });

    test('admin prodi mengaktifkan akun lewat tautan yang dikirim superadmin', async ({
        app,
    }) => {
        await signOutOf(app);
        await acceptInvitation(app, admin.email);

        await signInAs(app, admin.email, admin.password);
        await expect(app.getByText(admin.name).first()).toBeVisible();
        await expect(app.getByText(department.name).first()).toBeVisible();
    });
});
