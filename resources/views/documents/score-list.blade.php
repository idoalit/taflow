{{-- 5. Daftar Nilai Ujian Tugas Akhir --}}
<h1>Daftar Nilai {{ $exam['stage'] }}</h1>

<p style="margin-bottom:14px">
    Berdasarkan Keputusan Rapat Dewan Penguji Pada Hari {{ $exam['date_in_words']['day'] }}
    Tanggal {{ $exam['date_in_words']['date'] }} Bulan {{ $exam['date_in_words']['month'] }}
    Tahun {{ $exam['date_in_words']['year'] }} di {{ $exam['city'] }}, ditetapkan bahwa:
</p>

@include('documents.partials.identity', [
    'student' => $student, 'exam' => $exam, 'withProgramme' => true, 'withTitle' => true,
])

<p style="margin-bottom:6px"><strong>A. Nilai Ujian</strong></p>
<table class="data">
    <thead>
        <tr>
            <th class="num">No</th>
            <th>Dewan Penguji</th>
            <th style="width:130px">Tanda Tangan</th>
            <th class="score">Total Nilai</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($examiners as $index => $form)
            <tr>
                <td class="num">{{ $index + 1 }}</td>
                <td>{{ $form['lecturer'] }}</td>
                <td style="height:42px">
                    @if (! empty($signatures[$form['lecturer']]))
                        <img src="{{ $signatures[$form['lecturer']] }}" style="height:36px" alt="">
                    @endif
                </td>
                <td class="score">{{ number_format((float) $form['total'], 2, ',', '') }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="num subtotal"></td>
            <td class="subtotal" colspan="2">Jumlah</td>
            <td class="score subtotal">
                {{ number_format(collect($examiners)->sum(fn ($f) => (float) $f['total']), 2, ',', '') }}
            </td>
        </tr>
        <tr>
            <td class="num subtotal"></td>
            <td class="subtotal" colspan="2">Rerata ({{ $grade['examiner_count'] }} penguji)</td>
            <td class="score subtotal">{{ number_format((float) $grade['exam_score'], 2, ',', '') }}</td>
        </tr>
    </tbody>
</table>

<p style="margin-bottom:6px"><strong>B. Nilai Akhir</strong></p>
<table class="data">
    <thead>
        <tr>
            <th>Komponen</th>
            <th class="score">Nilai</th>
            <th class="score">Bobot</th>
            <th class="score">Nilai × Bobot</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Nilai Ujian</td>
            <td class="score">{{ number_format((float) $grade['exam_score'], 2, ',', '') }}</td>
            <td class="score">{{ rtrim(rtrim(number_format($grade['exam_weight'], 2, ',', ''), '0'), ',') }}</td>
            <td class="score">{{ number_format((float) $grade['exam_score'] * $grade['exam_weight'], 2, ',', '') }}</td>
        </tr>
        <tr>
            <td>Nilai Proses Bimbingan</td>
            <td class="score">{{ number_format((float) $grade['supervision_score'], 2, ',', '') }}</td>
            <td class="score">{{ rtrim(rtrim(number_format($grade['supervision_weight'], 2, ',', ''), '0'), ',') }}</td>
            <td class="score">{{ number_format((float) $grade['supervision_score'] * $grade['supervision_weight'], 2, ',', '') }}</td>
        </tr>
        <tr>
            <td class="subtotal" colspan="3">NILAI AKHIR</td>
            <td class="score subtotal">
                {{ number_format((float) $grade['final_score'], 2, ',', '') }}@if ($grade['letter']) ({{ $grade['letter'] }})@endif
            </td>
        </tr>
    </tbody>
</table>

<p class="note">
    Lama Perbaikan {{ $minutes['revision_days'] ?? '……' }} Hari.
</p>

@include('documents.partials.verify', ['verification' => $verification])
