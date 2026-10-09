import type { DepartmentOption } from '@/types';

/**
 * The program studi an Admin Prodi runs — usually one, sometimes several, so a
 * checklist rather than a single select.
 */
export function DepartmentChecklist({
    departments,
    value,
    onChange,
    error,
}: {
    departments: DepartmentOption[];
    value: number[];
    onChange: (value: number[]) => void;
    error?: string;
}) {
    return (
        <fieldset className="m-0 border-0 p-0">
            <legend className="ind-label">Program Studi yang Dikelola</legend>

            {departments.length === 0 ? (
                <p className="ind-muted m-0 text-[12.5px]">
                    Belum ada program studi. Buat program studi lebih dulu.
                </p>
            ) : (
                <div className="flex flex-col gap-[var(--ind-space-2)]">
                    {departments.map((department) => (
                        <label key={department.id} className="ind-check">
                            <input
                                type="checkbox"
                                checked={value.includes(department.id)}
                                onChange={(event) =>
                                    onChange(
                                        event.target.checked
                                            ? [...value, department.id]
                                            : value.filter(
                                                  (id) => id !== department.id,
                                              ),
                                    )
                                }
                            />
                            {department.name}
                            {!department.is_active && (
                                <span className="ind-muted">
                                    {' '}
                                    (tidak aktif)
                                </span>
                            )}
                        </label>
                    ))}
                </div>
            )}

            {error && (
                <p
                    className="mt-[5px] mb-0 text-[11.5px]"
                    style={{ color: 'var(--ind-status-error-fg)' }}
                >
                    {error}
                </p>
            )}
        </fieldset>
    );
}
