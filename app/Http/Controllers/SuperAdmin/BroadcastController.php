<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\System\SendBroadcast;
use App\Concerns\FormatsIndonesianDates;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Broadcast;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Siaran: an announcement delivered to the notification bell of everybody in
 * an audience.
 */
class BroadcastController extends Controller
{
    use FormatsIndonesianDates;

    public function index(): Response
    {
        Gate::authorize('manage', Broadcast::class);

        $broadcasts = Broadcast::query()
            ->with(['sender', 'department'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (Broadcast $broadcast): array => [
                'id' => $broadcast->id,
                'title' => $broadcast->title,
                'message' => $broadcast->message,
                'url' => $broadcast->url,
                'audience' => $broadcast->audienceLabel(),
                'recipients' => $broadcast->recipients_count,
                'sender' => $broadcast->sender?->name,
                'sent_at' => $this->shortDateTime($broadcast->created_at),
            ]);

        return Inertia::render('superadmin/broadcasts/index', ['broadcasts' => $broadcasts]);
    }

    /**
     * The audience travels in the query string, so the form can ask how many
     * people a choice reaches before anything is sent.
     */
    public function create(Request $request, SendBroadcast $send): Response
    {
        Gate::authorize('manage', Broadcast::class);

        [$role, $department] = $this->audience($request);

        return Inertia::render('superadmin/broadcasts/create', [
            'roles' => array_map(
                fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                SendBroadcast::AUDIENCE_ROLES,
            ),
            'departments' => Department::query()->orderBy('name')->get()
                ->map(fn (Department $department): array => ['id' => $department->id, 'name' => $department->name]),
            'audience' => [
                'role' => $role === null ? 'all' : $role->value,
                'department' => $department === null ? 'all' : (string) $department->id,
            ],
            'recipientCount' => $send->recipients($request->user(), $role, $department)->count(),
        ]);
    }

    public function store(Request $request, SendBroadcast $send): RedirectResponse
    {
        Gate::authorize('manage', Broadcast::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
            // A page of this application or a full address — nothing a browser
            // would run, such as a javascript: link.
            'url' => ['nullable', 'string', 'max:255', 'regex:#^(/(?!/)|https?://)#i'],
        ], [
            'url.regex' => 'Tautan harus berupa alamat lengkap (https://…) atau halaman aplikasi ini (/…).',
        ], [
            'title' => 'judul',
            'message' => 'isi siaran',
            'url' => 'tautan',
        ]);

        [$role, $department] = $this->audience($request);

        if (! $send->recipients($request->user(), $role, $department)->exists()) {
            throw ValidationException::withMessages([
                'audience' => 'Tidak ada pengguna pada sasaran yang dipilih.',
            ]);
        }

        $broadcast = $send->handle($request->user(), $validated, $role, $department);

        AuditLog::record(
            $broadcast,
            AuditAction::BroadcastSent,
            $request->user(),
            changes: [
                'name' => $broadcast->title,
                'sasaran' => $broadcast->audienceLabel(),
                'penerima' => $broadcast->recipients_count,
            ],
            request: $request,
        );

        return to_route('superadmin.broadcasts.index')
            ->with('success', "Siaran terkirim ke {$broadcast->recipients_count} pengguna.");
    }

    /**
     * @return array{0: UserRole|null, 1: Department|null}
     */
    private function audience(Request $request): array
    {
        $validated = $request->validate([
            'audience_role' => ['nullable', Rule::in(array_column(SendBroadcast::AUDIENCE_ROLES, 'value'))],
            'department_id' => ['nullable', Rule::exists(Department::class, 'id')],
        ], [], [
            'audience_role' => 'peran sasaran',
            'department_id' => 'program studi sasaran',
        ]);

        return [
            isset($validated['audience_role']) ? UserRole::from($validated['audience_role']) : null,
            isset($validated['department_id'])
                ? Department::query()->whereKey($validated['department_id'])->first()
                : null,
        ];
    }
}
