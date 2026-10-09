<?php

namespace App\Actions\Thesis;

use App\Models\Department;
use App\Models\SupervisionIndicator;

/**
 * Gives a program studi the master data no screen creates: the faculty's Form
 * Penilaian Proses Bimbingan, indicator for indicator. Without it a pembimbing
 * meets a refusal and the final grade can never be composed.
 *
 * Five lines marked out of 20, which is the 100 the supervision score is worth
 * before it is weighted against the exam.
 */
final readonly class ProvisionDepartment
{
    private const MAX_SCORE = 20;

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

    public function handle(Department $department): void
    {
        foreach (self::INDICATORS as $order => $indicator) {
            SupervisionIndicator::updateOrCreate(
                ['department_id' => $department->id, 'code' => $indicator['code']],
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
