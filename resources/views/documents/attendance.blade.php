{{-- 1. Daftar Hadir Ujian Tugas Akhir Mahasiswa --}}
<h1>Daftar Hadir {{ $exam['stage'] }}</h1>
<p class="subtitle">{{ $student['department'] }}@if ($student['faculty']) · {{ $student['faculty'] }}@endif</p>

<table class="identity">
    <tr><td class="label">Hari</td><td class="sep">:</td><td>{{ $exam['day'] }}</td></tr>
    <tr><td class="label">Tanggal</td><td class="sep">:</td><td>{{ $exam['date'] }}</td></tr>
    <tr><td class="label">Jam</td><td class="sep">:</td><td>{{ $exam['time'] }}</td></tr>
    <tr><td class="label">Tempat</td><td class="sep">:</td><td>{{ $exam['venue'] }}</td></tr>
</table>

<p style="margin-bottom:6px"><strong>Mahasiswa yang Diuji</strong></p>
<table class="data">
    <thead>
        <tr><th class="num">No</th><th>Nama</th><th style="width:150px">NIM</th><th style="width:150px">Tanda Tangan</th></tr>
    </thead>
    <tbody>
        @foreach ($participants as $person)
            @continue($person['role'] !== \App\Enums\ExamParticipantRole::Student)
            <tr>
                <td class="num">1</td>
                <td>{{ $person['name'] }}</td>
                <td>{{ $person['identifier'] }}</td>
                <td style="height:44px">
                    @if ($person['signature'])<img src="{{ $person['signature'] }}" style="height:38px" alt="">@endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<p style="margin-bottom:6px"><strong>Dewan Penguji dan Pembimbing</strong></p>
<table class="data">
    <thead>
        <tr><th class="num">No</th><th>Nama</th><th style="width:130px">NIP</th><th style="width:120px">Jabatan</th><th style="width:130px">Tanda Tangan</th></tr>
    </thead>
    <tbody>
        @php $row = 0; @endphp
        @foreach ($participants as $person)
            @continue($person['role'] === \App\Enums\ExamParticipantRole::Student)
            <tr>
                <td class="num">{{ ++$row }}</td>
                <td>{{ $person['name'] }}</td>
                <td>{{ $person['identifier'] ?: '—' }}</td>
                <td>{{ $person['role_label'] }}</td>
                <td style="height:44px">
                    @if ($person['signature'])<img src="{{ $person['signature'] }}" style="height:38px" alt="">@endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

@include('documents.partials.verify', ['verification' => $verification])
