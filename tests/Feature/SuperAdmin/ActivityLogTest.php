<?php

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Department;

beforeEach(function (): void {
    seedRoles();
});

it('reads the trail newest first, in words', function (): void {
    $superadmin = makeSuperAdmin();
    $department = makeDepartment();

    $this->travelTo('2026-10-01 08:00:00');
    AuditLog::record($department, AuditAction::DepartmentCreated, $superadmin, changes: ['name' => $department->name]);

    $this->travelTo('2026-10-02 09:30:00');
    AuditLog::record($department, AuditAction::DepartmentUpdated, $superadmin, reason: 'Ganti nama', changes: [
        'name' => $department->name,
        'is_active' => false,
    ]);

    $this->actingAs($superadmin)
        ->get(route('superadmin.activity-logs.index'))
        ->assertInertia(fn ($page) => $page
            ->component('superadmin/activity-logs/index')
            ->has('logs.data', 2)
            ->where('logs.data.0.action_label', 'Program studi diubah')
            ->where('logs.data.0.actor', $superadmin->name)
            ->where('logs.data.0.subject', "Program Studi · {$department->name}")
            ->where('logs.data.0.reason', 'Ganti nama')
            ->where('logs.data.0.changes', [['key' => 'aktif', 'value' => 'tidak']])
            ->where('logs.data.0.time', '2 Okt 2026, 09:30')
            ->where('logs.data.1.action_label', 'Program studi dibuat'));
});

it('still names a subject that has since been deleted', function (): void {
    $superadmin = makeSuperAdmin();
    $department = Department::factory()->create(['name' => 'Teknik Mesin']);

    AuditLog::record($department, AuditAction::DepartmentDeleted, $superadmin, changes: ['name' => 'Teknik Mesin']);
    $department->delete();

    $this->actingAs($superadmin)
        ->get(route('superadmin.activity-logs.index'))
        ->assertInertia(fn ($page) => $page->where('logs.data.0.subject', 'Program Studi · Teknik Mesin'));
});

it('spells out a recorded exam decision instead of its stored value', function (): void {
    $superadmin = makeSuperAdmin();

    AuditLog::record(makeDepartment(), AuditAction::MinutesDecided, $superadmin, changes: [
        'decision' => 'conditional_pass',
        'final_score' => 85.25,
    ]);

    $this->actingAs($superadmin)
        ->get(route('superadmin.activity-logs.index'))
        ->assertInertia(fn ($page) => $page->where('logs.data.0.changes', [
            ['key' => 'keputusan', 'value' => 'Lulus Bersyarat'],
            ['key' => 'nilai akhir', 'value' => '85.25'],
        ]));
});

it('filters the trail by action', function (): void {
    $superadmin = makeSuperAdmin();
    $department = makeDepartment();

    AuditLog::record($department, AuditAction::DepartmentCreated, $superadmin);
    AuditLog::record($superadmin, AuditAction::UserInvited, $superadmin);

    $this->actingAs($superadmin)
        ->get(route('superadmin.activity-logs.index', ['action' => AuditAction::UserInvited->value]))
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action_label', 'Tautan aktivasi dikirim'));
});

it('filters the trail by who acted and when', function (): void {
    $superadmin = makeSuperAdmin();
    $other = makeSuperAdmin();
    $department = makeDepartment();

    $this->travelTo('2026-09-01 10:00:00');
    AuditLog::record($department, AuditAction::DepartmentCreated, $superadmin);

    $this->travelTo('2026-10-05 10:00:00');
    AuditLog::record($department, AuditAction::DepartmentUpdated, $superadmin);
    AuditLog::record($department, AuditAction::DepartmentUpdated, $other);

    $this->actingAs($superadmin)
        ->get(route('superadmin.activity-logs.index', [
            'actor' => $superadmin->email,
            'from' => '2026-10-01',
            'to' => '2026-10-31',
        ]))
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.actor_email', $superadmin->email)
            ->where('logs.data.0.action_label', 'Program studi diubah'));
});

it('rejects an action the trail has never recorded', function (): void {
    $this->actingAs(makeSuperAdmin())
        ->get(route('superadmin.activity-logs.index', ['action' => 'bukan.aksi']))
        ->assertSessionHasErrors('action');
});
