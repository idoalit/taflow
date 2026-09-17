<table class="identity">
    <tr><td class="label">Nama</td><td class="sep">:</td><td>{{ $student['name'] }}</td></tr>
    <tr><td class="label">NIM</td><td class="sep">:</td><td>{{ $student['number'] }}</td></tr>
    @if (! empty($withProgramme))
        <tr><td class="label">Program Studi</td><td class="sep">:</td><td>{{ $student['department'] }}</td></tr>
        @if ($student['faculty'])
            <tr><td class="label">Jurusan</td><td class="sep">:</td><td>{{ $student['faculty'] }}</td></tr>
        @endif
    @endif
    @if (! empty($withTitle))
        <tr><td class="label">Judul Tugas Akhir</td><td class="sep">:</td><td>{{ $exam['title'] }}</td></tr>
    @endif
</table>
