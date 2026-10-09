<?php

use App\Models\Department;

beforeEach(function (): void {
    seedRoles();
});

it('shows the superadmin every program studi and flags the ones nobody runs', function (): void {
    $staffed = makeDepartment();
    makeAdmin($staffed);
    makeStudent($staffed);
    $unstaffed = makeDepartment();

    $this->actingAs(makeSuperAdmin())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('superadmin/dashboard')
            ->has('departments', 2)
            ->where('kpis.0', ['label' => 'Program Studi Aktif', 'value' => '2'])
            ->where('kpis.4', ['label' => 'Mahasiswa', 'value' => '1'])
            ->has('alerts', 1)
            ->where('alerts.0.text', "{$unstaffed->name} belum memiliki Admin Prodi")
            ->where('alerts.0.href', route('superadmin.departments.show', $unstaffed)));
});

it('counts the unstaffed program studi instead of listing them once there are many', function (): void {
    Department::factory()->count(4)->sequence(
        ['name' => 'Akuntansi'],
        ['name' => 'Biologi'],
        ['name' => 'Farmasi'],
        ['name' => 'Hukum'],
    )->create();

    $this->actingAs(makeSuperAdmin())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('alerts', 1)
            ->where('alerts.0.text', '4 program studi belum memiliki Admin Prodi')
            ->where('alerts.0.href', route('superadmin.departments.index')));
});
