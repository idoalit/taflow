<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Concerns\FormatsIndonesianDates;
use App\Enums\AuditAction;
use App\Enums\ExamParticipantRole;
use App\Enums\ExamResult;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\Department;
use App\Models\ExamMinutes;
use App\Models\ExamParticipant;
use App\Models\ProposalExam;
use App\Models\ResearchExam;
use App\Models\SystemSetting;
use App\Models\ThesisExam;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Log Aktivitas: the audit trail, read-only. Nothing here can be edited or
 * removed — a trail that its reader could tidy would not be one.
 */
class ActivityLogController extends Controller
{
    use FormatsIndonesianDates;

    /**
     * What the keys of a recorded change are called on screen. A key that is
     * not listed is shown as it was stored.
     */
    private const DETAIL_LABELS = [
        'code' => 'kode',
        'faculty' => 'fakultas',
        'city' => 'kota',
        'description' => 'keterangan',
        'is_active' => 'aktif',
        'email' => 'surel',
        'role' => 'peran',
        'roles' => 'peran',
        'admin_prodi' => 'admin prodi',
        'decision' => 'keputusan',
        'final_score' => 'nilai akhir',
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'actor' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $actor = trim((string) ($filters['actor'] ?? ''));

        $logs = AuditLog::query()
            ->with(['user', 'auditable'])
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($actor !== '', fn (Builder $query) => $query->whereHas(
                'user',
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$actor}%")
                    ->orWhereLike('email', "%{$actor}%"),
            ))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        // An exam is named by its student, two relations away from the entry.
        $logs->getCollection()->loadMorph('auditable', [
            ProposalExam::class => ['student.user'],
            ResearchExam::class => ['student.user'],
        ]);

        $logs->through(fn (AuditLog $log): array => [
            'id' => $log->id,
            'action_label' => AuditAction::tryFrom($log->action)?->label() ?? $log->action,
            'actor' => $log->user?->name,
            'actor_email' => $log->user?->email,
            'subject' => $this->subject($log),
            'reason' => $log->reason,
            'changes' => $this->changes($log),
            'ip_address' => $log->ip_address,
            'time' => $this->shortDateTime($log->created_at),
        ]);

        return Inertia::render('superadmin/activity-logs/index', [
            'logs' => $logs,
            'filters' => [
                'action' => $filters['action'] ?? 'all',
                'actor' => $actor,
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'actions' => array_map(
                fn (AuditAction $action): array => ['value' => $action->value, 'label' => $action->label()],
                AuditAction::cases(),
            ),
        ]);
    }

    /**
     * What the entry is about, in words. A subject that has since been deleted
     * is named from the snapshot the entry kept of it.
     */
    private function subject(AuditLog $log): string
    {
        $subject = $log->auditable;

        $kind = match (true) {
            $subject instanceof ThesisExam => $subject->stage()->title(),
            $subject instanceof ExamMinutes => 'Berita Acara Ujian',
            $subject instanceof ExamParticipant => 'Panel Ujian',
            $subject instanceof SystemSetting => 'Pengaturan Sistem',
            default => match ($log->auditable_type) {
                (new User)->getMorphClass() => 'Akun',
                (new Department)->getMorphClass() => 'Program Studi',
                (new Role)->getMorphClass() => 'Peran',
                (new Broadcast)->getMorphClass() => 'Siaran',
                default => 'Data',
            },
        };

        $name = match (true) {
            $subject instanceof ThesisExam => $subject->student->user->name,
            $subject instanceof User, $subject instanceof Department => $subject->name,
            $subject instanceof Role => UserRole::tryFrom($subject->name)?->label() ?? $subject->name,
            $subject instanceof Broadcast => $subject->title,
            default => $log->changes['name'] ?? null,
        };

        return is_string($name) && $name !== '' ? "{$kind} · {$name}" : $kind;
    }

    /**
     * The recorded detail as label–value lines, in the words the rest of the
     * interface uses. The name is left out: it is already in the subject.
     *
     * @return array<int, array{key: string, value: string}>
     */
    private function changes(AuditLog $log): array
    {
        return collect($log->changes ?? [])
            ->except('name')
            ->map(fn (mixed $value, string $key): array => [
                'key' => self::DETAIL_LABELS[$key] ?? str($key)->replace('_', ' ')->toString(),
                'value' => $this->detail($key, $value),
            ])
            ->values()
            ->all();
    }

    private function detail(string $key, mixed $value): string
    {
        // Entries written by the exam screens store the enum's value.
        $label = match (true) {
            ! is_string($value) => null,
            $key === 'decision' => ExamResult::tryFrom($value)?->label(),
            $key === 'role' => ExamParticipantRole::tryFrom($value)?->label(),
            default => null,
        };

        return match (true) {
            $label !== null => $label,
            is_bool($value) => $value ? 'ya' : 'tidak',
            is_array($value) => $value === [] ? '—' : implode(', ', array_map(
                fn (mixed $item): string => is_scalar($item) ? (string) $item : (string) json_encode($item),
                $value,
            )),
            $value === null, $value === '' => '—',
            is_scalar($value) => (string) $value,
            default => (string) json_encode($value),
        };
    }
}
