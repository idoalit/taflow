{{-- 2. Form Penilaian Ujian Tugas Akhir — one per examiner --}}
<h1>Form Penilaian {{ $exam['stage'] }}</h1>
@if ($exam['rubric'])<p class="subtitle">{{ $exam['rubric'] }}</p>@endif

@include('documents.partials.identity', [
    'student' => $student, 'exam' => $exam, 'withProgramme' => true, 'withTitle' => true,
])

@if ($form['block_additive'])
    {{-- The faculty's block form: items grouped, a subtotal per block. --}}
    <table class="data">
        <thead>
            <tr>
                <th class="num">No</th>
                <th>Aspek yang Dinilai</th>
                <th class="score">Skor Maksimal</th>
                <th class="score">Skor</th>
            </tr>
        </thead>
        <tbody>
            @php $lastBlock = null; $lastSub = null; $number = 0; @endphp
            @foreach ($form['criteria'] as $criterion)
                @if ($criterion->block_code !== $lastBlock)
                    @php $lastBlock = $criterion->block_code; $lastSub = null; $number = 0; @endphp
                    <tr>
                        <td class="num"><strong>{{ $criterion->block_code }}</strong></td>
                        <td colspan="3"><strong>{{ $criterion->block_label }}</strong></td>
                    </tr>
                @endif

                @if ($criterion->subblock_code && $criterion->subblock_code !== $lastSub)
                    @php $lastSub = $criterion->subblock_code; $number = 0; @endphp
                    <tr>
                        <td class="num">{{ $criterion->subblock_code }}</td>
                        <td colspan="3"><em>{{ $criterion->subblock_label }}</em></td>
                    </tr>
                @endif

                <tr>
                    <td class="num">{{ ++$number }}</td>
                    <td>{{ $criterion->label }}</td>
                    <td class="score">{{ $criterion->max_score }}</td>
                    <td class="score">{{ rtrim(rtrim(number_format((float) ($form['marks'][$criterion->key] ?? 0), 2, ',', ''), '0'), ',') }}</td>
                </tr>

                @if ($loop->last || $form['criteria'][$loop->index + 1]->block_code !== $lastBlock)
                    @php $block = collect($form['blocks'])->firstWhere('code', $lastBlock); @endphp
                    <tr>
                        <td class="num subtotal"></td>
                        <td class="subtotal">JUMLAH {{ $lastBlock }}</td>
                        <td class="score subtotal">{{ $block['max'] ?? '' }}</td>
                        <td class="score subtotal">{{ rtrim(rtrim(number_format((float) ($block['earned'] ?? 0), 2, ',', ''), '0'), ',') }}</td>
                    </tr>
                @endif
            @endforeach

            <tr>
                <td class="num subtotal"></td>
                <td class="subtotal">TOTAL NILAI</td>
                <td class="score subtotal">100</td>
                <td class="score subtotal">{{ number_format((float) $form['total'], 2, ',', '') }}</td>
            </tr>
        </tbody>
    </table>
@else
    {{-- A weighted rubric: each criterion earns a share of 100 rather than
         adding to it, so the form shows the weight instead of a subtotal. --}}
    <table class="data">
        <thead>
            <tr>
                <th class="num">No</th>
                <th>Aspek yang Dinilai</th>
                <th class="score">Bobot</th>
                <th class="score">Skor Maksimal</th>
                <th class="score">Skor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($form['criteria'] as $criterion)
                <tr>
                    <td class="num">{{ $loop->iteration }}</td>
                    <td>{{ $criterion->label }}</td>
                    <td class="score">{{ $criterion->weight }}%</td>
                    <td class="score">{{ $criterion->max_score }}</td>
                    <td class="score">{{ rtrim(rtrim(number_format((float) ($form['marks'][$criterion->key] ?? 0), 2, ',', ''), '0'), ',') }}</td>
                </tr>
            @endforeach

            <tr>
                <td class="num subtotal"></td>
                <td class="subtotal" colspan="3">TOTAL NILAI (persentase terbobot)</td>
                <td class="score subtotal">{{ number_format((float) $form['total'], 2, ',', '') }}</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($form['feedback'])
    <p class="note"><strong>Umpan balik:</strong> {{ $form['feedback'] }}</p>
@endif

<table class="sign">
    <tr>
        <td style="width:55%"></td>
        @include('documents.partials.signature', [
            'place' => $exam['signed_on'],
            'role' => 'Penguji',
            'name' => $form['lecturer'],
            'identifier' => $form['lecturer_number'],
            'image' => $signature,
        ])
    </tr>
</table>

@include('documents.partials.verify', ['verification' => $verification])
