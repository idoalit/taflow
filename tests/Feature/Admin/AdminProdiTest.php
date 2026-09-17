<?php

use App\Enums\ApprovalStatus;
use App\Enums\ExamParticipantRole;
use App\Enums\ExamStage;
use App\Enums\PerceptionAlignmentMode;
use App\Enums\RubricStatus;
use App\Enums\SupervisorRole;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\DepartmentRubric;
use App\Models\ProposalExam;
use App\Models\ProposalExamRequest;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\Student;
use App\Models\ThesisProposal;
use App\Models\User;
use App\Support\Workspace;

beforeEach(function (): void {
    seedRoles();
});

/**
 * An admin already sitting in their own program studi's workspace, which is
 * what every screen below reads its scope from.
 *
 * @return array{0: User, 1: Department}
 */
function adminInWorkspace(?Department $department = null): array
{
    $department ??= makeDepartment();
    $admin = makeAdmin($department);
    $admin->useWorkspace(new Workspace(UserRole::Admin, $department));

    return [$admin, $department];
}

it('lists only the students of the program studi in the active workspace', function (): void {
    [$admin, $department] = adminInWorkspace();
    $mine = makeStudent($department);
    $theirs = makeStudent();

    $this->actingAs($admin)
        ->get(route('admin.students.index'))
        ->assertInertia(fn ($page) => $page
            ->has('students', 1)
            ->where('students.0.name', $mine->user->name)
            ->where('department', $department->name));

    expect($theirs->department_id)->not->toBe($department->id);
});

it('refuses the admin screens from a dosen workspace', function (): void {
    $student = makeStudent();
    $lecturer = assignSupervisor($student);

    $this->actingAs($lecturer->user)
        ->get(route('admin.students.index'))
        ->assertForbidden();
});

it('assigns supervisors and examiners to a student', function (): void {
    [$admin, $department] = adminInWorkspace();
    $student = makeStudent($department);
    $first = makeLecturer();
    $second = makeLecturer();
    foreach ([$first, $second] as $lecturer) {
        $lecturer->departments()->attach($department);
    }

    $this->actingAs($admin)
        ->put(route('admin.students.supervisors.update', $student), [
            'assignments' => [
                ['lecturer_id' => $first->id, 'role' => SupervisorRole::Supervisor->value],
                ['lecturer_id' => $second->id, 'role' => SupervisorRole::Examiner->value],
            ],
        ])
        ->assertRedirect();

    expect($student->supervisorAssignments()->count())->toBe(2)
        ->and($student->supervisorAssignments()->where('role', SupervisorRole::Examiner)->exists())
        ->toBeTrue();
});

it('enforces the program studi supervisor limit', function (): void {
    [$admin, $department] = adminInWorkspace();
    $department->activeThesisSetting()->update(['max_supervisors_per_student' => 1]);
    $student = makeStudent($department);
    $lecturers = collect([makeLecturer(), makeLecturer()])
        ->each(fn ($lecturer) => $lecturer->departments()->attach($department));

    $this->actingAs($admin)
        ->put(route('admin.students.supervisors.update', $student), [
            'assignments' => $lecturers
                ->map(fn ($lecturer): array => [
                    'lecturer_id' => $lecturer->id,
                    'role' => SupervisorRole::Supervisor->value,
                ])->all(),
        ])
        ->assertStatus(422);

    expect($student->supervisorAssignments()->count())->toBe(0);
});

it('refuses to assign a dosen from another program studi', function (): void {
    [$admin, $department] = adminInWorkspace();
    $student = makeStudent($department);
    $outsider = makeLecturer();

    $this->actingAs($admin)
        ->put(route('admin.students.supervisors.update', $student), [
            'assignments' => [
                ['lecturer_id' => $outsider->id, 'role' => SupervisorRole::Supervisor->value],
            ],
        ])
        ->assertSessionHasErrors('assignments.0.lecturer_id');
});

it('saves the thesis settings for the active program studi only', function (): void {
    [$admin, $department] = adminInWorkspace();
    $other = makeDepartment();

    $this->actingAs($admin)
        ->put(route('admin.thesis-settings.update'), [
            'requires_proposal_exam' => false,
            'min_proposal_guidance_sessions' => 6,
            'min_research_guidance_sessions' => 3,
            'min_logbook_progress' => 80,
            'max_supervisors_per_student' => 2,
            'guidance_slot_minutes' => 45,
            'perception_alignment_mode' => PerceptionAlignmentMode::Examiner->value,
        ])
        ->assertRedirect();

    $setting = $department->activeThesisSetting()->fresh();

    expect($setting->requires_proposal_exam)->toBeFalse()
        ->and($setting->min_proposal_guidance_sessions)->toBe(6)
        ->and($setting->perception_alignment_mode)->toBe(PerceptionAlignmentMode::Examiner)
        ->and($other->activeThesisSetting()->min_proposal_guidance_sessions)->toBe(4);
});

it('opens the admin gate only after the supervisor has approved', function (): void {
    [$admin, $department] = adminInWorkspace();
    $student = makeStudent($department);
    $proposal = ThesisProposal::factory()->for($student)->for($department)->approved()->create();

    $request = ProposalExamRequest::factory()->for($student)->for($proposal)->create();

    $this->actingAs($admin)
        ->post(route('admin.exam-requests.verify', [
            'stage' => ExamStage::Proposal,
            'examRequest' => $request,
        ]), ['decision' => 'approved'])
        ->assertForbidden();

    $request->update([
        'supervisor_status' => ApprovalStatus::Approved,
        'supervisor_verified_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.exam-requests.verify', [
            'stage' => ExamStage::Proposal,
            'examRequest' => $request,
        ]), ['decision' => 'approved'])
        ->assertRedirect();

    expect($request->refresh()->status)->toBe(ApprovalStatus::Approved);
});

it('schedules an exam from an approved request and notifies the student', function (): void {
    [$admin, $department] = adminInWorkspace();
    $student = makeStudent($department);
    $supervisor = assignSupervisor($student);
    $examiner = makeLecturer();
    $secondExaminer = makeLecturer();
    foreach ([$examiner, $secondExaminer] as $lecturer) {
        $lecturer->departments()->attach($department);
    }
    $proposal = ThesisProposal::factory()->for($student)->for($department)->approved()->create();
    $rubric = assignRubric($department);

    $request = ProposalExamRequest::factory()->for($student)->for($proposal)->create([
        'status' => ApprovalStatus::Approved,
        'supervisor_status' => ApprovalStatus::Approved,
        'admin_status' => ApprovalStatus::Approved,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.exams.store', ['stage' => ExamStage::Proposal, 'examRequest' => $request]), [
            'title' => 'Ujian Proposal — Uji',
            'scheduled_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'venue' => 'Ruang Sidang 1',
            'panel' => [
                ['lecturer_id' => $examiner->id, 'role' => ExamParticipantRole::ChairExaminer->value],
                ['lecturer_id' => $secondExaminer->id, 'role' => ExamParticipantRole::MemberExaminer->value],
                ['lecturer_id' => $supervisor->id, 'role' => ExamParticipantRole::Supervisor->value],
            ],
        ])
        ->assertRedirect();

    $exam = ProposalExam::sole();

    expect($exam->student_id)->toBe($student->id)
        ->and($exam->rubric_id)->toBe($rubric->id)
        ->and($exam->pass_threshold)->toBe(65)
        ->and($request->refresh()->proposal_exam_id)->toBe($exam->id)
        // The examiner named on the panel joins the student's roster, which is
        // what lets them open the exam at all.
        ->and($student->supervisorAssignments()->where('lecturer_id', $examiner->id)->exists())->toBeTrue()
        ->and($student->user->notifications()->count())->toBe(1);
});

it('refuses to schedule a request that both gates have not approved', function (): void {
    [$admin, $department] = adminInWorkspace();
    $student = makeStudent($department);
    $proposal = ThesisProposal::factory()->for($student)->for($department)->approved()->create();
    $request = ProposalExamRequest::factory()->for($student)->for($proposal)->create();

    $this->actingAs($admin)
        ->get(route('admin.exams.create', ['stage' => ExamStage::Proposal, 'examRequest' => $request]))
        ->assertForbidden();
});

it('publishes a rubric only when its weights total 100', function (): void {
    [$admin] = adminInWorkspace();

    $this->actingAs($admin)
        ->post(route('admin.rubrics.store'), [
            'name' => 'Rubrik Uji',
            'scoring_scheme' => 'weighted',
            'criteria' => [
                ['label' => 'Metodologi', 'weight' => 40, 'max_score' => 100],
                ['label' => 'Presentasi', 'weight' => 40, 'max_score' => 100],
            ],
        ])
        ->assertSessionHasErrors('criteria');

    $this->actingAs($admin)
        ->post(route('admin.rubrics.store'), [
            'name' => 'Rubrik Uji',
            'scoring_scheme' => 'weighted',
            'criteria' => [
                ['label' => 'Metodologi', 'weight' => 60, 'max_score' => 100],
                ['label' => 'Presentasi', 'weight' => 40, 'max_score' => 100],
            ],
        ])
        ->assertRedirect();

    $rubric = Rubric::sole();

    expect($rubric->status)->toBe(RubricStatus::Draft);

    $this->actingAs($admin)
        ->post(route('admin.rubrics.publish', $rubric))
        ->assertRedirect();

    expect($rubric->refresh()->isPublished())->toBeTrue();
});

it('retires the previous rubric assignment when a new one is made', function (): void {
    [$admin, $department] = adminInWorkspace();
    assignRubric($department);
    $replacement = Rubric::factory()->for($department)->published()->create();

    $this->actingAs($admin)
        ->post(route('admin.rubrics.assign'), [
            'exam_stage' => ExamStage::Proposal->value,
            'rubric_id' => $replacement->id,
            'pass_threshold' => 75,
        ])
        ->assertRedirect();

    $active = DepartmentRubric::activeFor($department, ExamStage::Proposal);

    expect($active->rubric_id)->toBe($replacement->id)
        ->and($active->pass_threshold)->toBe(75)
        ->and(DepartmentRubric::where('department_id', $department->id)
            ->where('exam_stage', ExamStage::Proposal)
            ->where('is_active', false)
            ->count())->toBe(1);
});

it('refuses to assign a rubric that is still a draft', function (): void {
    [$admin, $department] = adminInWorkspace();
    $draft = Rubric::factory()->for($department)->create();

    $this->actingAs($admin)
        ->post(route('admin.rubrics.assign'), [
            'exam_stage' => ExamStage::Proposal->value,
            'rubric_id' => $draft->id,
            'pass_threshold' => 65,
        ])
        ->assertSessionHasErrors('rubric_id');
});

it('counts the program studi\'s own figures on the report', function (): void {
    [$admin, $department] = adminInWorkspace();
    makeStudent($department);
    makeStudent($department);
    makeStudent();

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('students.total', 2)
            ->where('students.without_supervisor', 2));
});

it('shows the admin dashboard with the alerts that matter', function (): void {
    [$admin, $department] = adminInWorkspace();
    makeStudent($department);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/dashboard')
            ->where('department', $department->name)
            ->has('kpis', 6)
            ->where('alerts.0.text', '1 mahasiswa belum memiliki dosen pembimbing'));
});

it('keeps an admin of two program studi from seeing the other one', function (): void {
    $first = makeDepartment();
    $second = makeDepartment();
    $admin = makeAdmin($first);
    $admin->departments()->attach($second);

    makeStudent($first);
    makeStudent($second);

    $this->actingAs($admin)
        ->withSession(inWorkspace(UserRole::Admin, $second))
        ->get(route('admin.students.index'))
        ->assertInertia(fn ($page) => $page
            ->has('students', 1)
            ->where('department', $second->name));
});

it('refuses to seat the same dosen twice for one student', function (): void {
    [$admin, $department] = adminInWorkspace();
    $student = makeStudent($department);
    $lecturer = makeLecturer();
    $lecturer->departments()->attach($department);

    $this->actingAs($admin)
        ->put(route('admin.students.supervisors.update', $student), [
            'assignments' => [
                ['lecturer_id' => $lecturer->id, 'role' => SupervisorRole::Supervisor->value],
                ['lecturer_id' => $lecturer->id, 'role' => SupervisorRole::Examiner->value],
            ],
        ])
        ->assertStatus(422);

    expect($student->supervisorAssignments()->count())->toBe(0);
});

it('counts a dosen once on the panel even if the data says twice', function (): void {
    $student = makeStudent();
    $lecturer = assignSupervisor($student);

    // A row that predates the guard above.
    $student->supervisorAssignments()->create([
        'lecturer_id' => $lecturer->id,
        'role' => SupervisorRole::Examiner,
        'order' => 2,
    ]);

    $exam = ProposalExam::factory()->for($student)->create();

    expect($student->supervisorAssignments()->count())->toBe(2)
        ->and($exam->panel())->toHaveCount(1);
});

it('moves a pembimbing rather than seating them twice when scheduling', function (): void {
    [$admin, $department] = adminInWorkspace();
    $student = makeStudent($department);
    $supervisor = assignSupervisor($student);
    $other = makeLecturer();
    $other->departments()->attach($department);
    $proposal = ThesisProposal::factory()->for($student)->for($department)->approved()->create();
    assignRubric($department);

    $request = ProposalExamRequest::factory()->for($student)->for($proposal)->create([
        'status' => ApprovalStatus::Approved,
        'supervisor_status' => ApprovalStatus::Approved,
        'admin_status' => ApprovalStatus::Approved,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.exams.store', ['stage' => ExamStage::Proposal, 'examRequest' => $request]), [
            'title' => 'Ujian Proposal — Uji',
            'scheduled_at' => now()->addWeek()->format('Y-m-d\TH:i'),
            'duration_minutes' => 60,
            'panel' => [
                ['lecturer_id' => $supervisor->id, 'role' => ExamParticipantRole::ChairExaminer->value],
                ['lecturer_id' => $other->id, 'role' => ExamParticipantRole::MemberExaminer->value],
            ],
        ])
        ->assertRedirect();

    $assignment = $student->supervisorAssignments()->where('lecturer_id', $supervisor->id)->sole();

    expect($student->supervisorAssignments()->where('lecturer_id', $supervisor->id)->count())->toBe(1)
        // Still their pembimbing on the roster: chairing one sitting does not
        // change the supervisory relationship.
        ->and($assignment->role)->toBe(SupervisorRole::Supervisor)
        // The sitting's own panel is what the exam reads, chair first.
        ->and(ProposalExam::sole()->panel())->toHaveCount(2)
        ->and(ProposalExam::sole()->panel()->first()->lecturerId)->toBe($supervisor->id)
        ->and(ProposalExam::sole()->panel()->first()->label)->toBe('Ketua Penguji');
});

it('derives criterion keys from their labels', function (): void {
    [$admin] = adminInWorkspace();

    $this->actingAs($admin)
        ->post(route('admin.rubrics.store'), [
            'name' => 'Rubrik Kunci',
            'scoring_scheme' => 'weighted',
            'criteria' => [
                // Whatever a client sends as a key is ignored.
                ['label' => 'Penguasaan Materi', 'key' => 'dikirim_klien', 'weight' => 50, 'max_score' => 100],
                ['label' => 'Kualitas & Kedalaman Analisis', 'weight' => 50, 'max_score' => 100],
            ],
        ])
        ->assertRedirect();

    expect(Rubric::sole()->criteria->pluck('key')->all())
        ->toBe(['penguasaan_materi', 'kualitas_kedalaman_analisis']);
});

it('keeps generated keys apart when two labels reduce to the same slug', function (): void {
    expect(RubricCriterion::keysFor(['Metodologi', 'Metodologi!', 'Metodologi?']))
        ->toBe(['metodologi', 'metodologi_2', 'metodologi_3'])
        // Nothing usable in the label still has to yield a valid key.
        ->and(RubricCriterion::keysFor(['???', 'Presentasi']))
        ->toBe(['kriteria_1', 'presentasi']);
});

it('refuses two criteria with the same label', function (): void {
    [$admin] = adminInWorkspace();

    $this->actingAs($admin)
        ->post(route('admin.rubrics.store'), [
            'name' => 'Rubrik Ganda',
            'scoring_scheme' => 'weighted',
            'criteria' => [
                ['label' => 'Metodologi', 'weight' => 50, 'max_score' => 100],
                ['label' => 'metodologi ', 'weight' => 50, 'max_score' => 100],
            ],
        ])
        ->assertSessionHasErrors('criteria');

    expect(Rubric::count())->toBe(0);
});

it('lets a draft rubric be edited', function (): void {
    [$admin, $department] = adminInWorkspace();
    $rubric = Rubric::factory()->for($department)->create();
    $rubric->criteria()->create(['label' => 'Lama', 'key' => 'lama', 'weight' => 100, 'order' => 1]);

    $this->actingAs($admin)
        ->put(route('admin.rubrics.update', $rubric), [
            'name' => 'Rubrik Direvisi',
            'scoring_scheme' => 'weighted',
            'criteria' => [
                ['label' => 'Metodologi', 'weight' => 60, 'max_score' => 100],
                ['label' => 'Presentasi', 'weight' => 40, 'max_score' => 5],
            ],
        ])
        ->assertRedirect(route('admin.rubrics.show', $rubric));

    $rubric->refresh()->load('criteria');

    expect($rubric->name)->toBe('Rubrik Direvisi')
        ->and($rubric->criteria->pluck('key')->all())->toBe(['metodologi', 'presentasi'])
        // Each criterion keeps its own scale.
        ->and($rubric->criteria->pluck('max_score')->all())->toBe([100, 5]);
});

it('refuses to edit a rubric once it is published', function (): void {
    [$admin, $department] = adminInWorkspace();
    $rubric = Rubric::factory()->for($department)->published()->create();

    $this->actingAs($admin)
        ->get(route('admin.rubrics.edit', $rubric))
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('admin.rubrics.update', $rubric), [
            'name' => 'Diam-diam diubah',
            'scoring_scheme' => 'weighted',
            'criteria' => [['label' => 'Metodologi', 'weight' => 100, 'max_score' => 100]],
        ])
        ->assertForbidden();

    expect($rubric->refresh()->name)->not->toBe('Diam-diam diubah');
});

it('offers the edit route only while a rubric is a draft', function (): void {
    [$admin, $department] = adminInWorkspace();
    $draft = Rubric::factory()->for($department)->create(['name' => 'Draf']);
    Rubric::factory()->for($department)->published()->create(['name' => 'Terbit']);

    $this->actingAs($admin)
        ->get(route('admin.rubrics.index'))
        ->assertInertia(fn ($page) => $page
            ->where('rubrics.0.name', 'Draf')
            ->where('rubrics.0.editable', true)
            ->where('rubrics.1.name', 'Terbit')
            ->where('rubrics.1.editable', false));

    expect($draft->isEditable())->toBeTrue();
});

it('keeps the pass threshold a percentage whatever the criteria scales are', function (): void {
    [$admin, $department] = adminInWorkspace();
    $rubric = Rubric::factory()->for($department)->published(5)->create();

    $this->actingAs($admin)
        ->post(route('admin.rubrics.assign'), [
            'exam_stage' => ExamStage::Proposal->value,
            'rubric_id' => $rubric->id,
            'pass_threshold' => 140,
        ])
        ->assertSessionHasErrors('pass_threshold');

    $this->actingAs($admin)
        ->post(route('admin.rubrics.assign'), [
            'exam_stage' => ExamStage::Proposal->value,
            'rubric_id' => $rubric->id,
            'pass_threshold' => 70,
        ])
        ->assertRedirect();

    expect(DepartmentRubric::activeFor($department, ExamStage::Proposal)->pass_threshold)->toBe(70);
});

it('deletes a draft rubric with its criteria', function (): void {
    [$admin, $department] = adminInWorkspace();
    $rubric = Rubric::factory()->for($department)->create();
    $rubric->criteria()->create([
        'label' => 'Metodologi', 'key' => 'metodologi', 'weight' => 100, 'max_score' => 100, 'order' => 1,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.rubrics.destroy', $rubric))
        ->assertRedirect(route('admin.rubrics.index'));

    expect(Rubric::count())->toBe(0)
        ->and(RubricCriterion::count())->toBe(0);
});

it('refuses to delete a published rubric', function (): void {
    [$admin, $department] = adminInWorkspace();
    $rubric = Rubric::factory()->for($department)->published()->create();

    $this->actingAs($admin)
        ->delete(route('admin.rubrics.destroy', $rubric))
        ->assertForbidden();

    expect(Rubric::count())->toBe(1);
});

it('shows each criterion with its own scale', function (): void {
    [$admin, $department] = adminInWorkspace();
    $rubric = Rubric::factory()->for($department)->create();
    $rubric->criteria()->create([
        'label' => 'Naskah', 'key' => 'naskah', 'weight' => 50, 'max_score' => 100, 'order' => 1,
    ]);
    $rubric->criteria()->create([
        'label' => 'Presentasi', 'key' => 'presentasi', 'weight' => 50, 'max_score' => 5, 'order' => 2,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.rubrics.show', $rubric))
        ->assertInertia(fn ($page) => $page
            ->where('rubric.criteria.0.max_score', 100)
            ->where('rubric.criteria.1.max_score', 5)
            ->where('rubric.scale_label', '0–5 s.d. 0–100'));
});
