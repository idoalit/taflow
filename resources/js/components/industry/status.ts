import type { StatusCategory } from '@/types';

/**
 * Every domain status in the system maps onto one of five visual categories.
 * Keyed by the Indonesian label the server sends, matching the design.
 */
const STATUS_CATEGORIES: Record<string, StatusCategory> = {
    Disetujui: 'success',
    Terbit: 'success',
    Aktif: 'success',
    Selesai: 'success',
    Lulus: 'success',
    Berhasil: 'success',
    'Menunggu Persetujuan': 'warning',
    'Menunggu Verifikasi': 'warning',
    'Perlu Revisi': 'warning',
    Menunggu: 'warning',
    Tertunda: 'warning',
    'Lulus Bersyarat': 'warning',
    Berlangsung: 'info',
    'Sedang Berlangsung': 'info',
    Terjadwal: 'info',
    Diproses: 'info',
    Diajukan: 'info',
    'Sedang Ditinjau': 'info',
    Ditolak: 'error',
    'Tidak Lulus': 'error',
    Dibatalkan: 'error',
    'Tidak Aktif': 'neutral',
    Draft: 'neutral',
    Diarsipkan: 'neutral',
    Purnatugas: 'neutral',
};

export function statusCategory(label: string): StatusCategory {
    return STATUS_CATEGORIES[label] ?? 'neutral';
}
