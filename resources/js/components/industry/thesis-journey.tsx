import { BlueprintCorners } from '@/components/industry/blueprint';

/**
 * The stations a thesis passes through, in order. Drawn as steps because each
 * one is only reached by finishing the one before it.
 */
const STATIONS = [
    'Proposal',
    'Bimbingan',
    'Ujian Proposal',
    'Penelitian',
    'Ujian Hasil',
];

const POINTS = [
    {
        title: 'Bimbingan tercatat',
        body: 'Setiap sesi punya jadwal dan catatannya sendiri, terbaca oleh mahasiswa dan pembimbing.',
    },
    {
        title: 'Syarat ujian jelas',
        body: 'Jumlah bimbingan dan progres logbook dihitung sistem, bukan dikira-kira.',
    },
    {
        title: 'Dokumen dapat diperiksa',
        body: 'Dokumen ujian bertanda tangan dan keasliannya dicek lewat kode QR.',
    },
];

// The drawing's own coordinates: a step every STEP units across, RISE up.
const LEFT = 36;
const STEP = 122;
const RISE = 32;
const FLOOR = 176;
const BASELINE = 212;

const station = (index: number) => ({
    x: LEFT + index * STEP,
    y: FLOOR - index * RISE,
});

/**
 * The route from proposal to the final exam as a technical drawing: the system
 * frames everything with registration marks, so its one illustration is drawn
 * the same way.
 */
function JourneyDrawing() {
    const first = station(0);
    const last = station(STATIONS.length - 1);

    // Across, then up: the line leaves each station level and climbs at the
    // next one.
    const steps = STATIONS.slice(1).reduce((path, _, index) => {
        const to = station(index + 1);

        return `${path} H ${to.x} V ${to.y}`;
    }, `M ${first.x} ${first.y}`);

    return (
        <svg
            viewBox="0 0 580 240"
            className="h-auto w-full"
            role="img"
            aria-label={`Alur tugas akhir: ${STATIONS.join(', ')}.`}
        >
            {/* What has been climbed so far, shaded so the steps read as mass. */}
            <path
                d={`${steps} H ${last.x + 24} V ${BASELINE} H ${LEFT} Z`}
                fill="var(--ind-accent-400)"
                opacity="0.09"
            />

            <line
                x1="12"
                y1={BASELINE}
                x2="568"
                y2={BASELINE}
                stroke="currentColor"
                strokeWidth="1"
                opacity="0.45"
            />

            {STATIONS.map((name, index) => {
                const { x, y } = station(index);

                return (
                    <g key={name}>
                        <line
                            x1={x}
                            y1={y}
                            x2={x}
                            y2={BASELINE + 6}
                            stroke="currentColor"
                            strokeWidth="1"
                            strokeDasharray="2 4"
                            opacity="0.4"
                        />
                        <text
                            x={x}
                            y={BASELINE + 20}
                            textAnchor="middle"
                            fontSize="10"
                            letterSpacing="1"
                            fill="currentColor"
                            opacity="0.55"
                        >
                            {String(index + 1).padStart(2, '0')}
                        </text>
                    </g>
                );
            })}

            <path
                d={`${steps} H ${last.x + 24}`}
                fill="none"
                stroke="var(--ind-accent-300)"
                strokeWidth="1.5"
            />

            {STATIONS.map((name, index) => {
                const { x, y } = station(index);
                const isLast = index === STATIONS.length - 1;

                return (
                    <g key={name}>
                        {isLast && (
                            <circle
                                cx={x}
                                cy={y}
                                r="11"
                                fill="none"
                                stroke="var(--ind-accent-300)"
                                strokeWidth="1"
                                opacity="0.6"
                            />
                        )}
                        <rect
                            x={x - 4}
                            y={y - 4}
                            width="8"
                            height="8"
                            fill={
                                isLast
                                    ? 'var(--ind-accent-200)'
                                    : 'var(--ind-accent-900)'
                            }
                            stroke="var(--ind-accent-200)"
                            strokeWidth="1.5"
                        />
                        <text
                            x={isLast ? x - 14 : x + 10}
                            y={y - 14}
                            textAnchor={isLast ? 'end' : 'start'}
                            fontSize="15"
                            fontWeight="600"
                            fill="currentColor"
                            style={{ fontFamily: 'var(--ind-font-heading)' }}
                        >
                            {name}
                        </text>
                    </g>
                );
            })}
        </svg>
    );
}

/**
 * The half of the sign-in screen that is not the form: what the application is
 * for, said once, to someone who has not signed in yet.
 */
export function ThesisJourney() {
    return (
        <div
            className="relative flex h-full flex-col justify-between gap-[var(--ind-space-8)] p-[clamp(28px,3.4vw,52px)]"
            style={{
                color: 'var(--ind-neutral-100)',
                backgroundColor: 'var(--ind-accent-900)',
                // Drafting paper: a fine grid, ruled heavier every fifth line.
                backgroundImage: [
                    'linear-gradient(color-mix(in srgb, #fff 7%, transparent) 1px, transparent 1px)',
                    'linear-gradient(90deg, color-mix(in srgb, #fff 7%, transparent) 1px, transparent 1px)',
                    'linear-gradient(color-mix(in srgb, #fff 3.5%, transparent) 1px, transparent 1px)',
                    'linear-gradient(90deg, color-mix(in srgb, #fff 3.5%, transparent) 1px, transparent 1px)',
                ].join(', '),
                backgroundSize:
                    '120px 120px, 120px 120px, 24px 24px, 24px 24px',
            }}
        >
            <div className="flex items-baseline justify-between gap-4 text-[11px] tracking-[0.1em] uppercase">
                <span style={{ color: 'var(--ind-accent-300)' }}>
                    Bimbingan Tugas Akhir
                </span>
                <span className="opacity-55">Alur 01–05</span>
            </div>

            {/* Sized against the viewport's height as well as the column's
                width, so the sheet gives way before the words beneath it do. */}
            <div className="ind-bp mx-auto w-full max-w-[min(720px,88vh)] border border-white/15 bg-[color-mix(in_srgb,var(--ind-accent-900)_70%,transparent)] p-[clamp(16px,2.4vw,32px)] [&>.ind-corner]:text-white/60">
                <BlueprintCorners />
                <JourneyDrawing />
            </div>

            <div>
                <h2
                    className="max-w-[22ch] text-[clamp(30px,3.2vw,44px)] text-balance"
                    style={{ marginBottom: 'var(--ind-space-4)' }}
                >
                    Dari proposal sampai ujian hasil, dalam satu alur.
                </h2>
                <p className="m-0 max-w-[56ch] text-[15px] opacity-75">
                    Mahasiswa, pembimbing, penguji, dan program studi bekerja
                    pada catatan yang sama, sehingga tidak ada berkas yang
                    tercecer atau kesepakatan yang hanya tersimpan di
                    percakapan.
                </p>

                {/* The three reasons are the first thing to go on a short
                    screen: the headline has already made the point. */}
                <dl className="mt-[var(--ind-space-8)] grid gap-x-[var(--ind-space-8)] gap-y-[var(--ind-space-4)] border-t border-white/15 pt-[var(--ind-space-6)] xl:grid-cols-3 [@media(max-height:840px)]:hidden">
                    {POINTS.map((point) => (
                        <div key={point.title}>
                            <dt
                                className="text-[16px] font-semibold"
                                style={{
                                    fontFamily: 'var(--ind-font-heading)',
                                }}
                            >
                                {point.title}
                            </dt>
                            <dd className="m-0 mt-1 text-[13px] leading-[1.5] opacity-70">
                                {point.body}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </div>
    );
}
