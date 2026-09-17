{{-- 6. Berita Acara Ujian Tugas Akhir --}}
<h1>Berita Acara {{ $exam['stage'] }}</h1>

<p style="margin-bottom:14px">
    Pada hari ini, {{ $exam['date_in_words']['day'] }}
    Tanggal {{ $exam['date_in_words']['date'] }}
    Bulan {{ $exam['date_in_words']['month'] }}
    Tahun {{ $exam['date_in_words']['year'] }},
    telah dilaksanakan {{ $exam['stage'] }} bertempat di {{ $exam['venue'] }},
    atas nama:
</p>

@include('documents.partials.identity', [
    'student' => $student, 'exam' => $exam, 'withProgramme' => true, 'withTitle' => true,
])

<p style="margin-bottom:4px"><strong>Dinyatakan:</strong></p>
<p style="margin-top:0; margin-bottom:14px">
    @foreach ($minutes['options'] as $option)
        <span class="{{ $option['chosen'] ? 'chosen' : 'struck' }}">{{ $option['label'] }}</span>@if (! $loop->last) &nbsp;/&nbsp; @endif
    @endforeach
</p>

<p style="margin-bottom:14px">
    Dengan nilai {{ number_format((float) $minutes['final_score'], 2, ',', '') }}
    ({{ $minutes['final_in_words'] }})@if ($minutes['letter']), predikat {{ $minutes['letter'] }}@endif.
</p>

@if ($minutes['requires_revision'])
    <p style="margin-bottom:14px">
        Mahasiswa yang bersangkutan wajib menyelesaikan perbaikan tugas akhir
        selambat-lambatnya {{ $minutes['revision_days'] }}
        ({{ $minutes['revision_days_in_words'] }}) hari kerja terhitung sejak
        tanggal ujian ini, sesuai daftar revisi dari dewan penguji.
    </p>
@endif

@if ($minutes['notes'])
    <p class="note"><strong>Catatan:</strong> {{ $minutes['notes'] }}</p>
@endif

@php
    $studentSeat = collect($participants)
        ->firstWhere('role', \App\Enums\ExamParticipantRole::Student);
    $chairSeat = collect($participants)
        ->firstWhere('role', \App\Enums\ExamParticipantRole::ChairExaminer);
@endphp

<table class="sign">
    <tr>
        @include('documents.partials.signature', [
            'place' => 'Mahasiswa',
            'role' => '',
            'name' => $studentSeat['name'] ?? '—',
            'identifier' => $studentSeat['identifier'] ?? null,
            'identifierLabel' => 'NIM',
            'image' => $studentSeat['signature'] ?? null,
        ])
        @include('documents.partials.signature', [
            'place' => $exam['signed_on'],
            'role' => 'Ketua Penguji',
            'name' => $chairSeat['name'] ?? '—',
            'identifier' => $chairSeat['identifier'] ?? null,
            'image' => $chairSeat['signature'] ?? null,
        ])
    </tr>
</table>

<p style="margin-top:18px; margin-bottom:6px"><strong>Dewan Penguji dan Pembimbing</strong></p>
<table class="data">
    <thead>
        <tr><th class="num">No</th><th style="width:150px">Jabatan</th><th>Nama</th><th style="width:130px">Tanda Tangan</th></tr>
    </thead>
    <tbody>
        @php $row = 0; @endphp
        @foreach ($participants as $person)
            @continue($person['role'] === \App\Enums\ExamParticipantRole::Student)
            <tr>
                <td class="num">{{ ++$row }}</td>
                <td>{{ $person['role_label'] }}</td>
                <td>{{ $person['name'] }}</td>
                <td style="height:42px">
                    @if ($person['signature'])<img src="{{ $person['signature'] }}" style="height:36px" alt="">@endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

@include('documents.partials.verify', ['verification' => $verification])
