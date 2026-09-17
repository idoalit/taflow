import { readFileSync } from 'node:fs';

/**
 * The application mails to the log in development, so that is where a password
 * reset link is read from — the same link the person would click.
 */
export function latestResetLink(email: string): string {
    const log = readFileSync('storage/logs/laravel.log', 'utf8');

    // Each mail is one block; take the last one addressed to this person.
    const blocks = log.split('Illuminate\\Mail\\Events\\MessageSent');
    const mine = blocks.filter((block) => block.includes(email));

    if (mine.length === 0) {
        throw new Error(`Tidak ada surel reset untuk ${email} pada log.`);
    }

    // The body is HTML, so the address stops at the first quote or angle
    // bracket — otherwise the closing tags ride along with the query string.
    const links = [
        ...mine[mine.length - 1].matchAll(
            /https?:\/\/[^\s"'<>]*reset-password\/[^\s"'<>]+/g,
        ),
    ];

    if (links.length === 0) {
        throw new Error(`Surel untuk ${email} tidak memuat tautan reset.`);
    }

    return links[links.length - 1][0].replaceAll('&amp;', '&');
}
