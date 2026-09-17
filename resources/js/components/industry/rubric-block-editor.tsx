import { ChevronDown, ChevronUp, Trash2 } from 'lucide-react';
import { Btn, Field, Input } from '@/components/industry/ui';

export type BlockItem = { label: string; max_score: string };
export type SubBlock = { label: string; items: BlockItem[] };
export type Block = { label: string; subblocks: SubBlock[] };

export const BLANK_ITEM: BlockItem = { label: '', max_score: '' };
export const BLANK_BLOCK: Block = {
    label: '',
    subblocks: [{ label: '', items: [{ ...BLANK_ITEM }] }],
};

/** Mirrors RubricCriterion::blockCodeFor — A, B, C … Z, AA. */
export function blockCode(index: number): string {
    let code = '';

    for (let n = index; n >= 0; n = Math.floor(n / 26) - 1) {
        code = String.fromCharCode(65 + (n % 26)) + code;
    }

    return code;
}

/** Mirrors RubricCriterion::subBlockCodeFor — I, II, III, IV … */
export function subBlockCode(index: number): string {
    const numerals: [number, string][] = [
        [1000, 'M'],
        [900, 'CM'],
        [500, 'D'],
        [400, 'CD'],
        [100, 'C'],
        [90, 'XC'],
        [50, 'L'],
        [40, 'XL'],
        [10, 'X'],
        [9, 'IX'],
        [5, 'V'],
        [4, 'IV'],
        [1, 'I'],
    ];

    let remaining = index + 1;
    let code = '';

    for (const [value, numeral] of numerals) {
        while (remaining >= value) {
            code += numeral;
            remaining -= value;
        }
    }

    return code;
}

export function subBlockMax(sub: SubBlock): number {
    return sub.items.reduce(
        (sum, item) => sum + (Number(item.max_score) || 0),
        0,
    );
}

export function blockMax(block: Block): number {
    return block.subblocks.reduce((sum, sub) => sum + subBlockMax(sub), 0);
}

/** A sub-block only earns a numeral once it is one of several, or is named. */
function subBlocksAreNamed(block: Block): boolean {
    return (
        block.subblocks.length > 1 || block.subblocks[0]?.label.trim() !== ''
    );
}

function move<T>(list: T[], from: number, to: number): T[] {
    if (to < 0 || to >= list.length) {
        return list;
    }

    const next = [...list];
    const [moved] = next.splice(from, 1);
    next.splice(to, 0, moved);

    return next;
}

/** Reorder controls, as buttons so they work by keyboard and by touch. */
function MoveButtons({
    index,
    count,
    onMove,
    label,
}: {
    index: number;
    count: number;
    onMove: (to: number) => void;
    label: string;
}) {
    return (
        <span className="flex">
            <button
                type="button"
                className="ind-btn ind-btn-ghost ind-btn-icon"
                aria-label={`Naikkan ${label}`}
                disabled={index === 0}
                onClick={() => onMove(index - 1)}
            >
                <ChevronUp size={15} strokeWidth={1.5} />
            </button>
            <button
                type="button"
                className="ind-btn ind-btn-ghost ind-btn-icon"
                aria-label={`Turunkan ${label}`}
                disabled={index === count - 1}
                onClick={() => onMove(index + 1)}
            >
                <ChevronDown size={15} strokeWidth={1.5} />
            </button>
        </span>
    );
}

/**
 * Edits a rubric the shape its printed form has: blocks, optionally divided
 * into sub-blocks, each holding the items that are actually marked.
 *
 * Codes are shown but never typed — they follow position, so reordering is the
 * only way to change them, and there is no way to end up with two blocks
 * called B. Subtotals are live so the 100 the scheme requires is reached
 * deliberately rather than discovered on submit.
 */
export function RubricBlockEditor({
    blocks,
    onChange,
    errors,
}: {
    blocks: Block[];
    onChange: (blocks: Block[]) => void;
    errors: Record<string, string>;
}) {
    const patchBlock = (index: number, patch: Partial<Block>) =>
        onChange(blocks.map((b, i) => (i === index ? { ...b, ...patch } : b)));

    const patchSub = (bi: number, si: number, patch: Partial<SubBlock>) =>
        patchBlock(bi, {
            subblocks: blocks[bi].subblocks.map((s, i) =>
                i === si ? { ...s, ...patch } : s,
            ),
        });

    const patchItem = (
        bi: number,
        si: number,
        ii: number,
        patch: Partial<BlockItem>,
    ) =>
        patchSub(bi, si, {
            items: blocks[bi].subblocks[si].items.map((it, i) =>
                i === ii ? { ...it, ...patch } : it,
            ),
        });

    return (
        <div className="flex flex-col gap-[var(--ind-space-6)]">
            {blocks.map((block, bi) => {
                const named = subBlocksAreNamed(block);

                return (
                    // Blocks are positional while they are being edited.
                    <div
                        key={bi}
                        className="ind-card ind-bp flex flex-col gap-[var(--ind-space-4)]"
                    >
                        <i className="ind-corner tl" />
                        <i className="ind-corner tr" />
                        <i className="ind-corner bl" />
                        <i className="ind-corner br" />

                        <div className="flex flex-wrap items-end gap-[var(--ind-space-3)]">
                            <span className="ind-rubric-code">
                                {blockCode(bi)}
                            </span>

                            <div className="min-w-[220px] flex-1">
                                <Field label="Nama Blok">
                                    <Input
                                        value={block.label}
                                        placeholder="TATA PENULISAN"
                                        onChange={(e) =>
                                            patchBlock(bi, {
                                                label: e.target.value,
                                            })
                                        }
                                    />
                                </Field>
                            </div>

                            <span className="ind-muted pb-[10px] text-[12.5px] whitespace-nowrap">
                                Subtotal {blockMax(block)}
                            </span>

                            <MoveButtons
                                index={bi}
                                count={blocks.length}
                                label="blok"
                                onMove={(to) => onChange(move(blocks, bi, to))}
                            />

                            <button
                                type="button"
                                className="ind-btn ind-btn-ghost ind-btn-icon"
                                aria-label="Hapus blok"
                                onClick={() =>
                                    onChange(blocks.filter((_, i) => i !== bi))
                                }
                            >
                                <Trash2 size={16} strokeWidth={1.5} />
                            </button>
                        </div>

                        {block.subblocks.map((sub, si) => (
                            <div
                                key={si}
                                className="ind-rubric-child flex flex-col gap-[var(--ind-space-3)]"
                            >
                                <div className="flex flex-wrap items-end gap-[var(--ind-space-3)]">
                                    {named && (
                                        <span className="ind-rubric-code ind-rubric-code-sub">
                                            {subBlockCode(si)}
                                        </span>
                                    )}

                                    <div className="min-w-[200px] flex-1">
                                        <Field label="Nama Sub-blok" optional>
                                            <Input
                                                value={sub.label}
                                                placeholder="Pendahuluan & Tinjauan Pustaka"
                                                onChange={(e) =>
                                                    patchSub(bi, si, {
                                                        label: e.target.value,
                                                    })
                                                }
                                            />
                                        </Field>
                                    </div>

                                    <span className="ind-muted pb-[10px] text-[12.5px] whitespace-nowrap">
                                        {subBlockMax(sub)}
                                    </span>

                                    {block.subblocks.length > 1 && (
                                        <>
                                            <MoveButtons
                                                index={si}
                                                count={block.subblocks.length}
                                                label="sub-blok"
                                                onMove={(to) =>
                                                    patchBlock(bi, {
                                                        subblocks: move(
                                                            block.subblocks,
                                                            si,
                                                            to,
                                                        ),
                                                    })
                                                }
                                            />

                                            <button
                                                type="button"
                                                className="ind-btn ind-btn-ghost ind-btn-icon"
                                                aria-label="Hapus sub-blok"
                                                onClick={() =>
                                                    patchBlock(bi, {
                                                        subblocks:
                                                            block.subblocks.filter(
                                                                (_, i) =>
                                                                    i !== si,
                                                            ),
                                                    })
                                                }
                                            >
                                                <Trash2
                                                    size={16}
                                                    strokeWidth={1.5}
                                                />
                                            </button>
                                        </>
                                    )}
                                </div>

                                {sub.items.map((item, ii) => (
                                    <div
                                        key={ii}
                                        className="ind-rubric-child flex flex-wrap items-end gap-[var(--ind-space-2)]"
                                    >
                                        <span className="ind-rubric-code ind-rubric-code-item">
                                            {ii + 1}
                                        </span>

                                        <div className="min-w-[220px] flex-[3]">
                                            <Field
                                                label={
                                                    ii === 0
                                                        ? 'Item'
                                                        : undefined
                                                }
                                            >
                                                <Input
                                                    value={item.label}
                                                    placeholder="Sistematika penulisan sesuai dengan buku pedoman"
                                                    onChange={(e) =>
                                                        patchItem(bi, si, ii, {
                                                            label: e.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </Field>
                                        </div>

                                        <div className="w-[110px]">
                                            <Field
                                                label={
                                                    ii === 0
                                                        ? 'Nilai Maks'
                                                        : undefined
                                                }
                                            >
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={100}
                                                    value={item.max_score}
                                                    onChange={(e) =>
                                                        patchItem(bi, si, ii, {
                                                            max_score:
                                                                e.target.value,
                                                        })
                                                    }
                                                />
                                            </Field>
                                        </div>

                                        <MoveButtons
                                            index={ii}
                                            count={sub.items.length}
                                            label="item"
                                            onMove={(to) =>
                                                patchSub(bi, si, {
                                                    items: move(
                                                        sub.items,
                                                        ii,
                                                        to,
                                                    ),
                                                })
                                            }
                                        />

                                        <button
                                            type="button"
                                            className="ind-btn ind-btn-ghost ind-btn-icon"
                                            aria-label="Hapus item"
                                            onClick={() =>
                                                patchSub(bi, si, {
                                                    items: sub.items.filter(
                                                        (_, i) => i !== ii,
                                                    ),
                                                })
                                            }
                                        >
                                            <Trash2
                                                size={16}
                                                strokeWidth={1.5}
                                            />
                                        </button>
                                    </div>
                                ))}

                                <Btn
                                    type="button"
                                    className="self-start"
                                    onClick={() =>
                                        patchSub(bi, si, {
                                            items: [
                                                ...sub.items,
                                                { ...BLANK_ITEM },
                                            ],
                                        })
                                    }
                                >
                                    Tambah Item
                                </Btn>
                            </div>
                        ))}

                        <Btn
                            type="button"
                            className="self-start"
                            onClick={() =>
                                patchBlock(bi, {
                                    subblocks: [
                                        ...block.subblocks,
                                        {
                                            label: '',
                                            items: [{ ...BLANK_ITEM }],
                                        },
                                    ],
                                })
                            }
                        >
                            Tambah Sub-blok
                        </Btn>
                    </div>
                );
            })}

            {errors.blocks && (
                <p
                    className="m-0 text-[12.5px]"
                    style={{ color: 'var(--ind-status-error-fg)' }}
                >
                    {errors.blocks}
                </p>
            )}

            <Btn
                type="button"
                className="self-start"
                onClick={() =>
                    onChange([
                        ...blocks,
                        JSON.parse(JSON.stringify(BLANK_BLOCK)) as Block,
                    ])
                }
            >
                Tambah Blok
            </Btn>
        </div>
    );
}
