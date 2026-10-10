<?php

namespace Database\Seeders;

use App\Actions\Thesis\BuildExamPanel;
use App\Actions\Thesis\BuildVerificationQr;
use App\Actions\Thesis\ComposeFinalGrade;
use App\Enums\ApprovalStatus;
use App\Enums\AuditAction;
use App\Enums\ExamParticipantRole;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\GuidanceStage;
use App\Enums\GuidanceStatus;
use App\Enums\GuidanceTargetType;
use App\Enums\GuidanceType;
use App\Enums\NotificationCategory;
use App\Enums\ProposalStatus;
use App\Enums\RevisionStatus;
use App\Enums\RubricStatus;
use App\Enums\SignatureType;
use App\Enums\SupervisorRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\DepartmentRubric;
use App\Models\DepartmentThesisSetting;
use App\Models\ExamEvaluation;
use App\Models\ExamMinutes;
use App\Models\GuidanceSession;
use App\Models\Lecturer;
use App\Models\ProposalExam;
use App\Models\ProposalExamRequest;
use App\Models\ResearchExam;
use App\Models\ResearchExamRequest;
use App\Models\ResearchLogbook;
use App\Models\RevisionNote;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\Signature;
use App\Models\Student;
use App\Models\SupervisionIndicator;
use App\Models\SupervisionScore;
use App\Models\ThesisProposal;
use App\Models\User;
use App\Notifications\ThesisNotification;
use Carbon\CarbonInterface;
use Database\Seeders\Support\DemoFile;
use Database\Seeders\Support\ThesisExamRubricBlueprint;
use Illuminate\Database\Seeder;

/**
 * A worked example of the full supervision flow, so every screen has something
 * real to render during development.
 */
class DemoThesisSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::firstOrCreate(
            ['code' => 'TI'],
            ['name' => 'Teknik Informatika', 'description' => 'Program Studi Teknik Informatika', 'is_active' => true],
        );

        DepartmentThesisSetting::firstOrCreate(
            ['department_id' => $department->id, 'effective_since' => now()->startOfYear()],
            ['is_active' => true],
        );

        $this->seedRubric($department);
        app(SupervisionIndicatorSeeder::class)->run($department);

        $admin = $this->user('Admin Prodi TI', 'admin@kampus.test', UserRole::Admin);
        $admin->departments()->syncWithoutDetaching([$department->id]);

        $this->user('Superadmin '.config('app.name'), 'superadmin@kampus.test', UserRole::SuperAdmin);

        $supervisorOne = $this->lecturer('Dr. Budi Santoso', 'budi@kampus.test', '0012345678', $department);
        $supervisorTwo = $this->lecturer('Sari Wulandari, M.Kom', 'sari@kampus.test', '0012345679', $department);

        $this->seedSecondDepartment($supervisorOne);

        $student = $this->student('Rani Amelia', 'rani@kampus.test', '21051234', $department);

        foreach ([[$supervisorOne, 1], [$supervisorTwo, 2]] as [$lecturer, $order]) {
            $student->supervisorAssignments()->firstOrCreate(
                ['lecturer_id' => $lecturer->id, 'role' => SupervisorRole::Supervisor],
                ['order' => $order, 'assigned_by' => $admin->id, 'assigned_at' => now()],
            );
        }

        $proposalDocument = DemoFile::pdf(
            $student,
            'proposals',
            'Proposal_Rani_Amelia_v3.pdf',
            'PROPOSAL TUGAS AKHIR',
            [
                'Analisis Sentimen Ulasan E-Commerce dengan BERT',
                '',
                'Nama    : Rani Amelia',
                'NIM     : 21051234',
                'Prodi   : Teknik Informatika',
                '',
                'Bab 1 Pendahuluan, Bab 2 Tinjauan Pustaka, Bab 3 Metodologi.',
            ],
        );

        $proposal = ThesisProposal::firstOrCreate(
            ['student_id' => $student->id, 'title' => 'Analisis Sentimen Ulasan E-Commerce dengan BERT'],
            [
                'department_id' => $department->id,
                'background' => 'Pertumbuhan e-commerce di Indonesia menghasilkan volume ulasan pelanggan yang besar, namun sebagian besar belum dianalisis secara sistematis untuk mendukung pengambilan keputusan bisnis.',
                'problem_formulation' => 'Bagaimana performa model BERT dibandingkan metode berbasis leksikon dalam mengklasifikasikan sentimen ulasan produk berbahasa Indonesia?',
                'objectives' => 'Membangun dan mengevaluasi model klasifikasi sentimen berbasis BERT untuk ulasan e-commerce berbahasa Indonesia.',
                'methodology' => 'Eksperimen kuantitatif dengan fine-tuning IndoBERT pada dataset ulasan berlabel, dievaluasi menggunakan akurasi, precision, recall, dan F1-score.',
                'status' => ProposalStatus::Approved,
                'approved_by' => $supervisorOne->id,
                'approved_at' => now()->subWeeks(4),
                'document_path' => $proposalDocument['path'],
                'document_name' => $proposalDocument['name'],
                'document_size' => $proposalDocument['size'],
            ],
        );

        foreach ([[$supervisorOne, 1], [$supervisorTwo, 2]] as [$lecturer, $order]) {
            $proposal->perceptionAlignments()->firstOrCreate(
                ['lecturer_id' => $lecturer->id],
                [
                    'student_id' => $student->id,
                    'supervisor_order' => $order,
                    'status' => ApprovalStatus::Approved,
                    'feedback' => 'Topik dan metodologi disetujui. Silakan lanjut ke tahap bimbingan Bab 1–3.',
                    'responded_at' => now()->subWeeks(4),
                ],
            );
        }

        $this->seedGuidanceSessions($student, $supervisorOne);

        // Rani is still in the proposal phase, so she has no research logbook —
        // the logbook only opens once the ujian proposal has been passed.

        $this->seedNotifications($student, $supervisorOne);

        $this->seedStudentPastProposalExam($department, $admin, $supervisorOne, $supervisorTwo);

        $this->seedReleasedResearchExam($department, $admin, $supervisorOne, $supervisorTwo);
    }

    /**
     * A worked notification feed for the demo account, covering all four
     * categories and a mix of read and unread.
     */
    private function seedNotifications(Student $student, Lecturer $supervisor): void
    {
        if ($student->user->notifications()->exists()) {
            return;
        }

        $entries = [
            [NotificationCategory::Approval, 3, false,
                'Proposal Anda disetujui',
                "{$supervisor->user->name} menyetujui proposal \"Analisis Sentimen Ulasan E-Commerce dengan BERT\"."],
            [NotificationCategory::Schedule, 1, false,
                'Sesi bimbingan dijadwalkan',
                "Sesi bimbingan Bab 3 - Metodologi dijadwalkan bersama {$supervisor->user->name}."],
            [NotificationCategory::Reminder, 2, false,
                'Pengingat logbook mingguan',
                'Anda belum mengisi logbook penelitian untuk minggu ini. Segera tambahkan catatan progres.'],
            [NotificationCategory::Document, 8, true,
                'Berkas bimbingan diterima',
                'Draf Bab 1 yang Anda unggah telah diterima oleh dosen pembimbing.'],
            [NotificationCategory::Approval, 9, true,
                'Sesi bimbingan Bab 2 selesai',
                "{$supervisor->user->name} menandai sesi Bab 2 - Tinjauan Pustaka sebagai selesai."],
        ];

        foreach ($entries as [$category, $daysAgo, $read, $title, $message]) {
            $student->user->notify(new ThesisNotification($category, $title, $message));

            $notification = $student->user->notifications()->latest('created_at')->first();

            $notification->forceFill([
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
                'read_at' => $read ? now()->subDays($daysAgo)->addHour() : null,
            ])->save();
        }
    }

    /**
     * A second program studi, so the workspace switcher has something to
     * switch between. Dr. Budi teaches in both prodi and administers this one —
     * three seats on one account, which is the case the design's "Ganti
     * Workspace" menu exists for.
     */
    private function seedSecondDepartment(Lecturer $supervisorOne): void
    {
        $department = Department::firstOrCreate(
            ['code' => 'SI'],
            ['name' => 'Sistem Informasi', 'description' => 'Program Studi Sistem Informasi', 'is_active' => true],
        );

        DepartmentThesisSetting::firstOrCreate(
            ['department_id' => $department->id, 'effective_since' => now()->startOfYear()],
            ['is_active' => true],
        );

        $this->seedRubric($department);
        app(SupervisionIndicatorSeeder::class)->run($department);

        $supervisorOne->departments()->syncWithoutDetaching([$department->id]);
        $supervisorOne->user->assignRole(UserRole::Admin->value);
        $supervisorOne->user->departments()->syncWithoutDetaching([$department->id]);

        $student = $this->student('Putri Maharani', 'putri@kampus.test', '21062301', $department);

        $student->supervisorAssignments()->firstOrCreate(
            ['lecturer_id' => $supervisorOne->id, 'role' => SupervisorRole::Supervisor],
            ['order' => 1, 'assigned_by' => $supervisorOne->user_id, 'assigned_at' => now()],
        );

        ThesisProposal::firstOrCreate(
            ['student_id' => $student->id, 'title' => 'Evaluasi Usability Portal Akademik dengan Metode SUS'],
            [
                'department_id' => $department->id,
                'background' => 'Portal akademik dipakai seluruh mahasiswa, namun kemudahan penggunaannya belum pernah diukur secara formal.',
                'problem_formulation' => 'Seberapa baik tingkat usability portal akademik menurut System Usability Scale?',
                'objectives' => 'Mengukur dan memetakan masalah usability portal akademik sebagai dasar perbaikan antarmuka.',
                'methodology' => 'Survei SUS kepada 100 responden, dilanjutkan wawancara pada kasus dengan skor terendah.',
                'status' => ProposalStatus::Submitted,
            ],
        );
    }

    private function user(string $name, string $email, UserRole $role): User
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function lecturer(string $name, string $email, string $number, Department $department): Lecturer
    {
        $user = $this->user($name, $email, UserRole::Lecturer);

        $lecturer = Lecturer::firstOrCreate(['user_id' => $user->id], ['lecturer_number' => $number]);

        // The prodi a dosen is first created in is their homebase, and so the
        // workspace they land in by default.
        $lecturer->departments()->syncWithoutDetaching([$department->id => ['is_primary' => true]]);

        return $lecturer;
    }

    private function student(string $name, string $email, string $number, Department $department): Student
    {
        $user = $this->user($name, $email, UserRole::Student);

        return Student::firstOrCreate(
            ['user_id' => $user->id],
            [
                'department_id' => $department->id,
                'student_number' => $number,
                'enrollment_year' => 2021,
            ],
        );
    }

    /**
     * The four sessions the design walks through: two finished ones carrying
     * the supervisor's closing notes, a running async discussion with a real
     * chat thread, and an upcoming research-stage meeting.
     *
     * Every entry carries the same keys so the list has one array shape.
     */
    private function seedGuidanceSessions(Student $student, Lecturer $supervisor): void
    {
        $draft = DemoFile::pdf($student, 'guidance', 'Draf_Bab1_Rani_Amelia.pdf', 'DRAF BAB 1 - PENDAHULUAN', [
            'Analisis Sentimen Ulasan E-Commerce dengan BERT',
            '',
            '1.1 Latar Belakang',
            '1.2 Rumusan Masalah',
            '1.3 Tujuan Penelitian',
        ]);

        $chapterThree = DemoFile::pdf($student, 'guidance', 'Draf_Bab3_Metodologi_v2.pdf', 'DRAF BAB 3 - METODOLOGI', [
            'Revisi kedua: populasi, sampel, dan teknik analisis data.',
            '',
            '3.1 Jenis Penelitian',
            '3.2 Populasi dan Sampel',
            '3.3 Teknik Pengumpulan Data',
        ]);

        $sessions = [
            [
                'title' => 'Bimbingan Bab 1 - Pendahuluan',
                'stage' => GuidanceStage::Proposal,
                'target_type' => GuidanceTargetType::Single,
                'guidance_type' => GuidanceType::FaceToFace,
                'status' => GuidanceStatus::Completed,
                'weeks_ago' => 4,
                'scheduled_in_days' => null,
                'document' => $draft,
                'notes' => 'Mohon diperiksa latar belakang dan rumusan masalah pada draf Bab 1.',
                'completion_notes' => 'Latar belakang sudah menunjukkan kesenjangan penelitian dengan jelas. '
                    .'Rumusan masalah diperbaiki menjadi satu pertanyaan utama, dan sitasi dirapikan ke format APA.',
                'feedback' => 'Bab 1 diterima. Lanjutkan ke tinjauan pustaka.',
            ],
            [
                'title' => 'Bimbingan Bab 2 - Tinjauan Pustaka',
                'stage' => GuidanceStage::Proposal,
                'target_type' => GuidanceTargetType::Single,
                'guidance_type' => GuidanceType::FaceToFace,
                'status' => GuidanceStatus::Completed,
                'weeks_ago' => 2,
                'scheduled_in_days' => null,
                'document' => null,
                'notes' => 'Diskusi pemetaan penelitian terdahulu tentang analisis sentimen bahasa Indonesia.',
                'completion_notes' => 'Tinjauan pustaka sudah mencakup sepuluh penelitian terdahulu yang relevan. '
                    .'Tambahkan tabel perbandingan metode leksikon dan transformer sebelum masuk Bab 3.',
                'feedback' => 'Bab 2 diterima dengan catatan penambahan tabel perbandingan.',
            ],
            [
                'title' => 'Bimbingan Bab 3 - Metodologi',
                'stage' => GuidanceStage::Proposal,
                'target_type' => GuidanceTargetType::Single,
                'guidance_type' => GuidanceType::OnlineAsync,
                'status' => GuidanceStatus::Ongoing,
                'weeks_ago' => null,
                'scheduled_in_days' => null,
                'document' => null,
                'notes' => 'Mohon diperiksa kembali bagian teknik analisis data pada Bab 3.',
                'completion_notes' => null,
                'feedback' => null,
            ],
            [
                // Rani has not sat her ujian proposal, so every session she has
                // is still in the proposal stage — that is the locked state the
                // guidance-stage gate is meant to produce.
                'title' => 'Bimbingan Revisi Bab 3 - Teknik Analisis Data',
                'stage' => GuidanceStage::Proposal,
                'target_type' => GuidanceTargetType::Both,
                'guidance_type' => GuidanceType::FaceToFace,
                'status' => GuidanceStatus::Ongoing,
                'weeks_ago' => null,
                'scheduled_in_days' => 4,
                'document' => $chapterThree,
                'notes' => 'Menyempurnakan teknik analisis data sebelum proposal diajukan ke ujian.',
                'completion_notes' => null,
                'feedback' => null,
            ],
        ];

        foreach ($sessions as $data) {
            $isAsync = $data['guidance_type'] === GuidanceType::OnlineAsync;
            $isCompleted = $data['status'] === GuidanceStatus::Completed;
            $completedAt = $isCompleted ? now()->subWeeks($data['weeks_ago'])->setTime(10, 30) : null;

            $session = GuidanceSession::firstOrCreate(
                ['student_id' => $student->id, 'title' => $data['title']],
                [
                    'created_by' => $student->user_id,
                    'stage' => $data['stage'],
                    'target_type' => $data['target_type'],
                    'guidance_type' => $data['guidance_type'],
                    'meeting_type' => $isAsync ? null : 'offline',
                    'meeting_location' => $isAsync ? null : 'Ruang Dosen 301',
                    'meeting_scheduled_at' => match (true) {
                        $isAsync => null,
                        $isCompleted => $completedAt,
                        default => now()->addDays($data['scheduled_in_days'] ?? 3)->setTime(13, 0),
                    },
                    'status' => $data['status'],
                    'completed_at' => $completedAt,
                    'completion_notes' => $data['completion_notes'],
                    'feedback' => $data['feedback'],
                    'notes' => $data['notes'],
                    'document_path' => $data['document']['path'] ?? null,
                    'document_name' => $data['document']['name'] ?? null,
                ],
            );

            if ($isAsync) {
                $this->seedChat($session, $student, $supervisor, $chapterThree);
            }
        }
    }

    /**
     * The three-message thread the design shows on an async session.
     *
     * @param  array{path: string, name: string, size: int}  $attachment
     */
    private function seedChat(
        GuidanceSession $session,
        Student $student,
        Lecturer $supervisor,
        array $attachment,
    ): void {
        if ($session->messages()->exists()) {
            return;
        }

        $messages = [
            [
                'user_id' => $supervisor->user_id,
                'message' => 'Silakan lanjutkan penjelasan populasi dan sampel pada Bab 3 ya, masih kurang detail.',
                'created_at' => now()->subDays(3)->setTime(9, 12),
                'attachment' => null,
            ],
            [
                'user_id' => $student->user_id,
                'message' => 'Baik Pak, saya akan tambahkan teknik purposive sampling dan kriteria respondennya. '
                    .'Draf revisi saya lampirkan.',
                'created_at' => now()->subDays(3)->setTime(14, 30),
                'attachment' => $attachment,
            ],
            [
                'user_id' => $supervisor->user_id,
                'message' => 'Sudah saya baca, jauh lebih jelas. Lanjutkan ke teknik analisis datanya.',
                'created_at' => now()->subDays(2)->setTime(8, 5),
                'attachment' => null,
            ],
        ];

        foreach ($messages as $message) {
            $session->messages()->create([
                'user_id' => $message['user_id'],
                'message' => $message['message'],
                'attachment_path' => $message['attachment']['path'] ?? null,
                'attachment_name' => $message['attachment']['name'] ?? null,
                'created_at' => $message['created_at'],
                'updated_at' => $message['created_at'],
            ]);
        }
    }

    /**
     * A second mahasiswa who has already passed his ujian proposal, so the
     * unlocked side of the guidance-stage gate is demonstrable. Nothing in the
     * application schedules or evaluates an exam yet — that arrives with the
     * Dosen and Admin Prodi screens — so the whole chain is written here.
     */
    private function seedStudentPastProposalExam(
        Department $department,
        User $admin,
        Lecturer $supervisorOne,
        Lecturer $supervisorTwo,
    ): void {
        $student = $this->student('Dimas Prasetyo', 'dimas@kampus.test', '21051235', $department);

        foreach ([[$supervisorOne, 1], [$supervisorTwo, 2]] as [$lecturer, $order]) {
            $student->supervisorAssignments()->firstOrCreate(
                ['lecturer_id' => $lecturer->id, 'role' => SupervisorRole::Supervisor],
                ['order' => $order, 'assigned_by' => $admin->id, 'assigned_at' => now()],
            );
        }

        $title = 'Sistem Rekomendasi Mata Kuliah Pilihan Berbasis Collaborative Filtering';

        $document = DemoFile::pdf($student, 'proposals', 'Proposal_Dimas_Prasetyo_final.pdf', 'PROPOSAL TUGAS AKHIR', [
            $title,
            '',
            'Nama    : Dimas Prasetyo',
            'NIM     : 21051235',
            'Prodi   : Teknik Informatika',
        ]);

        $proposal = ThesisProposal::firstOrCreate(
            ['student_id' => $student->id, 'title' => $title],
            [
                'department_id' => $department->id,
                'background' => 'Mahasiswa kerap kesulitan memilih mata kuliah pilihan yang sesuai dengan minat dan rekam jejak akademiknya.',
                'problem_formulation' => 'Bagaimana penerapan collaborative filtering dapat menghasilkan rekomendasi mata kuliah pilihan yang relevan?',
                'objectives' => 'Membangun dan mengevaluasi sistem rekomendasi mata kuliah pilihan berbasis collaborative filtering.',
                'methodology' => 'Pengembangan sistem dengan pendekatan item-based collaborative filtering, dievaluasi menggunakan precision@k dan recall@k.',
                'status' => ProposalStatus::Approved,
                'approved_by' => $supervisorOne->id,
                'approved_at' => now()->subMonths(3),
                'document_path' => $document['path'],
                'document_name' => $document['name'],
                'document_size' => $document['size'],
            ],
        );

        foreach ([[$supervisorOne, 1], [$supervisorTwo, 2]] as [$lecturer, $order]) {
            $proposal->perceptionAlignments()->firstOrCreate(
                ['lecturer_id' => $lecturer->id],
                [
                    'student_id' => $student->id,
                    'supervisor_order' => $order,
                    'status' => ApprovalStatus::Approved,
                    'feedback' => 'Topik dan metodologi disetujui.',
                    'responded_at' => now()->subMonths(3),
                ],
            );
        }

        $this->seedCompletedProposalGuidance($student);

        $exam = ProposalExam::firstOrCreate(
            ['student_id' => $student->id, 'title' => 'Ujian Proposal — '.$student->user->name],
            [
                'thesis_proposal_id' => $proposal->id,
                'scheduled_by' => $admin->id,
                'description' => 'Ujian proposal tugas akhir semester genap.',
                'status' => ExamStatus::Scored,
                'scheduled_at' => now()->subWeeks(6)->setTime(9, 0),
                'duration_minutes' => 60,
                'venue' => 'Ruang Sidang 2',
                'result' => ExamResult::Passed,
                'evaluation_notes' => 'Penguasaan materi baik, metodologi sudah tepat sasaran.',
                'feedback' => 'Perbaiki penulisan rumusan masalah dan lanjutkan ke tahap penelitian.',
                'started_at' => now()->subWeeks(6)->setTime(9, 0),
                'completed_at' => now()->subWeeks(6)->setTime(10, 0),
            ],
        );

        $receipt = DemoFile::pdf($student, 'exam-requests', 'Bukti_Lunas_SPP.pdf', 'BUKTI PEMBAYARAN SPP', [
            'Nama : Dimas Prasetyo',
            'NIM  : 21051235',
            'Status : LUNAS',
        ]);

        ProposalExamRequest::firstOrCreate(
            ['student_id' => $student->id, 'thesis_proposal_id' => $proposal->id],
            [
                'proposal_exam_id' => $exam->id,
                'student_notes' => 'Seluruh bimbingan proposal telah selesai, mohon dijadwalkan.',
                'documents' => [['name' => $receipt['name'], 'path' => $receipt['path']]],
                'status' => ApprovalStatus::Approved,
                'supervisor_status' => ApprovalStatus::Approved,
                'supervisor_verified_by' => $supervisorOne->user_id,
                'supervisor_verified_at' => now()->subWeeks(8),
                'supervisor_notes' => 'Mahasiswa siap mengikuti ujian proposal.',
                'admin_status' => ApprovalStatus::Approved,
                'admin_verified_by' => $admin->id,
                'admin_verified_at' => now()->subWeeks(7),
                'admin_notes' => 'Berkas lengkap, dijadwalkan pada periode berjalan.',
            ],
        );

        $this->seedPanelEvaluations($exam, $supervisorOne, $supervisorTwo);

        $this->seedResearchGuidance($student);

        $this->seedUpcomingResearchExam($student, $proposal, $admin, $supervisorOne, $supervisorTwo);

        $evaluation = DemoFile::csv($student, 'logbook', 'evaluasi_model_v3.csv', [
            ['k', 'precision', 'recall', 'f1'],
            [5, '0.7412', '0.6188', '0.6745'],
            [10, '0.7106', '0.7023', '0.7064'],
            [15, '0.6822', '0.7590', '0.7186'],
        ]);

        $logs = [
            [6, 85, 'Menyelesaikan evaluasi precision@k dan recall@k pada dataset riwayat KRS tiga angkatan.'],
            [18, 70, 'Membangun matriks kemiripan antar mata kuliah dan menguji parameter neighbourhood.'],
            [32, 50, 'Menyiapkan dan membersihkan dataset riwayat pengambilan mata kuliah pilihan.'],
        ];

        foreach ($logs as $index => [$daysAgo, $progress, $description]) {
            // Only the most recent entry carries an attachment, so the timeline
            // shows both shapes of row.
            $attachment = $index === 0 ? $evaluation : null;

            ResearchLogbook::firstOrCreate(
                ['student_id' => $student->id, 'log_date' => now()->subDays($daysAgo)->toDateString()],
                [
                    'progress' => $progress,
                    'description' => $description,
                    'attachment_path' => $attachment['path'] ?? null,
                    'attachment_name' => $attachment['name'] ?? null,
                ],
            );
        }
    }

    /**
     * Four finished proposal sessions, which is the default minimum a program
     * studi requires before the ujian proposal.
     */
    private function seedCompletedProposalGuidance(Student $student): void
    {
        $sessions = [
            ['Bimbingan Bab 1 - Pendahuluan', 20, 'Latar belakang dan rumusan masalah sudah tajam.'],
            ['Bimbingan Bab 2 - Tinjauan Pustaka', 17, 'Penelitian terdahulu sudah dipetakan dengan baik.'],
            ['Bimbingan Bab 3 - Metodologi', 14, 'Skema evaluasi precision@k dan recall@k disepakati.'],
            ['Bimbingan Finalisasi Proposal', 10, 'Proposal siap diajukan ke ujian proposal.'],
        ];

        foreach ($sessions as [$title, $weeksAgo, $notes]) {
            GuidanceSession::firstOrCreate(
                ['student_id' => $student->id, 'title' => $title],
                [
                    'created_by' => $student->user_id,
                    'stage' => GuidanceStage::Proposal,
                    'target_type' => GuidanceTargetType::Single,
                    'guidance_type' => GuidanceType::FaceToFace,
                    'meeting_type' => 'offline',
                    'meeting_location' => 'Ruang Dosen 302',
                    'meeting_scheduled_at' => now()->subWeeks($weeksAgo)->setTime(10, 0),
                    'status' => GuidanceStatus::Completed,
                    'completed_at' => now()->subWeeks($weeksAgo)->setTime(11, 0),
                    'completion_notes' => $notes,
                    'feedback' => 'Diterima, lanjutkan ke tahap berikutnya.',
                ],
            );
        }
    }

    /**
     * The stage that only opens once the ujian proposal has been passed.
     */
    private function seedResearchGuidance(Student $student): void
    {
        $sessions = [
            ['Bimbingan Hasil Analisis & Pembahasan', GuidanceStatus::Completed, 4, 'Interpretasi metrik evaluasi sudah tepat.'],
            ['Bimbingan Penulisan Laporan Akhir', GuidanceStatus::Ongoing, null, null],
        ];

        foreach ($sessions as [$title, $status, $weeksAgo, $notes]) {
            $completed = $status === GuidanceStatus::Completed;

            GuidanceSession::firstOrCreate(
                ['student_id' => $student->id, 'title' => $title],
                [
                    'created_by' => $student->user_id,
                    'stage' => GuidanceStage::Research,
                    'target_type' => GuidanceTargetType::Both,
                    'guidance_type' => GuidanceType::FaceToFace,
                    'meeting_type' => 'offline',
                    'meeting_location' => 'Ruang Dosen 302',
                    'meeting_scheduled_at' => $completed
                        ? now()->subWeeks($weeksAgo)->setTime(13, 0)
                        : now()->addDays(5)->setTime(13, 0),
                    'status' => $status,
                    'completed_at' => $completed ? now()->subWeeks($weeksAgo)->setTime(14, 0) : null,
                    'completion_notes' => $notes,
                    'feedback' => $completed ? 'Lanjutkan ke penulisan laporan akhir.' : null,
                ],
            );
        }
    }

    /**
     * The far-along student is already booked for his ujian hasil, so the
     * Pelaksanaan Ujian screen has something in both of its tabs.
     *
     * His panel is seated but nothing has happened at it yet: no presensi, no
     * marks, no berita acara. That is the state the screens for running a
     * sitting are actually used in, so one demo sitting has to be in it.
     */
    private function seedUpcomingResearchExam(
        Student $student,
        ThesisProposal $proposal,
        User $admin,
        Lecturer $supervisorOne,
        Lecturer $supervisorTwo,
    ): void {
        $exam = ResearchExam::firstOrCreate(
            ['student_id' => $student->id, 'title' => 'Ujian Hasil Penelitian — '.$student->user->name],
            [
                'scheduled_by' => $admin->id,
                'description' => 'Ujian hasil penelitian tugas akhir semester genap.',
                'status' => ExamStatus::Scheduled,
                'scheduled_at' => now()->addWeek()->setTime(13, 0),
                'duration_minutes' => 90,
                'venue' => 'Ruang Sidang 1',
                'preparation_notes' => 'Siapkan demo sistem rekomendasi dan hasil evaluasi precision@k.',
            ],
        );

        $draft = DemoFile::pdf($student, 'exam-requests', 'Draf_Laporan_Hasil_Dimas.pdf', 'DRAF LAPORAN HASIL PENELITIAN', [
            $proposal->title,
            '',
            'Nama  : Dimas Prasetyo',
            'NIM   : 21051235',
            'Bab   : I - V (draf lengkap)',
        ]);

        ResearchExamRequest::firstOrCreate(
            ['student_id' => $student->id, 'thesis_proposal_id' => $proposal->id],
            [
                'research_exam_id' => $exam->id,
                'student_notes' => 'Penelitian dan penulisan laporan telah selesai, mohon dijadwalkan ujian hasil.',
                'documents' => [['name' => $draft['name'], 'path' => $draft['path']]],
                'status' => ApprovalStatus::Approved,
                'supervisor_status' => ApprovalStatus::Approved,
                'supervisor_verified_by' => $supervisorOne->user_id,
                'supervisor_verified_at' => now()->subWeeks(2),
                'supervisor_notes' => 'Hasil penelitian sudah lengkap dan layak diuji.',
                'admin_status' => ApprovalStatus::Approved,
                'admin_verified_by' => $admin->id,
                'admin_verified_at' => now()->subWeek(),
                'admin_notes' => 'Berkas lengkap, dijadwalkan pada periode berjalan.',
            ],
        );

        if ($exam->participants()->exists()) {
            return;
        }

        $department = $student->department;

        app(BuildExamPanel::class)->handle($exam, [
            [
                'lecturer_id' => $this->lecturer(
                    'Prof. Dr. Hendra Wijaya', 'hendra@kampus.test', '0012345680', $department,
                )->id,
                'role' => ExamParticipantRole::ChairExaminer->value,
            ],
            [
                'lecturer_id' => $this->lecturer(
                    'Aulia Rahman, M.T.', 'aulia@kampus.test', '0012345681', $department,
                )->id,
                'role' => ExamParticipantRole::MemberExaminer->value,
            ],
            ['lecturer_id' => $supervisorOne->id, 'role' => ExamParticipantRole::Supervisor->value],
            ['lecturer_id' => $supervisorTwo->id, 'role' => ExamParticipantRole::Supervisor->value],
        ]);
    }

    private function seedPanelEvaluations(
        ProposalExam $exam,
        Lecturer $supervisorOne,
        Lecturer $supervisorTwo,
    ): void {
        if ($exam->evaluations()->exists()) {
            return;
        }

        $rubric = $exam->effectiveRubric();

        if ($rubric === null) {
            return;
        }

        $verdicts = [
            // Keyed to the criteria in order: proposal, metodologi (0-100),
            // presentasi (0-10), penguasaan materi (0-100).
            [$supervisorOne, [88, 84, 9, 82], 'Penguasaan materi baik, metodologi sudah tepat sasaran.'],
            [$supervisorTwo, [82, 80, 8, 80], 'Rumusan masalah jelas; perbaiki penulisan sitasi.'],
        ];

        foreach ($verdicts as [$lecturer, $marks, $feedback]) {
            $scores = $rubric->criteria
                ->values()
                ->mapWithKeys(fn (RubricCriterion $criterion, int $index): array => [
                    $criterion->key => $marks[$index] ?? 80,
                ])
                ->all();

            $evaluation = $exam->evaluations()->create([
                'lecturer_id' => $lecturer->id,
                'rubric_id' => $rubric->id,
                'panel_role' => SupervisorRole::Supervisor,
                'final_score' => ExamEvaluation::scoreFor($rubric, $scores),
                'result' => ExamResult::Passed,
                'feedback' => $feedback,
                'evaluated_at' => now()->subWeeks(6),
            ]);

            foreach ($rubric->criteria as $criterion) {
                $evaluation->scores()->create([
                    'criterion_key' => $criterion->key,
                    'criterion_label' => $criterion->label,
                    'weight' => $criterion->weight,
                    'max_score' => $criterion->max_score,
                    'score' => $scores[$criterion->key],
                ]);
            }
        }

        // Let the exam's own score come from the panel, so the detail screen
        // and the verdicts behind it cannot disagree.
        $exam->settleFromEvaluations();
    }

    /**
     * A third mahasiswa whose ujian hasil is entirely behind him: panel seated,
     * presensi signed, both forms marked, revisi written, berita acara taken
     * and the result released. Without this every screen added for Pelaksanaan
     * Ujian would render empty on a fresh database.
     */
    private function seedReleasedResearchExam(
        Department $department,
        User $admin,
        Lecturer $supervisorOne,
        Lecturer $supervisorTwo,
    ): void {
        $student = $this->student('Bagas Nugroho', 'bagas@kampus.test', '21051236', $department);

        foreach ([[$supervisorOne, 1], [$supervisorTwo, 2]] as [$lecturer, $order]) {
            $student->supervisorAssignments()->firstOrCreate(
                ['lecturer_id' => $lecturer->id, 'role' => SupervisorRole::Supervisor],
                ['order' => $order, 'assigned_by' => $admin->id, 'assigned_at' => now()],
            );
        }

        $title = 'Deteksi Dini Risiko Gagal Studi Mahasiswa dengan Gradient Boosting';

        $document = DemoFile::pdf($student, 'proposals', 'Proposal_Bagas_Nugroho.pdf', 'PROPOSAL TUGAS AKHIR', [
            $title,
            '',
            'Nama    : Bagas Nugroho',
            'NIM     : 21051236',
            'Prodi   : Teknik Informatika',
        ]);

        $proposal = ThesisProposal::firstOrCreate(
            ['student_id' => $student->id, 'title' => $title],
            [
                'department_id' => $department->id,
                'background' => 'Keterlambatan penanganan mahasiswa berisiko gagal studi umumnya terjadi karena tanda-tandanya baru terlihat pada akhir semester.',
                'problem_formulation' => 'Sejauh mana model gradient boosting dapat memprediksi risiko gagal studi berdasarkan data akademik semester awal?',
                'objectives' => 'Membangun model prediksi risiko gagal studi sebagai dasar intervensi dosen pembimbing akademik.',
                'methodology' => 'Eksperimen kuantitatif pada data akademik lima angkatan, dievaluasi dengan AUC, recall, dan matriks konfusi.',
                'status' => ProposalStatus::Approved,
                'approved_by' => $supervisorOne->id,
                'approved_at' => now()->subMonths(8),
                'document_path' => $document['path'],
                'document_name' => $document['name'],
                'document_size' => $document['size'],
            ],
        );

        foreach ([[$supervisorOne, 1], [$supervisorTwo, 2]] as [$lecturer, $order]) {
            $proposal->perceptionAlignments()->firstOrCreate(
                ['lecturer_id' => $lecturer->id],
                [
                    'student_id' => $student->id,
                    'supervisor_order' => $order,
                    'status' => ApprovalStatus::Approved,
                    'feedback' => 'Topik dan metodologi disetujui.',
                    'responded_at' => now()->subMonths(8),
                ],
            );
        }

        $this->seedCompletedProposalGuidance($student);
        $this->seedResearchGuidance($student);

        ResearchLogbook::firstOrCreate(
            ['student_id' => $student->id, 'log_date' => now()->subMonths(2)->toDateString()],
            [
                'progress' => 100,
                'description' => 'Laporan akhir selesai, seluruh bab dan lampiran telah diperiksa pembimbing.',
            ],
        );

        // He passed his ujian proposal half a year ago; the research stage is
        // what this account exists to demonstrate.
        ProposalExam::firstOrCreate(
            ['student_id' => $student->id, 'title' => 'Ujian Proposal — '.$student->user->name],
            [
                'thesis_proposal_id' => $proposal->id,
                'scheduled_by' => $admin->id,
                'status' => ExamStatus::Scored,
                'scheduled_at' => now()->subMonths(6)->setTime(9, 0),
                'duration_minutes' => 60,
                'venue' => 'Ruang Sidang 2',
                'result' => ExamResult::Passed,
                'final_score' => 84.5,
                'completed_at' => now()->subMonths(6)->setTime(10, 0),
            ],
        );

        $exam = ResearchExam::firstOrCreate(
            ['student_id' => $student->id, 'title' => 'Ujian Hasil Penelitian — '.$student->user->name],
            [
                'scheduled_by' => $admin->id,
                'description' => 'Ujian hasil penelitian tugas akhir semester ganjil.',
                'status' => ExamStatus::Ongoing,
                'scheduled_at' => now()->subWeeks(3)->setTime(9, 0),
                'duration_minutes' => 90,
                'venue' => 'Ruang Sidang Utama',
                'preparation_notes' => 'Bawa laporan akhir tercetak rangkap empat.',
            ],
        );

        $draft = DemoFile::pdf($student, 'exam-requests', 'Laporan_Akhir_Bagas.pdf', 'LAPORAN TUGAS AKHIR', [
            $title,
            '',
            'Nama  : Bagas Nugroho',
            'NIM   : 21051236',
            'Bab   : I - V',
        ]);

        ResearchExamRequest::firstOrCreate(
            ['student_id' => $student->id, 'thesis_proposal_id' => $proposal->id],
            [
                'research_exam_id' => $exam->id,
                'student_notes' => 'Laporan akhir telah disetujui kedua pembimbing.',
                'documents' => [['name' => $draft['name'], 'path' => $draft['path']]],
                'status' => ApprovalStatus::Approved,
                'supervisor_status' => ApprovalStatus::Approved,
                'supervisor_verified_by' => $supervisorOne->user_id,
                'supervisor_verified_at' => now()->subWeeks(6),
                'supervisor_notes' => 'Laporan lengkap dan layak diuji.',
                'admin_status' => ApprovalStatus::Approved,
                'admin_verified_by' => $admin->id,
                'admin_verified_at' => now()->subWeeks(5),
                'admin_notes' => 'Berkas lengkap, dijadwalkan pada periode berjalan.',
            ],
        );

        // Everything below writes the sitting itself, and only once.
        if ($exam->participants()->exists()) {
            return;
        }

        $chair = $this->lecturer('Prof. Dr. Hendra Wijaya', 'hendra@kampus.test', '0012345680', $department);
        $member = $this->lecturer('Aulia Rahman, M.T.', 'aulia@kampus.test', '0012345681', $department);

        app(BuildExamPanel::class)->handle($exam, [
            ['lecturer_id' => $chair->id, 'role' => ExamParticipantRole::ChairExaminer->value],
            ['lecturer_id' => $member->id, 'role' => ExamParticipantRole::MemberExaminer->value],
            ['lecturer_id' => $supervisorOne->id, 'role' => ExamParticipantRole::Supervisor->value],
            ['lecturer_id' => $supervisorTwo->id, 'role' => ExamParticipantRole::Supervisor->value],
        ]);

        $sat = $exam->scheduled_at;

        $this->seedAttendance($exam, $sat);
        $this->seedBlockEvaluations($exam, [
            [$chair, [0, 1], 'Penguasaan materi baik; pemilihan metrik evaluasi tepat untuk kasus data tidak seimbang.'],
            [$member, [0, 1, 1], 'Pembahasan sudah runtut. Perdalam interpretasi feature importance pada Bab IV.'],
        ], $sat);
        $this->seedSupervisionScores($exam, [
            [$supervisorOne, [19, 18, 18, 17, 19]],
            [$supervisorTwo, [18, 17, 18, 18, 18]],
        ], $sat);
        $this->seedRevisionNotes($exam, [
            [$chair, [
                'Perbaiki penulisan rumusan masalah pada Bab I agar selaras dengan tujuan penelitian.',
                'Lengkapi tabel matriks konfusi dengan nilai recall per kelas.',
            ]],
            [$member, [
                'Tambahkan pembahasan feature importance pada Bab IV.',
                'Perbaiki konsistensi penulisan sitasi pada daftar pustaka.',
            ]],
        ], $sat);

        $this->seedMinutes($exam, $chair, $sat);
        $this->releaseExam($exam, $admin, $sat);
    }

    /**
     * Presensi: everyone turned up and signed, so the Daftar Hadir has a real
     * signature block rather than five empty boxes.
     */
    private function seedAttendance(ResearchExam $exam, CarbonInterface $sat): void
    {
        foreach ($exam->participants()->with('user')->get() as $participant) {
            $signature = Signature::create([
                'user_id' => $participant->user_id,
                'type' => SignatureType::Drawn,
                'path' => DemoFile::signature($participant->user),
                'signed_at' => $sat->copy()->addMinutes(95),
                'ip_address' => '127.0.0.1',
                'user_agent' => config('app.name').' Demo Seeder',
            ]);

            $participant->update([
                'attended' => true,
                'attended_at' => $sat->copy()->subMinutes(10),
                'signed_at' => $signature->signed_at,
                'signature_id' => $signature->id,
            ]);
        }
    }

    /**
     * The examiners' marks on the block-additive faculty rubric, every item
     * filled in, so the Form Penilaian prints with real figures.
     *
     * Each verdict carries a repeating pattern of deductions rather than a
     * percentage: the items are worth two to four points each, so a fraction
     * would round back to full marks and every examiner would score 100.
     *
     * @param  array<int, array{0: Lecturer, 1: array<int, int>, 2: string}>  $verdicts
     */
    private function seedBlockEvaluations(ResearchExam $exam, array $verdicts, CarbonInterface $sat): void
    {
        $rubric = $exam->effectiveRubric();

        if ($rubric === null) {
            return;
        }

        foreach ($verdicts as [$lecturer, $deductions, $feedback]) {
            $marks = $rubric->criteria
                ->values()
                ->mapWithKeys(fn (RubricCriterion $criterion, int $index): array => [
                    // At least one mark on every line: an examiner who leaves a
                    // row blank is a case the forms do not allow.
                    $criterion->key => max(
                        1,
                        $criterion->max_score - $deductions[$index % count($deductions)],
                    ),
                ])
                ->all();

            $evaluation = $exam->evaluations()->create([
                'lecturer_id' => $lecturer->id,
                'rubric_id' => $rubric->id,
                'panel_role' => SupervisorRole::Supervisor,
                'final_score' => ExamEvaluation::scoreFor($rubric, $marks),
                'result' => ExamResult::Passed,
                'feedback' => $feedback,
                'evaluated_at' => $sat->copy()->addMinutes(80),
            ]);

            foreach ($rubric->criteria as $criterion) {
                $evaluation->scores()->create([
                    'criterion_key' => $criterion->key,
                    'criterion_label' => $criterion->label,
                    'weight' => $criterion->weight,
                    'max_score' => $criterion->max_score,
                    'score' => $marks[$criterion->key],
                ]);
            }
        }
    }

    /**
     * Form Penilaian Proses Bimbingan, filled in by each pembimbing.
     *
     * @param  array<int, array{0: Lecturer, 1: array<int, int>}>  $sheets
     */
    private function seedSupervisionScores(ResearchExam $exam, array $sheets, CarbonInterface $sat): void
    {
        $indicators = SupervisionIndicator::activeFor($exam->student->department)->values();

        foreach ($sheets as [$lecturer, $marks]) {
            foreach ($indicators as $index => $indicator) {
                SupervisionScore::create([
                    'exam_type' => $exam->getMorphClass(),
                    'exam_id' => $exam->id,
                    'lecturer_id' => $lecturer->id,
                    'supervision_indicator_id' => $indicator->id,
                    'indicator_code' => $indicator->code,
                    'indicator_label' => $indicator->label,
                    'max_score' => $indicator->max_score,
                    'score' => $marks[$index] ?? $indicator->max_score,
                    'scored_at' => $sat->copy()->addMinutes(85),
                ]);
            }
        }
    }

    /**
     * Lembar Revisi, one sheet per penguji, numbered within that sheet.
     *
     * @param  array<int, array{0: Lecturer, 1: array<int, string>}>  $sheets
     */
    private function seedRevisionNotes(ResearchExam $exam, array $sheets, CarbonInterface $sat): void
    {
        foreach ($sheets as [$lecturer, $points]) {
            foreach (array_values($points) as $index => $body) {
                RevisionNote::create([
                    'exam_type' => $exam->getMorphClass(),
                    'exam_id' => $exam->id,
                    'lecturer_id' => $lecturer->id,
                    'position' => $index + 1,
                    'body' => $body,
                    // The first point is already ticked off, so the checklist
                    // shows both of its states.
                    'status' => $index === 0 ? RevisionStatus::Done : RevisionStatus::Open,
                    'due_date' => $sat->copy()->addDays(14)->toDateString(),
                    'completed_at' => $index === 0 ? $sat->copy()->addDays(6) : null,
                ]);
            }
        }
    }

    /**
     * Berita Acara, taken by the ketua penguji on the score as it stood.
     */
    private function seedMinutes(ResearchExam $exam, Lecturer $chair, CarbonInterface $sat): void
    {
        $grade = app(ComposeFinalGrade::class)->handle($exam);

        $minutes = ExamMinutes::create([
            'exam_type' => $exam->getMorphClass(),
            'exam_id' => $exam->id,
            'decided_by' => $chair->user_id,
            'decision' => ExamResult::ConditionalPass,
            'exam_score' => $grade['exam_score'],
            'supervision_score' => $grade['supervision_score'],
            'final_score' => $grade['final_score'],
            'letter' => $grade['letter'],
            'revision_days' => 14,
            'notes' => 'Dinyatakan lulus dengan perbaikan; revisi diserahkan paling lambat dua minggu setelah sidang.',
            'decided_at' => $sat->copy()->addMinutes(100),
        ]);

        $exam->update([
            'result' => ExamResult::ConditionalPass,
            'final_score' => $grade['final_score'],
            'status' => ExamStatus::Scored,
            'completed_at' => $sat->copy()->addMinutes(90),
        ]);

        AuditLog::record($minutes, AuditAction::MinutesDecided, $chair->user, changes: [
            'decision' => ExamResult::ConditionalPass->value,
            'final_score' => $grade['final_score'],
        ]);
    }

    /**
     * The program studi publishing the result, which is also what locks it.
     */
    private function releaseExam(ResearchExam $exam, User $admin, CarbonInterface $sat): void
    {
        $exam->update([
            'status' => ExamStatus::Released,
            'released_at' => $sat->copy()->addDays(1),
            'released_by' => $admin->id,
        ]);

        app(BuildVerificationQr::class)->tokenFor($exam->refresh());

        AuditLog::record($exam, AuditAction::ExamReleased, $admin);
    }

    /**
     * The published rubrics a prodi marks with, one per exam stage. Without
     * one,
     * no dosen can open the penilaian form at all.
     */
    private function seedRubric(Department $department): Rubric
    {
        $rubric = Rubric::firstOrCreate(
            ['department_id' => $department->id, 'name' => 'Rubrik Ujian — Genap 2025/2026'],
            [
                'description' => 'Rubrik standar semester genap 2025/2026.',
                'status' => RubricStatus::Published,
                'published_at' => now()->startOfYear()->addDays(19),
            ],
        );

        $criteria = [
            ['Kualitas Proposal', 'kualitas_proposal', 30],
            ['Metodologi', 'metodologi', 30],
            ['Presentasi', 'presentasi', 20],
            ['Penguasaan Materi', 'penguasaan_materi', 20],
        ];

        foreach ($criteria as $order => [$label, $key, $weight]) {
            $rubric->criteria()->firstOrCreate(
                ['key' => $key],
                [
                    'label' => $label,
                    'weight' => $weight,
                    // The demo prodi marks presentation out of 10 and the rest
                    // out of 100, so the mixed-scale case has somewhere to show.
                    'max_score' => $key === 'presentasi' ? 10 : 100,
                    'order' => $order + 1,
                ],
            );
        }

        // Ujian proposal uses the prodi's own weighted rubric; ujian hasil uses
        // the faculty's block-additive form. Both schemes are then live, which
        // is the point of letting a prodi choose between them.
        app(ThesisExamRubricSeeder::class)->run($department);

        $facultyRubric = Rubric::query()
            ->where('department_id', $department->id)
            ->where('name', ThesisExamRubricBlueprint::NAME)
            ->firstOrFail();

        $assignments = [
            [ExamStage::Proposal, $rubric, 65],
            [ExamStage::Research, $facultyRubric, 70],
        ];

        foreach ($assignments as [$stage, $assigned, $threshold]) {
            DepartmentRubric::firstOrCreate(
                ['department_id' => $department->id, 'exam_stage' => $stage],
                [
                    'rubric_id' => $assigned->id,
                    'pass_threshold' => $threshold,
                    'is_active' => true,
                    'effective_from' => now()->startOfYear(),
                ],
            );
        }

        return $rubric->load('criteria');
    }
}
