@php
    use App\Enums\GuidanceStage;

    $isProposal = $stage === GuidanceStage::Proposal;
    $heading = $isProposal ? 'KARTU BIMBINGAN PROPOSAL' : 'KARTU BIMBINGAN HASIL PENELITIAN';
    $note = $isProposal
        ? 'kartu ini wajib dibawa pada setiap sesi bimbingan proposal dan diparaf oleh dosen pembimbing sebagai bukti kehadiran bimbingan, serta menjadi syarat pengajuan ujian proposal.'
        : 'kartu ini wajib dibawa pada setiap sesi bimbingan hasil penelitian dan diparaf oleh dosen pembimbing sebagai bukti kehadiran bimbingan, serta menjadi syarat pengajuan ujian hasil penelitian.';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} — {{ $student->user->name }}</title>
    <style>
        @page { size: A4; margin: 0.9in; }

        body {
            margin: 0;
            font-family: 'Barlow', ui-sans-serif, system-ui, sans-serif;
            color: #000;
            background: #fff;
            font-size: 12.5px;
            line-height: 1.5;
        }

        .sheet { max-width: 21cm; margin: 0 auto; padding: 0.9in; }

        .masthead {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }

        .masthead h1 {
            margin: 12px 0 0;
            font-family: 'Barlow Condensed', ui-sans-serif, system-ui, sans-serif;
            font-size: 25px;
            font-weight: 600;
        }

        .kb-info td { border: none; padding: 3px 0; font-size: 12.5px; vertical-align: top; }

        .kb-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        .kb-table th,
        .kb-table td { border: 1px solid #000; padding: 6px 8px; text-align: left; vertical-align: top; }
        .kb-table th {
            font-family: 'Barlow Condensed', ui-sans-serif, system-ui, sans-serif;
            font-weight: 600;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            background: #f2f2f3;
        }

        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 36px; }
        .kb-sign { break-inside: avoid; text-align: center; }
        .kb-sign .line { border-top: 1px solid #000; width: 200px; margin: 56px auto 4px; }

        .print-bar { text-align: center; padding: 16px; }
        .print-bar button {
            font: inherit;
            padding: 8px 16px;
            border: 1px solid #000;
            background: #fff;
            cursor: pointer;
        }

        @media print {
            .print-bar { display: none; }
            .sheet { padding: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button type="button" onclick="window.print()">Cetak Kartu Bimbingan</button>
    </div>

    <div class="sheet">
        <div class="masthead">
            <div style="font-size:13px;font-weight:600">{{ config('app.name') }}</div>
            <div style="font-size:12px">Program Studi {{ $student->department->name }}</div>
            <h1>{{ $heading }}</h1>
        </div>

        <table class="kb-info" style="margin-bottom:20px">
            <tr><td style="width:150px">Nama Mahasiswa</td><td style="width:16px">:</td><td>{{ $student->user->name }}</td></tr>
            <tr><td>NIM</td><td>:</td><td>{{ $student->student_number }}</td></tr>
            <tr><td>Program Studi</td><td>:</td><td>{{ $student->department->name }}</td></tr>
            <tr><td>Judul Tugas Akhir</td><td>:</td><td>{{ $proposalTitle }}</td></tr>
            @foreach ($student->supervisors as $supervisor)
                <tr>
                    <td>Pembimbing {{ $supervisor->pivot->order }}</td>
                    <td>:</td>
                    <td>{{ $supervisor->user->name }}</td>
                </tr>
            @endforeach
        </table>

        <table class="kb-table" style="margin-bottom:8px">
            <thead>
                <tr>
                    <th style="width:28px">No</th>
                    <th style="width:80px">Tanggal</th>
                    <th>Uraian Kegiatan Bimbingan</th>
                    <th style="width:90px">Paraf Pembimbing</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sessions as $index => $session)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ optional($session->completed_at ?? $session->created_at)->locale('id')->isoFormat('D MMM YYYY') }}</td>
                        <td>{{ $session->title }}@if (filled($session->completion_notes)): {{ $session->completion_notes }}@endif</td>
                        <td>&nbsp;</td>
                    </tr>
                @endforeach

                @for ($row = 1; $row <= $blankRows; $row++)
                    <tr>
                        <td>{{ $sessions->count() + $row }}</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <p style="font-size:11px;margin:6px 0 0">Catatan: {{ $note }}</p>

        <div class="signatures">
            @foreach ($student->supervisors as $supervisor)
                <div class="kb-sign">
                    <div>Pembimbing {{ $supervisor->pivot->order }},</div>
                    <div class="line"></div>
                    <div style="font-weight:600">{{ $supervisor->user->name }}</div>
                    <div style="font-size:11px">NIDN. {{ $supervisor->lecturer_number }}</div>
                </div>
            @endforeach

            <div class="kb-sign">
                <div>Mengetahui,<br>Admin Program Studi</div>
                <div class="line"></div>
                <div style="font-weight:600">...........................</div>
                <div style="font-size:11px">NIP. ...........................</div>
            </div>
        </div>
    </div>
</body>
</html>
