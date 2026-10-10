<?php

use App\Enums\ApprovalStatus;
use App\Models\GuidanceSession;
use App\Models\ProposalExamRequest;
use App\Models\ResearchExamRequest;
use App\Models\ResearchLogbook;
use App\Models\ThesisProposal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRoles();
    Storage::fake('documents');
});

it('reports a student as ineligible until the proposal guidance quota is met', function (): void {
    $department = makeDepartment(['min_proposal_guidance_sessions' => 4]);
    $student = makeStudent($department);
    ThesisProposal::factory()->for($student)->for($department)->approved()->create();
    GuidanceSession::factory()->count(3)->for($student)->completed()->create();

    $this->actingAs($student->user)
        ->get(route('proposal-exam-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->where('eligibility.eligible', false)
            ->where('eligibility.checks.1.detail', '3 dari 4 bimbingan selesai.'));
});

it('reports a student as eligible once the quota and approval are in place', function (): void {
    $department = makeDepartment(['min_proposal_guidance_sessions' => 4]);
    $student = makeStudent($department);
    ThesisProposal::factory()->for($student)->for($department)->approved()->create();
    GuidanceSession::factory()->count(4)->for($student)->completed()->create();

    $this->actingAs($student->user)
        ->get(route('proposal-exam-requests.create'))
        ->assertInertia(fn ($page) => $page->where('eligibility.eligible', true));
});

it('refuses a proposal exam request from an ineligible student', function (): void {
    $department = makeDepartment(['min_proposal_guidance_sessions' => 4]);
    $student = makeStudent($department);
    ThesisProposal::factory()->for($student)->for($department)->approved()->create();

    $this->actingAs($student->user)
        ->post(route('proposal-exam-requests.store'), ['student_notes' => 'Mohon dijadwalkan.'])
        ->assertRedirect();

    expect(ProposalExamRequest::count())->toBe(0);
});

it('stores a proposal exam request with its supporting documents', function (): void {
    $department = makeDepartment(['min_proposal_guidance_sessions' => 1]);
    $student = makeStudent($department);
    ThesisProposal::factory()->for($student)->for($department)->approved()->create();
    GuidanceSession::factory()->for($student)->completed()->create();

    $this->actingAs($student->user)
        ->post(route('proposal-exam-requests.store'), [
            'student_notes' => 'Mohon dijadwalkan minggu depan.',
            'documents' => [
                ['name' => 'Bukti Lunas SPP', 'file' => UploadedFile::fake()->create('spp.pdf', 100, 'application/pdf')],
            ],
        ])
        ->assertRedirect(route('proposal-exam-requests.index'));

    $request = ProposalExamRequest::sole();

    expect($request->status)->toBe(ApprovalStatus::Pending)
        ->and($request->documents)->toHaveCount(1)
        ->and($request->documents[0]['name'])->toBe('Bukti Lunas SPP');

    Storage::disk('documents')->assertExists($request->documents[0]['path']);
});

it('allows only one outstanding proposal exam request', function (): void {
    $department = makeDepartment(['min_proposal_guidance_sessions' => 1]);
    $student = makeStudent($department);
    $proposal = ThesisProposal::factory()->for($student)->for($department)->approved()->create();
    ProposalExamRequest::factory()->for($student)->for($proposal)->create();

    $this->actingAs($student->user)
        ->get(route('proposal-exam-requests.create'))
        ->assertForbidden();
});

it('gates the research exam on logbook progress as well as guidance', function (): void {
    $department = makeDepartment([
        'min_research_guidance_sessions' => 2,
        'min_logbook_progress' => 100,
    ]);
    $student = makeStudent($department);
    ThesisProposal::factory()->for($student)->for($department)->approved()->create();
    ResearchLogbook::factory()->for($student)->create(['progress' => 62, 'log_date' => now()]);
    GuidanceSession::factory()->for($student)->research()->completed()->create();

    $this->actingAs($student->user)
        ->get(route('research-exam-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->where('eligibility.eligible', false)
            ->where('eligibility.checks', fn (Collection $checks) => $checks
                ->keyBy('key')
                ->only(['proposal_exam', 'logbook_progress', 'guidance_quota'])
                ->map(fn (array $check): string => $check['detail'])
                ->all() === [
                    'proposal_exam' => 'Belum lulus ujian proposal.',
                    'logbook_progress' => '62% / 100%',
                    'guidance_quota' => '1 / 2',
                ]));
});

it('accepts a research exam request once both requirements are met', function (): void {
    $department = makeDepartment([
        'min_research_guidance_sessions' => 2,
        'min_logbook_progress' => 100,
    ]);
    $student = makeStudent($department);
    ThesisProposal::factory()->for($student)->for($department)->approved()->create();
    passProposalExam($student);
    ResearchLogbook::factory()->for($student)->create(['progress' => 100, 'log_date' => now()]);
    GuidanceSession::factory()->count(2)->for($student)->research()->completed()->create();

    $this->actingAs($student->user)
        ->post(route('research-exam-requests.store'), ['student_notes' => 'Siap sidang.'])
        ->assertRedirect(route('research-exam-requests.index'));

    expect(ResearchExamRequest::count())->toBe(1);
});

it('settles the overall status only once both gates have answered', function (): void {
    $request = ProposalExamRequest::factory()->create();

    $request->supervisor_status = ApprovalStatus::Approved;
    $request->syncOverallStatus();
    expect($request->status)->toBe(ApprovalStatus::Pending)
        ->and($request->awaitingLabel())->toBe('Menunggu verifikasi admin prodi');

    $request->admin_status = ApprovalStatus::Approved;
    $request->syncOverallStatus();
    expect($request->status)->toBe(ApprovalStatus::Approved);
});

it('settles as rejected as soon as either gate rejects', function (): void {
    $request = ProposalExamRequest::factory()->create();

    $request->supervisor_status = ApprovalStatus::Rejected;
    $request->syncOverallStatus();

    expect($request->status)->toBe(ApprovalStatus::Rejected);
});

it('drops the proposal exam check where the department requires no exam', function (): void {
    $department = makeDepartment(['requires_proposal_exam' => false]);
    $student = makeStudent($department);
    ThesisProposal::factory()->for($student)->for($department)->approved()->create();

    $this->actingAs($student->user)
        ->get(route('research-exam-requests.create'))
        ->assertInertia(fn ($page) => $page
            ->has('eligibility.checks', 2)
            ->where('eligibility.checks', fn (Collection $checks) => ! $checks
                ->pluck('key')
                ->contains('proposal_exam')));
});

it('refuses proposal exam requests where the department requires no exam', function (): void {
    $department = makeDepartment(['requires_proposal_exam' => false]);
    $student = makeStudent($department);
    ThesisProposal::factory()->for($student)->for($department)->approved()->create();

    $this->actingAs($student->user)->get(route('proposal-exam-requests.index'))->assertForbidden();
    $this->actingAs($student->user)->get(route('proposal-exam-requests.create'))->assertForbidden();
    $this->actingAs($student->user)
        ->post(route('proposal-exam-requests.store'), ['student_notes' => 'Mohon dijadwalkan.'])
        ->assertForbidden();
});
