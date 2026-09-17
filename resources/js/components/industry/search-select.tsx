import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import { cn } from '@/lib/utils';

export type SearchSelectOption<T extends string | number> = {
    value: T;
    label: string;
    /** Second line, and also searchable — a NIM or an email, say. */
    hint?: string;
};

/**
 * Select-with-search, for lists long enough that a native dropdown stops being
 * usable. Filters on both the label and the hint, and is driven by the keyboard
 * as well as the mouse.
 */
export function SearchSelect<T extends string | number>({
    value,
    options,
    onChange,
    placeholder = 'Pilih salah satu',
    searchPlaceholder = 'Cari...',
    emptyLabel = 'Tidak ada data yang cocok.',
    id,
}: {
    value: T | null;
    options: SearchSelectOption<T>[];
    onChange: (value: T) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyLabel?: string;
    id?: string;
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [active, setActive] = useState(0);
    const containerRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);
    const listRef = useRef<HTMLDivElement>(null);

    const selected = options.find((option) => option.value === value) ?? null;

    const matches = useMemo(() => {
        const term = query.trim().toLowerCase();

        if (term === '') {
            return options;
        }

        return options.filter(
            (option) =>
                option.label.toLowerCase().includes(term) ||
                (option.hint?.toLowerCase().includes(term) ?? false),
        );
    }, [options, query]);

    useEffect(() => {
        if (!open) {
            return;
        }

        searchRef.current?.focus();

        const close = (event: MouseEvent) => {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    // Keep the highlighted row in view while arrowing through a long list.
    useEffect(() => {
        listRef.current
            ?.querySelector<HTMLElement>(`[data-index="${active}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    const commit = (option: SearchSelectOption<T>) => {
        onChange(option.value);
        setOpen(false);
        setQuery('');
    };

    const onKeyDown = (event: React.KeyboardEvent) => {
        if (event.key === 'Escape') {
            setOpen(false);

            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            setActive((index) => {
                const next = event.key === 'ArrowDown' ? index + 1 : index - 1;

                return Math.max(0, Math.min(matches.length - 1, next));
            });

            return;
        }

        if (event.key === 'Enter' && matches[active]) {
            event.preventDefault();
            commit(matches[active]);
        }
    };

    return (
        <div className="relative" ref={containerRef}>
            <button
                type="button"
                id={id}
                role="combobox"
                aria-expanded={open}
                aria-haspopup="listbox"
                onClick={() => {
                    setOpen((value) => !value);
                    setActive(0);
                }}
                className="ind-input flex w-full items-center justify-between gap-2 text-left"
            >
                <span
                    className={cn('truncate', selected === null && 'ind-muted')}
                >
                    {selected?.label ?? placeholder}
                </span>
                <ChevronsUpDown
                    size={15}
                    strokeWidth={1.5}
                    className="flex-shrink-0"
                />
            </button>

            {open && (
                <div
                    className="ind-bp absolute top-[calc(100%+6px)] right-0 left-0 z-90 border"
                    style={{
                        background: 'var(--ind-bg)',
                        borderColor: 'var(--ind-divider)',
                        boxShadow: 'var(--ind-shadow-lg)',
                    }}
                >
                    <BlueprintCorners />

                    <div
                        className="flex items-center gap-2 px-2"
                        style={{ borderBottom: '1px solid var(--ind-divider)' }}
                    >
                        <Search
                            size={15}
                            strokeWidth={1.5}
                            className="ind-muted flex-shrink-0"
                        />
                        <input
                            ref={searchRef}
                            value={query}
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setActive(0);
                            }}
                            onKeyDown={onKeyDown}
                            placeholder={searchPlaceholder}
                            className="w-full border-0 bg-transparent py-2 text-[13.5px] outline-none"
                            style={{ fontFamily: 'var(--ind-font-body)' }}
                        />
                    </div>

                    <div
                        className="max-h-[260px] overflow-x-hidden overflow-y-auto"
                        role="listbox"
                        ref={listRef}
                    >
                        {matches.length === 0 ? (
                            <p className="ind-muted m-0 px-3 py-4 text-center text-[13px]">
                                {emptyLabel}
                            </p>
                        ) : (
                            matches.map((option, index) => {
                                const isSelected = option.value === value;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="option"
                                        aria-selected={isSelected}
                                        data-index={index}
                                        onMouseEnter={() => setActive(index)}
                                        onClick={() => commit(option)}
                                        className="flex w-full cursor-pointer items-center gap-2 border-0 px-3 py-2 text-left"
                                        style={{
                                            background:
                                                index === active
                                                    ? 'var(--ind-accent-100)'
                                                    : 'transparent',
                                            fontFamily: 'var(--ind-font-body)',
                                        }}
                                    >
                                        <Check
                                            size={14}
                                            strokeWidth={1.5}
                                            className="flex-shrink-0"
                                            style={{
                                                opacity: isSelected ? 1 : 0,
                                                color: 'var(--ind-accent-700)',
                                            }}
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-[13.5px]">
                                                {option.label}
                                            </span>
                                            {option.hint && (
                                                <span className="ind-muted block truncate text-[12px]">
                                                    {option.hint}
                                                </span>
                                            )}
                                        </span>
                                    </button>
                                );
                            })
                        )}
                    </div>

                    {options.length > 8 && (
                        <div
                            className="ind-muted px-3 py-[6px] text-[11px]"
                            style={{
                                borderTop: '1px solid var(--ind-divider)',
                            }}
                        >
                            {matches.length} dari {options.length} data
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
