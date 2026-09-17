/** Shapes shared by the FiPro thesis-supervision screens. */

/** Per-program-studi rules the interface branches on. */
export type ThesisSettings = {
    requiresProposalExam: boolean;
};

export type NotificationCategory =
    | 'approval'
    | 'schedule'
    | 'reminder'
    | 'document';

export type AppNotification = {
    id: string;
    category: NotificationCategory;
    category_label: string;
    tone: 'success' | 'info' | 'warning' | 'neutral';
    title: string;
    message: string;
    url: string | null;
    time: string;
    sent_at: string;
    unread: boolean;
};

export type NotificationFeed = {
    unread: number;
    recent: AppNotification[];
};

export type StatusCategory =
    | 'success'
    | 'warning'
    | 'error'
    | 'info'
    | 'neutral';

export type GuidanceStage = 'proposal' | 'research';
export type GuidanceType = 'face_to_face' | 'online_async';
export type MeetingType = 'offline' | 'online_meeting';
export type GuidanceStatus = 'ongoing' | 'completed' | 'cancelled';
export type ApprovalStatus = 'pending' | 'approved' | 'rejected';
export type ProposalStatus =
    | 'submitted'
    | 'under_review'
    | 'needs_revision'
    | 'approved'
    | 'rejected';

export type LecturerSummary = {
    id: number;
    name: string;
    initials: string;
    position_label: string;
    academic_position: string | null;
};

export type StudentSummary = {
    id: number;
    name: string;
    initials: string;
    student_number: string;
    department: string;
};

export type ProposalSummary = {
    id: number;
    title: string;
    student?: { name: string; student_number: string };
    status: ProposalStatus;
    status_label: string;
    submitted_at: string;
    submitted_at_short: string;
};

export type ProposalDetail = ProposalSummary & {
    background: string;
    problem_formulation: string;
    objectives: string;
    scope: string | null;
    methodology: string | null;
    roadmap_alignment: string | null;
    document: DocumentFile | null;
    approved_at: string | null;
    rejection_reason: string | null;
    student: StudentSummary;
    supervisors: LecturerSummary[];
    alignment_summary: string | null;
    history: TimelineEntry[];
};

export type DocumentFile = {
    name: string;
    kind: string;
    size: string;
    uploaded_at: string;
    url: string;
    previewable: boolean;
    preview_url: string | null;
};

export type TimelineEntry = {
    label: string;
    detail: string;
    current: boolean;
};

export type GuidanceSessionSummary = {
    id: number;
    title: string;
    student?: { name: string; student_number: string };
    meta: string;
    stage: GuidanceStage;
    stage_label: string;
    guidance_type: GuidanceType;
    guidance_type_label: string;
    status: 'scheduled' | 'ongoing' | 'completed' | 'cancelled';
    status_label: string;
};

export type GuidanceMessage = {
    id: number;
    body: string;
    author_name: string;
    sent_at: string;
    is_mine: boolean;
    attachment: DocumentFile | null;
};

export type GuidanceSessionDetail = GuidanceSessionSummary & {
    notes: string | null;
    feedback: string | null;
    completion_notes: string | null;
    meeting_type_label: string | null;
    location_label: string | null;
    location_value: string | null;
    scheduled_at: string | null;
    created_at: string;
    completed_at: string | null;
    cancellation_reason: string | null;
    target_type_label: string;
    student: StudentSummary;
    supervisors: LecturerSummary[];
    document: DocumentFile | null;
    messages: GuidanceMessage[];
};

export type LogbookEntry = {
    id: number;
    day: string;
    month_year: string;
    weekday: string;
    progress: number;
    description: string;
    attachment: DocumentFile | null;
};

export type EligibilityCheck = {
    key: string;
    label: string;
    met: boolean;
    detail: string;
};

export type Eligibility = {
    eligible: boolean;
    checks: EligibilityCheck[];
    summary: string;
};

export type ExamRequestDocument = {
    name: string;
    kind: string;
    previewable: boolean;
    url: string | null;
    preview_url: string | null;
};

export type ExamRequestSummary = {
    id: number;
    status: ApprovalStatus;
    status_label: string;
    awaiting_label: string;
    submitted_at: string;
    verified_at: string | null;
    student_notes: string | null;
    documents: ExamRequestDocument[];
    exam: ExamSummary | null;
};

export type ExamStage = 'proposal' | 'hasil';

export type ThesisExamSummary = {
    id: number;
    stage: ExamStage;
    stage_label: string;
    title: string;
    student: string;
    student_number: string;
    scheduled_at: string;
    scheduled_at_short: string;
    venue: string | null;
    status: string;
    status_label: string;
    result_label: string | null;
    my_evaluation: string;
    show_url: string;
};

export type ThesisExamPanelMember = {
    lecturer_id: number;
    name: string;
    initials: string;
    role_label: string;
    evaluated: boolean;
    score: string | null;
    result_label: string | null;
};

export type ThesisExamDetail = ThesisExamSummary & {
    description: string | null;
    preparation_notes: string | null;
    final_score: string | null;
    department: string;
    panel: ThesisExamPanelMember[];
    my_feedback: string | null;
    evaluation_count: number;
    panel_size: number;
    evaluate_url: string;
    rubric_name: string | null;
    pass_threshold: number | null;
    feedback: {
        lecturer: string;
        body: string;
        result_label: string;
        scores: { label: string; weight: number; score: string }[];
    }[];
};

export type RubricCriterion = {
    key: string;
    label: string;
    /** Null under the block-additive scheme, where the maxima carry the weight. */
    weight: number | null;
    max_score: number;
    item_number: number | null;
};

export type RubricBlock = {
    code: string | null;
    label: string | null;
    max: number;
    subblocks: {
        code: string | null;
        label: string | null;
        max: number;
        items: RubricCriterion[];
    }[];
};

export type ExamRubric = {
    name: string;
    description: string | null;
    /** A weighted percentage, so the bar is read out of 100 whatever the scales. */
    pass_threshold: number | null;
    block_additive: boolean;
    blocks: RubricBlock[];
    criteria: RubricCriterion[];
};

export type ExamSummary = {
    id: number;
    title: string;
    scheduled_at: string;
    venue: string | null;
    status_label: string;
    result_label: string | null;
};

export type AdminStudentRow = {
    id: number;
    name: string;
    email: string;
    student_number: string;
    enrollment_year: number;
    status: string;
    status_label: string;
    stage_label: string;
    supervisors: string[];
    examiners: string[];
    has_supervisor: boolean;
    sessions_completed: number;
    sessions_required: number;
    progress_percent: number;
    proposal_status_label: string;
    proposal_title: string | null;
};

export type AdminStudentDetail = AdminStudentRow & {
    department: string;
    initials: string;
    assignments: {
        lecturer_id: number;
        name: string;
        role: string;
        role_label: string;
    }[];
};

export type AdminLecturerRow = {
    id: number;
    name: string;
    email: string;
    lecturer_number: string;
    academic_position: string | null;
    status: string;
    status_label: string;
    max_supervisions: number;
    load: number;
    load_percent: number;
    at_capacity: boolean;
};

export type AdminExamRequestRow = {
    id: number;
    stage: ExamStage;
    stage_label: string;
    student: string;
    student_number: string;
    title: string;
    submitted_at: string;
    status_label: string;
    supervisor_status_label: string;
    admin_status: string;
    admin_status_label: string;
    schedulable: boolean;
    admin_show_url: string;
    documents: ExamRequestDocument[];
};

export type AdminExamRequestDetail = AdminExamRequestRow & {
    department: string;
    supervisor_notes: string | null;
    admin_notes: string | null;
    student_notes: string | null;
    completed_sessions: number;
    required_sessions: number;
};

export type AdminRubricRow = {
    id: number;
    name: string;
    description: string | null;
    status: string;
    status_label: string;
    published_at: string | null;
    scale_label: string;
    scoring_scheme: string;
    scheme_label: string;
    criteria_count: number;
    total_weight: number;
    total_label: string;
    balanced: boolean;
    editable: boolean;
    used: boolean;
    show_url: string;
    edit_url: string;
};

/** The time-of-day greeting every dashboard opens with. */
export type Greeting = {
    salutation: string;
    name: string;
};

/** Outcome of a bulk import, flashed back after the file is processed. */
export type ImportResult = {
    imported: number;
    attached: number;
    failed: number;
    errors: string[];
};
