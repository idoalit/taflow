<?php

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\SupervisionIndicator;
use App\Models\User;

beforeEach(function (): void {
    seedRoles();
});

it('lists every program studi with the people in it', function (): void {
    $department = makeDepartment();
    makeStudent($department);
    makeAdmin($department);

    $this->actingAs(makeSuperAdmin())
        ->get(route('superadmin.departments.index'))
        ->assertInertia(fn ($page) => $page
            ->component('superadmin/departments/index')
            ->has('departments', 1)
            ->where('departments.0.name', $department->name)
            ->where('departments.0.students', 1)
            ->where('departments.0.admins', 1)
            ->where('departments.0.workspace', "admin:{$department->id}"));
});

it('creates a program studi ready for supervision scoring', function (): void {
    $superadmin = makeSuperAdmin();

    $response = $this->actingAs($superadmin)->post(route('superadmin.departments.store'), [
        'name' => 'Teknik Sipil',
        'code' => 'ts',
        'faculty' => 'Fakultas Teknik',
        'city' => 'Semarang',
        'description' => '',
        'is_active' => true,
    ]);

    $department = Department::where('code', 'TS')->sole();

    $response->assertRedirect(route('superadmin.departments.show', $department));

    expect($department->name)->toBe('Teknik Sipil')
        ->and($department->faculty)->toBe('Fakultas Teknik')
        ->and(SupervisionIndicator::where('department_id', $department->id)->count())->toBe(5)
        ->and(AuditLog::where('action', AuditAction::DepartmentCreated->value)->sole()->user_id)->toBe($superadmin->id);
});

it('rejects a code another program studi already uses', function (): void {
    Department::factory()->create(['code' => 'TI']);

    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.departments.store'), [
            'name' => 'Teknik Informatika Kedua',
            'code' => 'ti',
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['code' => 'Kode ini sudah dipakai program studi lain.']);

    expect(Department::count())->toBe(1);
});

it('accepts a code numbered with dots', function (): void {
    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.departments.store'), [
            'name' => 'Keperawatan Semarang Program Diploma Tiga',
            'code' => '1.1.11',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect(Department::where('code', '1.1.11')->exists())->toBeTrue();
});

it('rejects a code with characters other than letters, digits, dots, dashes and underscores', function (): void {
    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.departments.store'), [
            'name' => 'Teknik Sipil',
            'code' => 'TS/01',
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['code' => 'Kode hanya boleh berisi huruf, angka, titik, tanda hubung, dan garis bawah.']);

    expect(Department::count())->toBe(0);
});

it('requires a name and a code', function (): void {
    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.departments.store'), [])
        ->assertSessionHasErrors(['name', 'code', 'is_active']);
});

it('archives a program studi without deleting anything in it', function (): void {
    $department = makeDepartment();
    $student = makeStudent($department);

    $this->actingAs(makeSuperAdmin())
        ->put(route('superadmin.departments.update', $department), [
            'name' => $department->name,
            'code' => $department->code,
            'is_active' => false,
        ])
        ->assertRedirect(route('superadmin.departments.show', $department));

    expect($department->refresh()->is_active)->toBeFalse();
    $this->assertModelExists($student);
});

it('refuses to delete a program studi that still has students', function (): void {
    $department = makeDepartment();
    makeStudent($department);

    $this->actingAs(makeSuperAdmin())
        ->delete(route('superadmin.departments.destroy', $department))
        ->assertSessionHas('error');

    $this->assertModelExists($department);
});

it('deletes a program studi nobody was ever in', function (): void {
    $department = makeDepartment();

    $this->actingAs(makeSuperAdmin())
        ->delete(route('superadmin.departments.destroy', $department))
        ->assertRedirect(route('superadmin.departments.index'));

    $this->assertModelMissing($department);
    expect(AuditLog::where('action', AuditAction::DepartmentDeleted->value)->sole()->changes['name'])
        ->toBe($department->name);
});

it('makes an existing account the Admin Prodi of a program studi', function (): void {
    $department = makeDepartment();
    $lecturer = makeLecturer();

    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.departments.admins.store', $department), ['user_id' => $lecturer->user_id])
        ->assertRedirect();

    $user = User::findOrFail($lecturer->user_id);

    expect($user->hasRole(UserRole::Admin->value))->toBeTrue()
        ->and($user->workspaces()->map->key()->all())->toContain("admin:{$department->id}");
});

it('refuses to make a mahasiswa an Admin Prodi', function (): void {
    $department = makeDepartment();
    $student = makeStudent($department);

    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.departments.admins.store', $department), ['user_id' => $student->user_id])
        ->assertSessionHasErrors(['user_id' => 'Akun mahasiswa tidak dapat ditugaskan sebagai Admin Prodi.']);

    expect($department->admins()->count())->toBe(0);
});

it('takes the admin role away with the last program studi', function (): void {
    $first = makeDepartment();
    $second = makeDepartment();
    $admin = makeAdmin($first);
    $admin->departments()->attach($second);
    $superadmin = makeSuperAdmin();

    $this->actingAs($superadmin)
        ->delete(route('superadmin.departments.admins.destroy', [$first, $admin]))
        ->assertRedirect();

    expect(User::findOrFail($admin->id)->hasRole(UserRole::Admin->value))->toBeTrue();

    $this->actingAs($superadmin)
        ->delete(route('superadmin.departments.admins.destroy', [$second, $admin]))
        ->assertRedirect();

    expect(User::findOrFail($admin->id)->hasRole(UserRole::Admin->value))->toBeFalse();
});
