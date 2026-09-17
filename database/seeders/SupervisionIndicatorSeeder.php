<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\SupervisionIndicator;
use Illuminate\Database\Seeder;

/**
 * The faculty's Form Penilaian Proses Bimbingan, indicator for indicator.
 *
 * Five lines marked out of 20, which is the 100 the supervision score is worth
 * before it is weighted against the exam.
 */
class SupervisionIndicatorSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, label: string}>
     */
    private const INDICATORS = [
        ['code' => '1a', 'label' => 'Konsultasi dengan pembimbing (keteraturan, kedisiplinan, dan motivasi)'],
        ['code' => '1b', 'label' => 'Respon terhadap saran pembimbing (kepatuhan dan keaktifan)'],
        ['code' => '1c', 'label' => 'Kesiapan konsultasi materi (kelengkapan)'],
        ['code' => '1d', 'label' => 'Kemampuan berdiskusi dengan pembimbing terkait laporan kasus (argumentasi)'],
        ['code' => '2', 'label' => 'Sikap mahasiswa selama proses bimbingan (etika dan sopan santun)'],
    ];

    public const MAX_SCORE = 20;

    public function run(?Department $department = null): void
    {
        $departments = $department !== null
            ? collect([$department])
            : Department::query()->where('is_active', true)->get();

        foreach ($departments as $target) {
            foreach (self::INDICATORS as $order => $indicator) {
                SupervisionIndicator::updateOrCreate(
                    ['department_id' => $target->id, 'code' => $indicator['code']],
                    [
                        'label' => $indicator['label'],
                        'max_score' => self::MAX_SCORE,
                        'order' => $order + 1,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    public static function totalMaxScore(): int
    {
        return count(self::INDICATORS) * self::MAX_SCORE;
    }
}
