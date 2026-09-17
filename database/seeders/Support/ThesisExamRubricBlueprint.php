<?php

namespace Database\Seeders\Support;

/**
 * The faculty's Form Penilaian Ujian Tugas Akhir, item for item.
 *
 * Kept as data rather than as seeder code so a program studi's rubric can be
 * versioned, diffed and re-seeded without touching logic. The maximum scores
 * add up to exactly 100; ThesisExamRubricSeeder refuses to seed if they do not.
 */
final class ThesisExamRubricBlueprint
{
    public const NAME = 'Rubrik Ujian Tugas Akhir';

    /**
     * Blocks, each with either a flat list of items or sub-blocks of items.
     *
     * @return array<int, array{code: string, label: string, subblocks: array<int, array{code: string|null, label: string|null, items: array<int, array{text: string, max: int}>}>}>
     */
    public static function blocks(): array
    {
        return [
            [
                'code' => 'A',
                'label' => 'TATA PENULISAN',
                'subblocks' => [[
                    'code' => null,
                    'label' => null,
                    'items' => [
                        ['text' => 'Sistematika penulisan sesuai dengan buku pedoman', 'max' => 3],
                        ['text' => 'Kesinambungan antar alinea/paragraf', 'max' => 3],
                        ['text' => 'Ada tidaknya pengulangan kata/kalimat yang tidak perlu', 'max' => 3],
                        ['text' => 'Penggunaan bahasa Indonesia yang benar (EYD)', 'max' => 3],
                        ['text' => 'Penulisan rujukan/pustaka (konsistensi, relatif baru dan relevan)', 'max' => 3],
                    ],
                ]],
            ],
            [
                'code' => 'B',
                'label' => 'ISI KARYA TULIS / TUGAS AKHIR',
                'subblocks' => [
                    [
                        'code' => 'I',
                        'label' => 'Pendahuluan & Tinjauan Pustaka',
                        'items' => [
                            ['text' => 'Intisari/abstract', 'max' => 2],
                            ['text' => 'Kesesuaian judul kasus dengan latar belakang dan disertai data/fakta yang kuat', 'max' => 2],
                            ['text' => 'Tujuan dan manfaat disusun sistematis dan bermanfaat bagi perkembangan IPTEKS', 'max' => 2],
                            ['text' => 'Penggunaan dasar teori yang relevan dengan topik kasus dan sistematis', 'max' => 2],
                        ],
                    ],
                    [
                        'code' => 'II',
                        'label' => 'Analisis Masalah',
                        'items' => [
                            ['text' => 'Ketepatan metode pengumpulan data yang digunakan', 'max' => 3],
                            ['text' => 'Pengumpulan data dilakukan secara sistematik dan lengkap', 'max' => 3],
                            ['text' => 'Ketepatan dalam penentuan subjek/objek kasus yang diambil', 'max' => 3],
                            ['text' => 'Analisis masalah disusun berdasarkan rumusan masalah dan tujuan', 'max' => 3],
                            ['text' => 'Terdapat solusi alternatif dan langkah-langkah untuk menerapkan solusi', 'max' => 3],
                        ],
                    ],
                    [
                        'code' => 'III',
                        'label' => 'Hasil dan Pembahasan',
                        'items' => [
                            ['text' => 'Kesesuaian penyajian hasil dengan rumusan masalah dan tujuan', 'max' => 4],
                            ['text' => 'Ketajaman analisa mahasiswa dalam menganalisis hasil/pembahasan', 'max' => 5],
                            ['text' => 'Ketepatan dalam memilih referensi yang mendukung pembahasan', 'max' => 4],
                        ],
                    ],
                    [
                        'code' => 'IV',
                        'label' => 'Kesimpulan dan Saran',
                        'items' => [
                            ['text' => 'Kesesuaian kesimpulan dengan rumusan masalah dan tujuan (umum/khusus)', 'max' => 2],
                            ['text' => 'Pemberian saran bersifat solutif, operasional, dan mudah dilakukan', 'max' => 2],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'C',
                'label' => 'PENYAJIAN',
                'subblocks' => [[
                    'code' => null,
                    'label' => null,
                    'items' => [
                        ['text' => 'Ketepatan waktu penyajian dengan waktu yang disediakan', 'max' => 3],
                        ['text' => 'Kejelasan mengemukakan intisari kasus dalam penyajian', 'max' => 3],
                        ['text' => 'Kelancaran orasi dalam penyajian/presentasi', 'max' => 3],
                        ['text' => 'Penampilan dan sikap saat penyajian/presentasi', 'max' => 3],
                        ['text' => 'Kelengkapan media penyajian (bahan tayang visible dan lengkap)', 'max' => 3],
                    ],
                ]],
            ],
            [
                'code' => 'D',
                'label' => 'TANYA JAWAB',
                'subblocks' => [[
                    'code' => null,
                    'label' => null,
                    'items' => [
                        ['text' => 'Ketepatan dalam menjawab pertanyaan', 'max' => 7],
                        ['text' => 'Penampilan dan sikap dalam mengemukakan argumentasi', 'max' => 7],
                        ['text' => 'Kemampuan mengemukakan argumentasi', 'max' => 8],
                        ['text' => 'Penguasaan materi/kasus', 'max' => 8],
                    ],
                ]],
            ],
        ];
    }

    /**
     * Flattened to one row per criterion, in printing order.
     *
     * @return array<int, array{block_code: string, block_label: string, subblock_code: string|null, subblock_label: string|null, item_number: int, label: string, max_score: int}>
     */
    public static function criteria(): array
    {
        $rows = [];

        foreach (self::blocks() as $block) {
            foreach ($block['subblocks'] as $subblock) {
                foreach (array_values($subblock['items']) as $index => $item) {
                    $rows[] = [
                        'block_code' => $block['code'],
                        'block_label' => $block['label'],
                        'subblock_code' => $subblock['code'],
                        'subblock_label' => $subblock['label'],
                        'item_number' => $index + 1,
                        'label' => $item['text'],
                        'max_score' => $item['max'],
                    ];
                }
            }
        }

        return $rows;
    }

    public static function totalMaxScore(): int
    {
        return array_sum(array_column(self::criteria(), 'max_score'));
    }
}
