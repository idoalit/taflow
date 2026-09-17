{{-- 3. Lembar Revisi — one per examiner --}}
<h1>Lembar Revisi {{ $exam['stage'] }}</h1>

@include('documents.partials.identity', [
    'student' => $student, 'exam' => $exam, 'withProgramme' => true, 'withTitle' => true,
])

<table class="data">
    <thead>
        <tr><th class="num">No</th><th>Poin Revisi</th><th style="width:110px">Batas Waktu</th></tr>
    </thead>
    <tbody>
        @forelse ($sheet['items'] as $item)
            <tr>
                <td class="num">{{ $item['position'] }}</td>
                <td>{{ $item['body'] }}</td>
                <td>{{ $item['due_date'] ?: '—' }}</td>
            </tr>
        @empty
            <tr><td class="num">1</td><td colspan="2">Tidak ada revisi.</td></tr>
        @endforelse
    </tbody>
</table>

<table class="sign">
    <tr>
        <td style="width:55%"></td>
        @include('documents.partials.signature', [
            'place' => $exam['signed_on'],
            'role' => 'Penguji',
            'name' => $sheet['lecturer'],
            'identifier' => $sheet['lecturer_number'],
            'image' => $signature,
        ])
    </tr>
</table>

@include('documents.partials.verify', ['verification' => $verification])
