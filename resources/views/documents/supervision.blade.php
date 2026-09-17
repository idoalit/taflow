{{-- 4. Form Penilaian Proses Bimbingan --}}
<h1>Form Penilaian Proses Bimbingan</h1>

@include('documents.partials.identity', [
    'student' => $student, 'exam' => $exam, 'withProgramme' => true, 'withTitle' => true,
])

<table class="data">
    <thead>
        <tr>
            <th class="num">No</th>
            <th>Indikator Penilaian</th>
            <th class="score">Nilai</th>
            <th style="width:110px">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($sheet['rows'] as $row)
            <tr>
                <td class="num">{{ $row['code'] }}</td>
                <td>{{ $row['label'] }}</td>
                <td class="score">{{ rtrim(rtrim(number_format((float) $row['score'], 2, ',', ''), '0'), ',') }}</td>
                <td>{{ $row['descriptor'] }}</td>
            </tr>
        @endforeach
        <tr>
            <td class="num subtotal"></td>
            <td class="subtotal">TOTAL NILAI</td>
            <td class="score subtotal">{{ number_format((float) $sheet['total'], 2, ',', '') }}</td>
            <td class="subtotal"></td>
        </tr>
    </tbody>
</table>

@if ($sheet['notes'])
    <p class="note"><strong>Catatan:</strong> {{ $sheet['notes'] }}</p>
@endif

<p class="note">
    Keterangan rentang nilai: Baik = 14–20, Cukup = 7–13, Kurang = 0–6.
</p>

<table class="sign">
    <tr>
        <td style="width:55%"></td>
        @include('documents.partials.signature', [
            'place' => $exam['signed_on'],
            'role' => 'Dosen Pembimbing',
            'name' => $sheet['lecturer'],
            'identifier' => $sheet['lecturer_number'],
            'image' => $signature,
        ])
    </tr>
</table>

@include('documents.partials.verify', ['verification' => $verification])
