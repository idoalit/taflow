<?php

use App\Enums\GuidanceDisplayStatus;
use App\Enums\GuidanceStage;
use App\Enums\GuidanceType;
use App\Models\GuidanceSession;
use App\Models\Student;
use App\Models\ThesisProposal;

beforeEach(function (): void {
    seedRoles();
});

function supervisedStudent(): Student
{
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    return $student;
}

it('calls a face-to-face session booked for later Terjadwal, not Berlangsung', function (): void {
    $session = GuidanceSession::factory()
        ->for(supervisedStudent())
        ->create(['meeting_scheduled_at' => now()->addDay()]);

    expect($session->displayStatus())->toBe(GuidanceDisplayStatus::Scheduled)
        ->and($session->displayStatus()->label())->toBe('Terjadwal');
});

it('calls it Berlangsung once its slot has arrived', function (): void {
    $session = GuidanceSession::factory()
        ->for(supervisedStudent())
        ->create(['meeting_scheduled_at' => now()->subMinutes(5)]);

    expect($session->displayStatus())->toBe(GuidanceDisplayStatus::Ongoing);
});

it('treats an async discussion as live regardless of the clock', function (): void {
    $session = GuidanceSession::factory()->for(supervisedStudent())->async()->create();

    expect($session->displayStatus())->toBe(GuidanceDisplayStatus::Ongoing);
});

it('keeps completed and cancelled sessions out of the live tabs', function (): void {
    $student = supervisedStudent();
    GuidanceSession::factory()->for($student)->completed()->create();

    expect(GuidanceSession::query()->displayStatus(GuidanceDisplayStatus::Completed)->count())->toBe(1)
        ->and(GuidanceSession::query()->displayStatus(GuidanceDisplayStatus::Ongoing)->count())->toBe(0)
        ->and(GuidanceSession::query()->displayStatus(GuidanceDisplayStatus::Scheduled)->count())->toBe(0);
});

it('opens on the live tab when something is running', function (): void {
    $student = supervisedStudent();
    $lecturer = assignSupervisor($student);

    GuidanceSession::factory()->for($student)->async()->create(['title' => 'Diskusi berjalan']);
    GuidanceSession::factory()->for($student)->create(['meeting_scheduled_at' => now()->addWeek()]);

    $this->actingAs($lecturer->user)
        ->get(route('guidance-sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('filters.tab', 'ongoing')
            ->has('sessions', 1)
            ->where('sessions.0.title', 'Diskusi berjalan'));
});

it('falls back to the Terjadwal tab when nothing is running', function (): void {
    $student = supervisedStudent();
    $lecturer = assignSupervisor($student);

    GuidanceSession::factory()->for($student)->create(['meeting_scheduled_at' => now()->addWeek()]);
    GuidanceSession::factory()->for($student)->completed()->create();

    $this->actingAs($lecturer->user)
        ->get(route('guidance-sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('filters.tab', 'scheduled')
            ->has('sessions', 1));
});

it('falls back to Selesai when nothing is running or booked', function (): void {
    $student = supervisedStudent();
    $lecturer = assignSupervisor($student);
    GuidanceSession::factory()->count(2)->for($student)->completed()->create();

    $this->actingAs($lecturer->user)
        ->get(route('guidance-sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('filters.tab', 'completed')
            ->has('sessions', 2));
});

it('honours an explicit tab over the default', function (): void {
    $student = supervisedStudent();
    $lecturer = assignSupervisor($student);
    GuidanceSession::factory()->for($student)->async()->create();

    $this->actingAs($lecturer->user)
        ->get(route('guidance-sessions.index', ['tab' => 'completed']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.tab', 'completed')
            ->has('sessions', 0));
});

it('counts every tab so the supervisor sees the workload at a glance', function (): void {
    $student = supervisedStudent();
    $lecturer = assignSupervisor($student);

    GuidanceSession::factory()->for($student)->async()->create();
    GuidanceSession::factory()->count(2)->for($student)->create(['meeting_scheduled_at' => now()->addWeek()]);
    GuidanceSession::factory()->count(3)->for($student)->completed()->create();

    $this->actingAs($lecturer->user)
        ->get(route('guidance-sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('tabs.0.value', 'scheduled')
            ->where('tabs.0.count', 2)
            ->where('tabs.1.value', 'ongoing')
            ->where('tabs.1.count', 1)
            ->where('tabs.2.count', 3));
});

it('tells a supervisor which student each session belongs to', function (): void {
    $student = supervisedStudent();
    $lecturer = assignSupervisor($student);
    GuidanceSession::factory()->for($student)->async()->create();

    $this->actingAs($lecturer->user)
        ->get(route('guidance-sessions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('sessions.0.student.name', $student->user->name)
            ->where('sessions.0.student.student_number', $student->student_number));
});

it('refuses a slot that clashes with the supervisor\'s existing booking', function (): void {
    $taken = supervisedStudent();
    $lecturer = assignSupervisor($taken);

    $slotAt = now()->addDay()->setTime(9, 0);
    GuidanceSession::factory()->for($taken)->create(['meeting_scheduled_at' => $slotAt]);

    // A different student sharing the same supervisor.
    $other = makeStudent($taken->department);
    ThesisProposal::factory()->for($other)->for($other->department)->approved()->create();
    assignSupervisor($other, $lecturer);

    $this->actingAs($other->user)
        ->post(route('guidance-sessions.store'), [
            'title' => 'Bimbingan Bab 1',
            'stage' => GuidanceStage::Proposal->value,
            'target_type' => 'single',
            'guidance_type' => GuidanceType::FaceToFace->value,
            'meeting_type' => 'offline',
            'meeting_location' => 'Ruang Dosen 301',
            'meeting_scheduled_at' => $slotAt->copy()->addMinutes(30)->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasErrors('meeting_scheduled_at');

    expect(GuidanceSession::count())->toBe(1);
});

it('accepts a slot outside the supervisor\'s booked window', function (): void {
    $taken = supervisedStudent();
    $lecturer = assignSupervisor($taken);

    $slotAt = now()->addDay()->setTime(9, 0);
    GuidanceSession::factory()->for($taken)->create(['meeting_scheduled_at' => $slotAt]);

    $other = makeStudent($taken->department);
    ThesisProposal::factory()->for($other)->for($other->department)->approved()->create();
    assignSupervisor($other, $lecturer);

    $this->actingAs($other->user)
        ->post(route('guidance-sessions.store'), [
            'title' => 'Bimbingan Bab 1',
            'stage' => GuidanceStage::Proposal->value,
            'target_type' => 'single',
            'guidance_type' => GuidanceType::FaceToFace->value,
            'meeting_type' => 'offline',
            'meeting_location' => 'Ruang Dosen 301',
            'meeting_scheduled_at' => $slotAt->copy()->addHours(2)->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasNoErrors();

    expect(GuidanceSession::count())->toBe(2);
});

it('never clashes an async discussion against a booked slot', function (): void {
    $taken = supervisedStudent();
    $lecturer = assignSupervisor($taken);

    $slotAt = now()->addDay()->setTime(9, 0);
    GuidanceSession::factory()->for($taken)->create(['meeting_scheduled_at' => $slotAt]);

    $other = makeStudent($taken->department);
    ThesisProposal::factory()->for($other)->for($other->department)->approved()->create();
    assignSupervisor($other, $lecturer);

    $this->actingAs($other->user)
        ->post(route('guidance-sessions.store'), [
            'title' => 'Diskusi Bab 1',
            'stage' => GuidanceStage::Proposal->value,
            'target_type' => 'single',
            'guidance_type' => GuidanceType::OnlineAsync->value,
        ])
        ->assertSessionHasNoErrors();

    expect(GuidanceSession::count())->toBe(2);
});

it('does not clash against a supervisor the student is not booking', function (): void {
    $taken = supervisedStudent();
    $busy = assignSupervisor($taken);

    $slotAt = now()->addDay()->setTime(9, 0);
    GuidanceSession::factory()->for($taken)->create(['meeting_scheduled_at' => $slotAt]);

    // A student of a different supervisor entirely.
    $other = makeStudent($taken->department);
    ThesisProposal::factory()->for($other)->for($other->department)->approved()->create();
    assignSupervisor($other);

    expect($busy->id)->not->toBe($other->supervisors->first()->id);

    $this->actingAs($other->user)
        ->post(route('guidance-sessions.store'), [
            'title' => 'Bimbingan Bab 1',
            'stage' => GuidanceStage::Proposal->value,
            'target_type' => 'single',
            'guidance_type' => GuidanceType::FaceToFace->value,
            'meeting_type' => 'offline',
            'meeting_location' => 'Ruang Dosen 302',
            'meeting_scheduled_at' => $slotAt->copy()->format('Y-m-d\TH:i'),
        ])
        ->assertSessionHasNoErrors();

    expect(GuidanceSession::count())->toBe(2);
});
