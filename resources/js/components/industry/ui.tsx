import { Link, type InertiaLinkProps } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import {
    cloneElement,
    type ComponentProps,
    isValidElement,
    type ReactNode,
    useId,
} from 'react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import { statusCategory } from '@/components/industry/status';
import { InfoTooltip } from '@/components/industry/tooltip';
import { cn } from '@/lib/utils';

type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'danger';

const VARIANT_CLASS: Record<ButtonVariant, string> = {
    primary: 'ind-btn-primary',
    secondary: 'ind-btn-secondary',
    ghost: 'ind-btn-ghost',
    danger: 'ind-btn-danger',
};

/**
 * Primary buttons carry the blueprint corner marks; the quieter variants do
 * not, which is what keeps a row of actions legible.
 */
export function Btn({
    variant = 'secondary',
    block = false,
    className,
    children,
    ...props
}: ComponentProps<'button'> & { variant?: ButtonVariant; block?: boolean }) {
    const framed =
        (variant === 'primary' || variant === 'danger') && !props.disabled;

    return (
        <button
            type="button"
            {...props}
            className={cn(
                'ind-btn',
                VARIANT_CLASS[variant],
                block && 'ind-btn-block',
                framed && 'ind-bp',
                className,
            )}
        >
            {framed && <BlueprintCorners />}
            {children}
        </button>
    );
}

export function BtnLink({
    variant = 'secondary',
    block = false,
    className,
    children,
    ...props
}: InertiaLinkProps & { variant?: ButtonVariant; block?: boolean }) {
    const framed = variant === 'primary';

    return (
        <Link
            {...props}
            className={cn(
                'ind-btn',
                VARIANT_CLASS[variant],
                block && 'ind-btn-block',
                framed && 'ind-bp',
                className,
            )}
        >
            {framed && <BlueprintCorners />}
            {children}
        </Link>
    );
}

/** A boxed surface with registration marks. */
export function Card({
    className,
    children,
    framed = true,
    ...props
}: ComponentProps<'div'> & { framed?: boolean }) {
    return (
        <div
            {...props}
            className={cn('ind-card', framed && 'ind-bp', className)}
        >
            {framed && <BlueprintCorners />}
            {children}
        </div>
    );
}

export function CardKicker({ children }: { children: ReactNode }) {
    return <span className="ind-kicker">{children}</span>;
}

/** Status pill. Pass the human label; the category is derived from it. */
export function Tag({
    label,
    tone,
    className,
}: {
    label: string;
    tone?: 'accent' | 'accent-2' | 'neutral' | 'outline';
    className?: string;
}) {
    const toneClass = tone
        ? `ind-tag-${tone}`
        : `ind-tag-${statusCategory(label)}`;

    return <span className={cn('ind-tag', toneClass, className)}>{label}</span>;
}

/**
 * Omit the label on repeated rows of the same shape: the first row's labels
 * already read as column headers, and repeating them is noise.
 */
export function Field({
    label,
    hint,
    error,
    optional = false,
    children,
}: {
    label?: string;
    hint?: string;
    error?: string;
    optional?: boolean;
    children: ReactNode;
}) {
    const generated = useId();

    // Tie the label to the control it names, so clicking the label focuses the
    // field and a screen reader announces the two together. The id is injected
    // rather than required from every caller, and a control that brings its own
    // id keeps it.
    const single = isValidElement<{ id?: string }>(children) ? children : null;
    const id = single?.props.id ?? generated;
    const control =
        single !== null && single.props.id === undefined
            ? cloneElement(single, { id })
            : children;

    return (
        <div>
            {label !== undefined && (
                <label className="ind-label" htmlFor={single ? id : undefined}>
                    {label}
                    {optional && <span className="ind-muted"> (opsional)</span>}
                </label>
            )}
            {control}
            {hint && !error && (
                <p className="ind-muted mt-[5px] mb-0 text-[11.5px]">{hint}</p>
            )}
            {error && (
                <p
                    className="mt-[5px] mb-0 text-[11.5px]"
                    style={{ color: 'var(--ind-status-error-fg)' }}
                >
                    {error}
                </p>
            )}
        </div>
    );
}

export function Input({ className, ...props }: ComponentProps<'input'>) {
    return <input {...props} className={cn('ind-input', className)} />;
}

export function Textarea({ className, ...props }: ComponentProps<'textarea'>) {
    return <textarea {...props} className={cn('ind-input', className)} />;
}

/** Segmented single-choice control, used heavily across the forms. */
/**
 * The primary switch on a screen: underline tabs, as every role's list screens
 * use. Reach for Seg instead when the choice narrows what a tab already shows.
 */
export function Tabs<T extends string>({
    value,
    options,
    onChange,
    label,
}: {
    value: T;
    options: { value: T; label: string; count?: number }[];
    onChange: (value: T) => void;
    label?: string;
}) {
    return (
        <div className="ind-tabs" role="tablist" aria-label={label}>
            {options.map((option) => (
                <button
                    key={option.value}
                    type="button"
                    role="tab"
                    aria-selected={option.value === value}
                    onClick={() => onChange(option.value)}
                    className="ind-tab"
                >
                    {option.label}
                    {option.count === undefined ? '' : ` (${option.count})`}
                </button>
            ))}
        </div>
    );
}

export function Seg<T extends string>({
    value,
    options,
    onChange,
    full = false,
    name,
}: {
    value: T;
    options: {
        value: T;
        label: string;
        disabled?: boolean;
        reason?: string | null;
    }[];
    onChange: (value: T) => void;
    full?: boolean;
    name?: string;
}) {
    return (
        <div
            className={cn('ind-seg', full && 'flex w-full')}
            role="group"
            aria-label={name}
        >
            {options.map((option) => {
                // aria-disabled rather than disabled: a disabled button emits no
                // pointer events, so its tooltip could never open.
                const button = (
                    <button
                        key={option.value}
                        type="button"
                        aria-pressed={option.value === value}
                        aria-disabled={option.disabled || undefined}
                        onClick={() => {
                            if (!option.disabled) {
                                onChange(option.value);
                            }
                        }}
                        className={cn(
                            'ind-seg-opt',
                            full && 'flex-1 justify-center',
                        )}
                    >
                        {option.disabled && (
                            <Lock size={12} strokeWidth={1.5} />
                        )}
                        {option.label}
                    </button>
                );

                return option.disabled && option.reason ? (
                    <InfoTooltip key={option.value} content={option.reason}>
                        {button}
                    </InfoTooltip>
                ) : (
                    button
                );
            })}
        </div>
    );
}

/** Tables scroll horizontally rather than letting the page do so. */
export function DataTable({
    head,
    children,
}: {
    head: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="ind-table">
                <thead>{head}</thead>
                <tbody>{children}</tbody>
            </table>
        </div>
    );
}

export function EmptyRow({
    colSpan,
    children,
}: {
    colSpan: number;
    children: ReactNode;
}) {
    return (
        <tr>
            <td
                colSpan={colSpan}
                className="ind-muted py-[var(--ind-space-6)] text-center"
            >
                {children}
            </td>
        </tr>
    );
}

/** Square avatar block with the person's initials. */
export function Avatar({
    initials,
    size = 30,
    tone = 'accent',
}: {
    initials: string;
    size?: number;
    tone?: 'accent' | 'accent-2';
}) {
    return (
        <div
            className="ind-bp flex flex-shrink-0 items-center justify-center border"
            style={{
                width: size,
                height: size,
                background: `var(--ind-${tone}-100)`,
                color: `var(--ind-${tone}-800)`,
                borderColor: 'var(--ind-divider)',
                fontFamily: 'var(--ind-font-heading)',
                fontWeight: 600,
                fontSize: Math.round(size * 0.4),
            }}
        >
            <BlueprintCorners />
            {initials}
        </div>
    );
}

export function PageHeader({
    title,
    description,
    actions,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 className="mb-1">{title}</h3>
                {description && <p className="ind-muted m-0">{description}</p>}
            </div>
            {actions && (
                <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                    {actions}
                </div>
            )}
        </div>
    );
}

/**
 * Previous / next for a list paged on the server. The links are the
 * paginator's own, so whatever filters are in the query string ride along.
 */
export function Pagination({
    page,
    unit,
}: {
    page: {
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    unit: string;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <span className="ind-muted text-[12.5px]">
                {page.total === 0
                    ? `Tidak ada ${unit}`
                    : `${page.from}–${page.to} dari ${page.total} ${unit}`}
            </span>

            {page.last_page > 1 && (
                <div className="flex items-center gap-[var(--ind-space-2)]">
                    {page.prev_page_url === null ? (
                        <Btn disabled>Sebelumnya</Btn>
                    ) : (
                        <BtnLink href={page.prev_page_url} preserveScroll>
                            Sebelumnya
                        </BtnLink>
                    )}
                    <span className="ind-muted text-[12.5px]">
                        Halaman {page.current_page} dari {page.last_page}
                    </span>
                    {page.next_page_url === null ? (
                        <Btn disabled>Berikutnya</Btn>
                    ) : (
                        <BtnLink href={page.next_page_url} preserveScroll>
                            Berikutnya
                        </BtnLink>
                    )}
                </div>
            )}
        </div>
    );
}

export function SectionHeading({ children }: { children: ReactNode }) {
    return <h6 className="mb-[var(--ind-space-3)]">{children}</h6>;
}
