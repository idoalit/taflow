<?php

use App\Enums\GuidanceStage;
use App\Enums\GuidanceType;
use App\Enums\NotificationCategory;
use App\Models\GuidanceSession;
use App\Models\Student;
use App\Models\ThesisProposal;
use App\Models\User;
use App\Notifications\ThesisNotification;
use Database\Seeders\DemoThesisSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    seedRoles();
});

/**
 * Notifications list newest-first, so tests that care about order freeze the
 * clock while writing rather than relying on ties between equal timestamps.
 */
function notify(
    User $user,
    NotificationCategory $category = NotificationCategory::Approval,
    int $minutesAgo = 0
): void {
    Date::setTestNow(Date::now()->subMinutes($minutesAgo));

    $user->notify(new ThesisNotification($category, 'Judul notifikasi', 'Isi notifikasi.'));

    Date::setTestNow();
}

it('shares the unread count and a preview with every page', function (): void {
    $student = makeStudent();
    notify($student->user, NotificationCategory::Approval, 10);
    notify($student->user, NotificationCategory::Reminder, 1);

    $this->actingAs($student->user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('notificationFeed.unread', 2)
            ->has('notificationFeed.recent', 2)
            ->where('notificationFeed.recent.0.category_label', 'Pengingat')
            ->where('notificationFeed.recent.0.tone', 'warning')
            ->where('notificationFeed.recent.0.unread', true));
});

it('lists every notification on the Notifikasi page', function (): void {
    $student = makeStudent();

    for ($i = 0; $i < 7; $i++) {
        notify($student->user, NotificationCategory::Approval, $i);
    }

    // The page's own list and the bell's preview travel as separate props, so
    // neither overwrites the other.
    $this->actingAs($student->user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('notifications', 7)
            ->has('notificationFeed.recent', 5)
            ->where('notificationFeed.unread', 7));
});

it('marks a single notification read', function (): void {
    $student = makeStudent();
    notify($student->user);
    $notification = $student->user->notifications()->sole();

    $this->actingAs($student->user)
        ->post(route('notifications.read', $notification->id))
        ->assertRedirect();

    expect($notification->refresh()->read_at)->not->toBeNull()
        ->and($student->user->unreadNotifications()->count())->toBe(0);
});

it('marks every notification read at once', function (): void {
    $student = makeStudent();
    notify($student->user);
    notify($student->user);

    $this->actingAs($student->user)
        ->post(route('notifications.read-all'))
        ->assertRedirect();

    expect($student->user->unreadNotifications()->count())->toBe(0);
});

it('refuses to mark another user\'s notification read', function (): void {
    $owner = makeStudent();
    $stranger = makeStudent();
    notify($owner->user);
    $notification = $owner->user->notifications()->sole();

    $this->actingAs($stranger->user)
        ->post(route('notifications.read', $notification->id))
        ->assertNotFound();

    expect($notification->refresh()->read_at)->toBeNull();
});

it('shares nothing for a signed-out visitor', function (): void {
    $this->get('/halaman-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->where('notificationFeed', null));
});

it('tells the supervisors when a student submits a proposal', function (): void {
    $student = makeStudent();
    $supervisor = assignSupervisor($student);

    $this->actingAs($student->user)->post(route('proposals.store'), [
        'title' => 'Judul Proposal',
        'background' => str_repeat('Konteks penelitian. ', 5),
        'problem_formulation' => 'Rumusan masalah yang memadai.',
        'objectives' => 'Tujuan penelitian yang memadai.',
        'document' => UploadedFile::fake()->create('p.pdf', 100, 'application/pdf'),
    ]);

    $notification = $supervisor->user->notifications()->sole();

    expect($notification->data['category'])->toBe('document')
        ->and($notification->data['title'])->toBe('Proposal baru diajukan')
        ->and($student->user->notifications()->count())->toBe(0);
});

it('tells the student when a supervisor completes a session', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();
    $supervisor = assignSupervisor($student);
    $session = GuidanceSession::factory()->for($student)->create();

    $this->actingAs($supervisor->user)
        ->post(route('guidance-sessions.complete', $session), ['completion_notes' => 'Selesai.']);

    $notification = $student->user->notifications()->sole();

    expect($notification->data['category'])->toBe('approval')
        ->and($notification->data['title'])->toBe('Sesi bimbingan ditandai selesai')
        ->and($supervisor->user->notifications()->count())->toBe(0);
});

it('tells the counterpart about a new guidance message', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();
    $supervisor = assignSupervisor($student);
    $session = GuidanceSession::factory()->for($student)->async()->create();

    // Student speaks: the supervisor hears about it, not the student.
    $this->actingAs($student->user)
        ->post(route('guidance-sessions.messages.store', $session), ['message' => 'Halo Pak.']);

    expect($supervisor->user->notifications()->count())->toBe(1)
        ->and($student->user->notifications()->count())->toBe(0);

    // Supervisor replies: now the student hears about it.
    $this->actingAs($supervisor->user)
        ->post(route('guidance-sessions.messages.store', $session), ['message' => 'Baik.']);

    expect($student->user->notifications()->sole()->data['title'])->toBe('Pesan bimbingan baru');
});

it('does not notify the supervisor about a session they created themselves', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();
    $supervisor = assignSupervisor($student);

    $this->actingAs($student->user)->post(route('guidance-sessions.store'), [
        'title' => 'Diskusi Bab 1',
        'stage' => GuidanceStage::Proposal->value,
        'target_type' => 'single',
        'guidance_type' => GuidanceType::OnlineAsync->value,
    ]);

    expect($supervisor->user->notifications()->count())->toBe(1)
        ->and($student->user->notifications()->count())->toBe(0);
});

it('seeds a demo feed covering every category', function (): void {
    app(DemoThesisSeeder::class)->run();

    $rani = Student::query()->where('student_number', '21051234')->sole();
    $categories = $rani->user->notifications()->get()
        ->map(fn ($notification): string => $notification->data['category'])
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($categories)->toBe(['approval', 'document', 'reminder', 'schedule'])
        ->and($rani->user->unreadNotifications()->count())->toBe(3);
});
