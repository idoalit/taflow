<?php

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Broadcast;

beforeEach(function (): void {
    seedRoles();
});

it('delivers a broadcast to one role of one program studi and nobody else', function (): void {
    $department = makeDepartment();
    $first = makeStudent($department);
    $second = makeStudent($department);
    $lecturer = assignSupervisor($first);
    $elsewhere = makeStudent();
    $superadmin = makeSuperAdmin();

    $this->actingAs($superadmin)
        ->post(route('superadmin.broadcasts.store'), [
            'title' => 'Jadwal Yudisium',
            'message' => 'Pendaftaran yudisium dibuka sampai akhir bulan.',
            'url' => '/dashboard',
            'audience_role' => UserRole::Student->value,
            'department_id' => $department->id,
        ])
        ->assertRedirect(route('superadmin.broadcasts.index'))
        ->assertSessionHas('success', 'Siaran terkirim ke 2 pengguna.');

    $broadcast = Broadcast::sole();
    $notification = $first->user->notifications()->sole();

    expect($broadcast->recipients_count)->toBe(2)
        ->and($broadcast->audienceLabel())->toBe("Mahasiswa · {$department->name}")
        ->and($notification->data)->toMatchArray([
            'category' => 'announcement',
            'title' => 'Jadwal Yudisium',
            'url' => '/dashboard',
        ])
        ->and($second->user->notifications()->count())->toBe(1)
        ->and($lecturer->user->notifications()->count())->toBe(0)
        ->and($elsewhere->user->notifications()->count())->toBe(0)
        ->and(AuditLog::where('action', AuditAction::BroadcastSent->value)->sole()->user_id)->toBe($superadmin->id);
});

it('delivers an unaddressed broadcast to everyone but the sender', function (): void {
    $student = makeStudent();
    $lecturer = assignSupervisor($student);
    $admin = makeAdmin($student->department);
    $colleague = makeSuperAdmin();
    $superadmin = makeSuperAdmin();

    $this->actingAs($superadmin)
        ->post(route('superadmin.broadcasts.store'), ['title' => 'Pemeliharaan', 'message' => 'Sistem berhenti Sabtu malam.'])
        ->assertSessionHas('success', 'Siaran terkirim ke 4 pengguna.');

    expect($student->user->notifications()->count())->toBe(1)
        ->and($lecturer->user->notifications()->count())->toBe(1)
        ->and($admin->notifications()->count())->toBe(1)
        ->and($colleague->notifications()->count())->toBe(1)
        ->and($superadmin->notifications()->count())->toBe(0);
});

it('reaches every dosen of a program studi, including one who teaches in two', function (): void {
    $first = makeDepartment();
    $second = makeDepartment();
    $shared = assignSupervisor(makeStudent($first));
    assignSupervisor(makeStudent($second), $shared);
    $other = assignSupervisor(makeStudent($second));

    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.broadcasts.store'), [
            'title' => 'Rapat Dosen',
            'message' => 'Rapat koordinasi pembimbing.',
            'audience_role' => UserRole::Lecturer->value,
            'department_id' => $first->id,
        ])
        ->assertSessionHas('success', 'Siaran terkirim ke 1 pengguna.');

    expect($shared->user->notifications()->count())->toBe(1)
        ->and($other->user->notifications()->count())->toBe(0);
});

it('tells the sender how many people an audience reaches before sending', function (): void {
    $department = makeDepartment();
    makeStudent($department);
    makeStudent($department);
    makeStudent();

    $this->actingAs(makeSuperAdmin())
        ->get(route('superadmin.broadcasts.create', [
            'audience_role' => UserRole::Student->value,
            'department_id' => $department->id,
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('superadmin/broadcasts/create')
            ->where('recipientCount', 2)
            ->where('audience.role', 'student'));
});

it('sends nothing to an audience with nobody in it', function (): void {
    $department = makeDepartment();

    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.broadcasts.store'), [
            'title' => 'Kosong',
            'message' => 'Tidak ada yang menerima.',
            'department_id' => $department->id,
        ])
        ->assertSessionHasErrors(['audience' => 'Tidak ada pengguna pada sasaran yang dipilih.']);

    expect(Broadcast::count())->toBe(0);
});

it('rejects a link a browser would run rather than open', function (string $url): void {
    makeStudent();

    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.broadcasts.store'), ['title' => 'Tautan', 'message' => 'Klik di sini.', 'url' => $url])
        ->assertSessionHasErrors([
            'url' => 'Tautan harus berupa alamat lengkap (https://…) atau halaman aplikasi ini (/…).',
        ]);

    expect(Broadcast::count())->toBe(0);
})->with([
    'javascript' => ['javascript:alert(1)'],
    'protocol-relative' => ['//evil.example/phish'],
    'data' => ['data:text/html,<script>alert(1)</script>'],
]);

it('cannot be addressed to superadmins as a role', function (): void {
    makeSuperAdmin();

    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.broadcasts.store'), [
            'title' => 'Khusus',
            'message' => 'Untuk superadmin.',
            'audience_role' => UserRole::SuperAdmin->value,
        ])
        ->assertSessionHasErrors('audience_role');
});

it('requires a title and a message', function (): void {
    $this->actingAs(makeSuperAdmin())
        ->post(route('superadmin.broadcasts.store'), [])
        ->assertSessionHasErrors(['title', 'message']);
});
