/** Shapes shared by the Superadmin screens. */

/** A server-paged list, as Laravel's paginator serialises it. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type SelectOption = { value: string; label: string };

export type DepartmentOption = { id: number; name: string; is_active: boolean };

export type DepartmentRow = {
    id: number;
    name: string;
    code: string;
    faculty: string | null;
    city: string | null;
    description: string | null;
    is_active: boolean;
    status_label: string;
    students: number;
    lecturers: number;
    admins: number;
    workspace: string;
};

export type AccountRow = {
    id: number;
    name: string;
    email: string;
    initials: string;
    roles: string[];
    departments: string[];
};

/** The rules a program studi sets for its tugas akhir. */
export type ThesisRuleValues = {
    requires_proposal_exam: boolean;
    min_proposal_guidance_sessions: number;
    min_research_guidance_sessions: number;
    min_logbook_progress: number;
    max_supervisors_per_student: number;
    guidance_slot_minutes: number;
    perception_alignment_mode: string;
};
