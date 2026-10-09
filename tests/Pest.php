<?php

use App\Actions\Thesis\BuildExamPanel;
use App\Enums\ExamParticipantRole;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\SupervisorRole;
use App\Enums\UserRole;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\Department;
use App\Models\DepartmentRubric;
use App\Models\DepartmentThesisSetting;
use App\Models\Lecturer;
use App\Models\ProposalExam;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\Student;
use App\Models\User;
use App\Support\Workspace;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SupervisionIndicatorSeeder;
use Database\Seeders\ThesisExamRubricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Roles and permissions are a fixture of the application, not of any one test,
 * so the seeder runs once per test that needs them.
 */
function seedRoles(): void
{
    app(RolePermissionSeeder::class)->run();
}

function makeDepartment(array $settings = []): Department
{
    $department = Department::factory()->create();

    DepartmentThesisSetting::factory()->create([
        'department_id' => $department->id,
        ...$settings,
    ]);

    return $department;
}

/**
 * A student with a user account, the given role, and an optional supervisor.
 */
function makeStudent(?Department $department = null): Student
{
    $department ??= makeDepartment();

    $student = Student::factory()->for($department)->create();
    $student->user->syncRoles([UserRole::Student->value]);

    return $student;
}

function makeLecturer(): Lecturer
{
    $lecturer = Lecturer::factory()->create();
    $lecturer->user->syncRoles([UserRole::Lecturer->value]);

    return $lecturer;
}

/**
 * Supervision happens inside a program studi, so assigning a supervisor also
 * puts them in the student's prodi unless they are already a member of it.
 */
function assignSupervisor(Student $student, ?Lecturer $lecturer = null, int $order = 1): Lecturer
{
    $lecturer ??= makeLecturer();

    $lecturer->departments()->syncWithoutDetaching([
        $student->department_id => ['is_primary' => $lecturer->departments()->count() === 0],
    ]);

    $student->supervisorAssignments()->create([
        'lecturer_id' => $lecturer->id,
        'role' => SupervisorRole::Supervisor,
        'order' => $order,
    ]);

    return $lecturer;
}

function makeAdmin(Department $department): User
{
    $admin = User::factory()->create();
    $admin->syncRoles([UserRole::Admin->value]);
    $admin->departments()->attach($department);

    return $admin;
}

function makeSuperAdmin(): User
{
    $superadmin = User::factory()->create();
    $superadmin->syncRoles([UserRole::SuperAdmin->value]);

    return $superadmin;
}

/**
 * Give a student a decided ujian proposal — the gate that opens research-stage
 * guidance. Nothing in the application schedules or evaluates one yet.
 */
function passProposalExam(Student $student, ExamResult $result = ExamResult::Passed): ProposalExam
{
    return ProposalExam::factory()->for($student)->decided($result)->create();
}

/**
 * Give a program studi a published rubric for one exam stage. Without one, no
 * panel member can mark an exam of that stage.
 */
function assignRubric(Department $department, ExamStage $stage = ExamStage::Proposal, int $threshold = 65): Rubric
{
    $rubric = Rubric::factory()->for($department)->published()->create();

    DepartmentRubric::factory()->create([
        'department_id' => $department->id,
        'exam_stage' => $stage,
        'rubric_id' => $rubric->id,
        'pass_threshold' => $threshold,
    ]);

    return $rubric->load('criteria');
}

/**
 * A full set of marks for a rubric, every criterion given the same score.
 *
 * @return array<string, float>
 */
function marksOf(Rubric $rubric, float $score): array
{
    return $rubric->criteria->mapWithKeys(
        fn (RubricCriterion $criterion): array => [$criterion->key => $score],
    )->all();
}

/**
 * Put a test request in a specific workspace. Setting it on the model instance
 * is not enough: ResolveWorkspace re-reads the session on every request, which
 * is exactly what makes a revoked workspace stop working.
 *
 * @return array<string, string>
 */
function inWorkspace(UserRole $role, ?Department $department = null): array
{
    return [ResolveWorkspace::SESSION_KEY => (new Workspace($role, $department))->key()];
}

/**
 * A sitting with the faculty's own forms in force: the block rubric for the
 * examiners, the five indicators for the pembimbing.
 *
 * @return array{exam: ProposalExam, rubric: Rubric, supervisor: Lecturer, examiners: array<int, Lecturer>}
 */
function sittingWithForms(int $examinerCount = 2): array
{
    $department = makeDepartment();
    $student = makeStudent($department);
    $supervisor = assignSupervisor($student);

    app(ThesisExamRubricSeeder::class)->run($department);
    app(SupervisionIndicatorSeeder::class)->run($department);

    $rubric = Rubric::where('department_id', $department->id)->sole()->load('criteria');

    DepartmentRubric::factory()->create([
        'department_id' => $department->id,
        'exam_stage' => ExamStage::Proposal,
        'rubric_id' => $rubric->id,
        'pass_threshold' => 65,
    ]);

    $exam = ProposalExam::factory()->for($student)->create();

    $examiners = collect(range(1, $examinerCount))
        ->map(function (int $i) use ($department): Lecturer {
            $lecturer = makeLecturer();
            $lecturer->departments()->attach($department);

            return $lecturer;
        })
        ->all();

    $seats = [];

    foreach ($examiners as $index => $examiner) {
        $seats[] = [
            'lecturer_id' => $examiner->id,
            'role' => $index === 0
                ? ExamParticipantRole::ChairExaminer->value
                : ExamParticipantRole::MemberExaminer->value,
        ];
    }

    $seats[] = ['lecturer_id' => $supervisor->id, 'role' => ExamParticipantRole::Supervisor->value];

    app(BuildExamPanel::class)->handle($exam, $seats);

    // The sitting happened, so the examinee was there. Without this every
    // verdict below would be a verdict on someone recorded absent.
    $exam->participants()
        ->where('role', ExamParticipantRole::Student)
        ->update(['attended' => true, 'attended_at' => now()]);

    return compact('exam', 'rubric', 'supervisor', 'examiners');
}

/** Every criterion marked to the same fraction of its maximum. */
function markExam(ProposalExam $exam, Rubric $rubric, Lecturer $examiner, float $fraction): void
{
    $marks = $rubric->criteria
        ->mapWithKeys(fn ($c): array => [$c->key => $c->max_score * $fraction])
        ->all();

    // Asserted, so a server error can never pass for a recorded mark.
    test()->actingAs($examiner->user)->post(
        route('exams.evaluations.store', ['stage' => ExamStage::Proposal, 'exam' => $exam]),
        ['scores' => $marks, 'result' => ExamResult::Passed->value],
    )->assertRedirect();
}

/**
 * The program studi publishing a result, which is also what locks the sitting.
 */
function releaseExam(ProposalExam $exam): void
{
    $department = $exam->student->department;

    test()->actingAs(makeAdmin($department))
        ->withSession(['workspace' => (new Workspace(UserRole::Admin, $department))->key()])
        ->post(route('admin.exams.release', ['stage' => ExamStage::Proposal, 'exam' => $exam]))
        ->assertRedirect();
}

function markSupervision(ProposalExam $exam, Lecturer $supervisor, float $each): void
{
    test()->actingAs($supervisor->user)->post(
        route('exams.supervision.store', ['stage' => ExamStage::Proposal, 'exam' => $exam]),
        ['scores' => ['1a' => $each, '1b' => $each, '1c' => $each, '1d' => $each, '2' => $each]],
    )->assertRedirect();
}
