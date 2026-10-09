<?php

namespace Database\Seeders;

use App\Actions\Thesis\ProvisionDepartment;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * The faculty's Form Penilaian Proses Bimbingan for program studi that were
 * created before a screen provisioned it, or by a seeder. The indicators
 * themselves live in ProvisionDepartment, which a new program studi runs
 * through on its own.
 */
class SupervisionIndicatorSeeder extends Seeder
{
    public function run(?Department $department = null): void
    {
        $departments = $department !== null
            ? collect([$department])
            : Department::query()->where('is_active', true)->get();

        foreach ($departments as $target) {
            app(ProvisionDepartment::class)->handle($target);
        }
    }
}
