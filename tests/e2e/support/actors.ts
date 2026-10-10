import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, type Page } from '@playwright/test';
import { latestResetLink } from './mail';

/** Who is signed in to the one window, so a handover is only done when needed. */
let signedIn: string | null = null;

/** The password every account in the story ends up with. */
export const PASSWORD = 'RahasiaE2E!2026';

/** The only account the database starts with; see E2EBootstrapSeeder. */
export const superadmin = {
    name: 'Superadmin E2E',
    email: 'superadmin.e2e@fipro.test',
    password: 'password',
};

export const department = {
    name: 'Teknik Informatika',
    code: 'TI',
    faculty: 'Fakultas Teknik',
};

/** Created by the superadmin in Bagian 0, and activated like everyone else. */
export const admin = {
    name: 'Admin Prodi E2E',
    email: 'admin.e2e@fipro.test',
    password: PASSWORD,
};

export const lecturers = {
    pembimbingUtama: {
        name: 'Dr. Rahmat Hidayat',
        email: 'rahmat@e2e.test',
        nip: '0198701',
    },
    pembimbingPendamping: {
        name: 'Nurul Hasanah, M.Kom',
        email: 'nurul@e2e.test',
        nip: '0198702',
    },
    ketuaPenguji: {
        name: 'Prof. Dr. Slamet Riyadi',
        email: 'slamet@e2e.test',
        nip: '0198703',
    },
    anggotaPenguji: {
        name: 'Yoga Pratama, M.T.',
        email: 'yoga@e2e.test',
        nip: '0198704',
    },
};

export const student = {
    name: 'Intan Permata',
    email: 'intan@e2e.test',
    nim: '22051001',
    angkatan: '2022',
    judul: 'Klasifikasi Keluhan Layanan Akademik dengan Naive Bayes',
};

/**
 * A moment some days from today, in the shape a datetime-local field takes.
 * Schedules have to lie in the future, so a date written into a test stops
 * being valid the day it is reached; this one keeps its distance.
 */
export function inDays(days: number, time: string): string {
    const date = new Date();
    date.setDate(date.getDate() + days);

    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${time}`;
}

export async function signIn(
    page: Page,
    email: string,
    password: string = PASSWORD,
): Promise<void> {
    // The front door is the sign-in screen.
    await page.goto('/');
    await page.getByLabel('Surel').fill(email);
    await page.getByLabel('Kata Sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk', exact: true }).click();
    await page.waitForURL(/\/dashboard/);
}

export async function signOut(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Menu akun' }).click();
    await page.getByRole('button', { name: 'Keluar' }).click();
    await page.waitForURL(/\/login|\/$/);

    signedIn = null;
}

/**
 * An imported account has no usable password: the person receives one the only
 * way the application offers, by asking for a reset link. The test clicks the
 * same link out of the mail log.
 */
export async function activateAccount(
    page: Page,
    email: string,
): Promise<void> {
    await page.goto('/forgot-password');
    await page.getByLabel('Email address').fill(email);
    await page
        .getByRole('button', { name: /email password reset link/i })
        .click();

    await expect(page.getByText(/link.*sent|tautan/i).first()).toBeVisible();

    await page.goto(latestResetLink(email));
    await page.getByLabel('Password', { exact: true }).fill(PASSWORD);
    await page.getByLabel(/confirm password/i).fill(PASSWORD);
    await page.getByRole('button', { name: /reset password/i }).click();

    await page.waitForURL(/\/login/);
}

/**
 * An account the superadmin created is mailed its link without asking for one.
 * The test opens that same mail rather than requesting a second link, so the
 * invitation itself is what gets proven.
 */
export async function acceptInvitation(
    page: Page,
    email: string,
): Promise<void> {
    await page.goto(latestResetLink(email));
    await page.getByLabel('Password', { exact: true }).fill(PASSWORD);
    await page.getByLabel(/confirm password/i).fill(PASSWORD);
    await page.getByRole('button', { name: /reset password/i }).click();

    await page.waitForURL(/\/login/);
}

/**
 * Put this person in front of the window.
 *
 * Signing in is throttled to five attempts a minute per account, which a person
 * never reaches but a suite that hands the window between six people does. The
 * counter lives in the cache table — sessions are in their own — so clearing it
 * costs nobody their session.
 */
export async function signInAs(
    page: Page,
    email: string,
    password: string = PASSWORD,
): Promise<void> {
    if (signedIn === email) {
        return;
    }

    if (signedIn !== null) {
        await signOut(page);
    }

    execFileSync('php', ['artisan', 'cache:clear'], { stdio: 'ignore' });

    await signIn(page, email, password);
    signedIn = email;
}

/** Leave the window empty, so the next person starts from the login screen. */
export async function signOutOf(page: Page): Promise<void> {
    if (signedIn !== null) {
        await signOut(page);
    }
}

/**
 * A small but genuinely valid PDF, so an upload is accepted by the same mime
 * check a real document would face.
 */
export function writePdf(name: string, title: string): string {
    const stream = `BT\n/F1 14 Tf\n1 0 0 1 60 780 Tm\n(${title.replaceAll(/[()\\]/g, '')}) Tj\nET\n`;

    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] ' +
            '/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
        `<< /Length ${stream.length} >>\nstream\n${stream}endstream`,
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    let pdf = '%PDF-1.4\n';
    const offsets: number[] = [];

    objects.forEach((body, index) => {
        offsets.push(pdf.length);
        pdf += `${index + 1} 0 obj\n${body}\nendobj\n`;
    });

    const startXref = pdf.length;
    pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n`;
    pdf += offsets
        .map((offset) => `${offset.toString().padStart(10, '0')} 00000 n \n`)
        .join('');
    pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${startXref}\n%%EOF\n`;

    const dir = join('storage', 'e2e-results', 'uploads');
    mkdirSync(dir, { recursive: true });

    const path = join(process.cwd(), dir, name);
    writeFileSync(path, pdf);

    return path;
}

/** A file the Import screen can be handed, written where the runner can see it. */
export function writeCsv(name: string, rows: string[][]): string {
    const dir = join('storage', 'e2e-results', 'uploads');
    mkdirSync(dir, { recursive: true });

    const path = join(process.cwd(), dir, name);

    // Quoted, because a dosen's name carries its own comma: "Nurul Hasanah,
    // M.Kom" would otherwise arrive as two columns.
    const csv = rows
        .map((row) =>
            row.map((cell) => `"${cell.replaceAll('"', '""')}"`).join(','),
        )
        .join('\n');

    writeFileSync(path, `${csv}\n`);

    return path;
}
