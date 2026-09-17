{{--
    The shared shell for every official document.

    Written with tables and plain CSS rather than flex or grid: dompdf renders
    a subset of CSS, and tables are what it renders reliably. Black on white
    throughout, since these are printed and photocopied.

    Legal text lives in the individual templates, so it can be changed without
    touching layout.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 16mm 16mm; }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10.5pt;
            line-height: 1.45;
            color: #000;
        }

        .kop { width: 100%; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 14px; }
        .kop td { vertical-align: middle; }
        .kop .logo { width: 68px; }
        .kop .logo img { width: 60px; }
        .kop .name { font-size: 13pt; font-weight: bold; text-transform: uppercase; }
        .kop .unit { font-size: 11pt; text-transform: uppercase; }
        .kop .meta { font-size: 8.5pt; }

        h1 { font-size: 12pt; text-align: center; text-transform: uppercase;
             margin: 0 0 2px; letter-spacing: .02em; }
        h1 + .subtitle { text-align: center; font-size: 10pt; margin: 0 0 16px; }

        table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.data th, table.data td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
        table.data th { background: #eee; font-size: 9.5pt; text-align: left; }
        table.data td.num, table.data th.num { text-align: center; width: 34px; }
        table.data td.score, table.data th.score { text-align: center; width: 64px; }
        td.subtotal, th.subtotal { background: #f4f4f4; font-weight: bold; }

        table.identity { width: 100%; margin-bottom: 14px; }
        table.identity td { padding: 1px 0; vertical-align: top; }
        table.identity td.label { width: 150px; }
        table.identity td.sep { width: 12px; }

        .sign { width: 100%; margin-top: 18px; }
        .sign td { vertical-align: top; text-align: center; font-size: 10pt; }
        .sign .space { height: 62px; }
        .sign .space img { height: 58px; }
        .sign .name { font-weight: bold; text-decoration: underline; }

        .note { font-size: 9pt; margin-top: 10px; }
        .verify { margin-top: 20px; font-size: 8pt; }
        .verify td { vertical-align: middle; }
        .verify img { width: 72px; }

        .page-break { page-break-after: always; }
        .struck { text-decoration: line-through; color: #555; }
        .chosen { font-weight: bold; }
    </style>
</head>
<body>
@foreach ($pages as $page)
    @include('documents.partials.kop', ['institution' => $institution])

    {!! $page !!}

    @if (! $loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
