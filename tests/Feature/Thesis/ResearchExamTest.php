<?php

use App\Enums\ApprovalStatus;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\RubricStatus;
use App\Models\DepartmentRubric;
use App\Models\ExamEvaluation;
use App\Models\ProposalExam;
use App\Models\ResearchExam;
use App\Models\ResearchExamRequest;
use App\Models\Rubric;
use App\Models\Student;
use App\Models\ThesisProposal;

beforeEach(function (): void {
    seedRoles();
});

/**
 * A student with an approved proposal and two supervisors — the panel for every
 * exam below.
 */
function studentWithResearchPanel(): Student
{
    $student = makeStudent();
    assignSupervisor($student, order: 1);
    assignSupervisor($student, order: 2);
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    return $student;
}

it('lists both exam stages with their own counts', function (): void {
    $student = studentWithResearchPanel();
    ProposalExam::factory()->for($student)->create();
    ResearchExam::factory()->for($student)->create();

    $this->actingAs($student->supervisors->first()->user)
        ->get(route('exams.index'))
        ->assertInertia(fn ($page) => $page
            ->where('stage', ExamStage::Proposal->value)
            ->has('exams', 1)
            ->where('exams.0.stage', 'proposal')
            ->where('stages.0.count', 1)
            ->where('stages.1.count', 1));
});

it('shows only the requested stage', function (): void {
    $student = studentWithResearchPanel();
    ProposalExam::factory()->for($student)->create();
    $research = ResearchExam::factory()->for($student)->create(['title' => 'Ujian Hasil Dimas']);

    $this->actingAs($student->supervisors->first()->user)
        ->get(route('exams.index', ['stage' => ExamStage::Research]))
        ->assertInertia(fn ($page) => $page
            ->where('stage', 'hasil')
            ->has('exams', 1)
            ->where('exams.0.title', $research->title)
            ->where('exams.0.stage_label', 'Ujian Hasil'));
});

it('opens on the stage that has exams when the proposal tab is empty', function (): void {
    $student = studentWithResearchPanel();
    ResearchExam::factory()->for($student)->create();

    $this->actingAs($student->supervisors->first()->user)
        ->get(route('exams.index'))
        ->assertInertia(fn ($page) => $page->where('stage', 'hasil')->has('exams', 1));
});

it('records a research exam verdict against the research exam', function (): void {
    $student = studentWithResearchPanel();
    $supervisor = $student->supervisors->first();
    $exam = ResearchExam::factory()->for($student)->create();

    $rubric = assignRubric($student->department, ExamStage::Research);

    $this->actingAs($supervisor->user)
        ->post(route('exams.evaluations.store', ['stage' => ExamStage::Research, 'exam' => $exam]), [
            'scores' => marksOf($rubric, 85),
            'result' => ExamResult::Passed->value,
        ])
        ->assertRedirect(route('exams.show', ['stage' => ExamStage::Research, 'exam' => $exam]));

    $evaluation = ExamEvaluation::sole();

    expect($evaluation->exam_type)->toBe('research_exam')
        ->and($evaluation->exam_id)->toBe($exam->id)
        ->and($evaluation->exam)->toBeInstanceOf(ResearchExam::class)
        ->and((float) $evaluation->final_score)->toBe(85.0)
        ->and($evaluation->scores)->toHaveCount($rubric->criteria->count());
});

it('settles a research exam once the whole panel has evaluated', function (): void {
    $student = studentWithResearchPanel();
    [$first, $second] = $student->supervisors->all();
    $exam = ResearchExam::factory()->for($student)->create();

    $rubric = assignRubric($student->department, ExamStage::Research);
    $route = route('exams.evaluations.store', ['stage' => ExamStage::Research, 'exam' => $exam]);

    $this->actingAs($first->user)->post($route, [
        'scores' => marksOf($rubric, 85),
        'result' => ExamResult::Passed->value,
    ]);

    expect($exam->refresh()->result)->toBeNull();

    $this->actingAs($second->user)->post($route, [
        'scores' => marksOf($rubric, 75),
        'result' => ExamResult::ConditionalPass->value,
    ]);

    $exam->refresh();

    expect((float) $exam->final_score)->toBe(80.0)
        ->and($exam->result)->toBe(ExamResult::ConditionalPass)
        ->and($exam->status)->toBe(ExamStatus::Scored);
});

it('keeps the two stages\' verdicts apart', function (): void {
    $student = studentWithResearchPanel();
    $supervisor = $student->supervisors->first();
    $proposalExam = ProposalExam::factory()->for($student)->create();
    $researchExam = ResearchExam::factory()->for($student)->create();

    $proposalRubric = assignRubric($student->department);
    assignRubric($student->department, ExamStage::Research);

    $this->actingAs($supervisor->user)
        ->post(route('exams.evaluations.store', ['stage' => ExamStage::Proposal, 'exam' => $proposalExam]), [
            'scores' => marksOf($proposalRubric, 80),
            'result' => ExamResult::Passed->value,
        ]);

    // Evaluating one stage must not count as having evaluated the other.
    $this->actingAs($supervisor->user)
        ->get(route('exams.evaluate', ['stage' => ExamStage::Research, 'exam' => $researchExam]))
        ->assertOk();

    expect($proposalExam->evaluations()->count())->toBe(1)
        ->and($researchExam->evaluations()->count())->toBe(0);
});

it('marks a research exam against the program studi\'s rubric for that stage', function (): void {
    $student = studentWithResearchPanel();
    $exam = ResearchExam::factory()->for($student)->create();
    $rubric = assignRubric($student->department, ExamStage::Research, threshold: 70);

    $this->actingAs($student->supervisors->first()->user)
        ->get(route('exams.evaluate', ['stage' => ExamStage::Research, 'exam' => $exam]))
        ->assertInertia(fn ($page) => $page
            ->where('rubric.name', $rubric->name)
            ->where('rubric.pass_threshold', 70)
            ->has('rubric.criteria', 4)
            ->where('rubric.criteria.0.key', 'kualitas_proposal')
            ->where('rubric.criteria.0.weight', 30));
});

it('refuses to open the evaluation form when no rubric is in force', function (): void {
    $student = studentWithResearchPanel();
    $exam = ResearchExam::factory()->for($student)->create();

    $this->actingAs($student->supervisors->first()->user)
        ->get(route('exams.evaluate', ['stage' => ExamStage::Research, 'exam' => $exam]))
        ->assertForbidden();
});

it('fails an exam whose weighted score misses the threshold', function (): void {
    $student = studentWithResearchPanel();
    [$first, $second] = $student->supervisors->all();
    $exam = ResearchExam::factory()->for($student)->create();
    $rubric = assignRubric($student->department, ExamStage::Research, threshold: 70);

    $route = route('exams.evaluations.store', ['stage' => ExamStage::Research, 'exam' => $exam]);

    // Both panel members say Lulus, but the marks do not reach the bar.
    foreach ([$first, $second] as $member) {
        $this->actingAs($member->user)->post($route, [
            'scores' => marksOf($rubric, 60),
            'result' => ExamResult::Passed->value,
        ]);
    }

    expect($exam->refresh()->result)->toBe(ExamResult::Failed)
        ->and((float) $exam->final_score)->toBe(60.0);
});

it('does not resolve an exam id belonging to the other stage', function (): void {
    $student = studentWithResearchPanel();
    $proposalExam = ProposalExam::factory()->for($student)->create();

    ResearchExam::query()->delete();

    $this->actingAs($student->supervisors->first()->user)
        ->get(route('exams.show', ['stage' => ExamStage::Research, 'exam' => $proposalExam->id]))
        ->assertNotFound();
});

it('lets a supervisor verify a research exam request', function (): void {
    $student = studentWithResearchPanel();
    $supervisor = $student->supervisors->first();
    $request = ResearchExamRequest::factory()
        ->for($student)
        ->for($student->activeProposal())
        ->create();

    $this->actingAs($supervisor->user)
        ->post(route('exam-verifications.verify', [
            'stage' => ExamStage::Research,
            'examRequest' => $request,
        ]), ['decision' => 'approved', 'supervisor_notes' => 'Hasil penelitian layak diuji.'])
        ->assertRedirect(route('exam-verifications.index', ['stage' => ExamStage::Research]));

    expect($request->refresh()->supervisor_status)->toBe(ApprovalStatus::Approved)
        ->and($request->supervisor_verified_by)->toBe($supervisor->user_id);
});

it('shows research exam requests under their own verification tab', function (): void {
    $student = studentWithResearchPanel();
    $request = ResearchExamRequest::factory()
        ->for($student)
        ->for($student->activeProposal())
        ->create();

    $this->actingAs($student->supervisors->first()->user)
        ->get(route('exam-verifications.index', ['stage' => ExamStage::Research]))
        ->assertInertia(fn ($page) => $page
            ->where('stage', 'hasil')
            ->has('requests', 1)
            ->where('requests.0.id', $request->id)
            ->where('requests.0.stage_label', 'Ujian Hasil'));
});

it('counts the research guidance quota on a research verification', function (): void {
    $student = studentWithResearchPanel();
    $request = ResearchExamRequest::factory()
        ->for($student)
        ->for($student->activeProposal())
        ->create();

    $required = $student->department->activeThesisSetting()->min_research_guidance_sessions;

    $this->actingAs($student->supervisors->first()->user)
        ->get(route('exam-verifications.show', [
            'stage' => ExamStage::Research,
            'examRequest' => $request,
        ]))
        ->assertInertia(fn ($page) => $page->where('request.required_sessions', $required));
});

it('normalises each criterion against its own scale', function (): void {
    $student = studentWithResearchPanel();
    [$first, $second] = $student->supervisors->all();
    $exam = ResearchExam::factory()->for($student)->create();

    // Two criteria, equal weight, different scales.
    $rubric = Rubric::factory()->for($student->department)->create([
        'status' => RubricStatus::Published,
        'published_at' => now(),
    ]);
    $rubric->criteria()->create([
        'label' => 'Naskah', 'key' => 'naskah', 'weight' => 50, 'max_score' => 100, 'order' => 1,
    ]);
    $rubric->criteria()->create([
        'label' => 'Presentasi', 'key' => 'presentasi', 'weight' => 50, 'max_score' => 5, 'order' => 2,
    ]);
    DepartmentRubric::factory()->create([
        'department_id' => $student->department_id,
        'exam_stage' => ExamStage::Research,
        'rubric_id' => $rubric->id,
        'pass_threshold' => 70,
    ]);
    $rubric->load('criteria');

    $route = route('exams.evaluations.store', ['stage' => ExamStage::Research, 'exam' => $exam]);

    // 80 out of 100 would be fine, but presentation is marked out of 5.
    $this->actingAs($first->user)
        ->post($route, [
            'scores' => ['naskah' => 80, 'presentasi' => 80],
            'result' => ExamResult::Passed->value,
        ])
        ->assertSessionHasErrors('scores.presentasi');

    foreach ([$first, $second] as $member) {
        $this->actingAs($member->user)->post($route, [
            // 80% and 4/5 = 80% — the same standard on two scales.
            'scores' => ['naskah' => 80, 'presentasi' => 4],
            'result' => ExamResult::Passed->value,
        ]);
    }

    $exam->refresh();

    expect((float) $exam->final_score)->toBe(80.0)
        ->and($exam->result)->toBe(ExamResult::Passed)
        // The scale travels onto each mark, so a later edit cannot re-read it.
        ->and(ExamEvaluation::first()->scores->pluck('max_score', 'criterion_key')->all())
        ->toBe(['naskah' => 100, 'presentasi' => 5]);
});

it('reports an out-of-range score alongside other errors, not after them', function (): void {
    $student = studentWithResearchPanel();
    $exam = ResearchExam::factory()->for($student)->create();
    $rubric = assignRubric($student->department, ExamStage::Research);

    $this->actingAs($student->supervisors->first()->user)
        ->post(route('exams.evaluations.store', ['stage' => ExamStage::Research, 'exam' => $exam]), [
            'scores' => marksOf($rubric, 140),
            'result' => 'juara',
        ])
        ->assertSessionHasErrors(['scores.kualitas_proposal', 'result']);
});
