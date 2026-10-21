<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        @page { size: @yield('page-size', 'A4 portrait'); margin: 10mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 10pt/1.3 Arial, Helvetica, sans-serif; color: #000; background: #e5e7eb; }
        .toolbar { position: sticky; top: 0; background: #111827; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; font: 14px system-ui, sans-serif; z-index: 2; }
        .toolbar button { background: #2563eb; color: #fff; border: 0; padding: 8px 14px; border-radius: 6px; font: inherit; font-weight: 600; cursor: pointer; }
        .page { background: #fff; width: 210mm; min-height: 297mm; margin: 12px auto; padding: 10mm 12mm; box-shadow: 0 2px 10px rgba(0,0,0,.15); }
        .head { text-align: center; margin-bottom: 4mm; } .head .agency { font-weight: bold; font-size: 11pt; }
        .formno { font-size: 8pt; font-style: italic; }
        h1 { text-align: center; font-size: 14pt; margin: 3mm 0; letter-spacing: .5px; }
        table.f { width: 100%; border-collapse: collapse; } table.f td, table.f th { border: 1px solid #000; padding: 1.5mm 2mm; vertical-align: top; }
        .lbl { font-size: 7.5pt; text-transform: uppercase; } .val { font-weight: bold; font-size: 10pt; min-height: 5mm; }
        .sec { background: #e5e7eb; font-weight: bold; text-align: center; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .box { display: inline-block; width: 3.4mm; height: 3.4mm; border: 1px solid #000; vertical-align: -0.6mm; margin-right: 1.5mm; text-align: center; line-height: 3mm; font-size: 8pt; font-weight: bold; }
        .opt { font-size: 8.5pt; margin: .8mm 0; } .basis { font-size: 7pt; color: #333; }
        .line { border-bottom: 1px solid #000; display: inline-block; min-width: 40mm; padding: 0 1mm; font-weight: bold; }
        .sign { margin-top: 9mm; text-align: center; } .sign .n { border-top: 1px solid #000; display: inline-block; min-width: 60mm; padding-top: .5mm; font-size: 8pt; }
        .sign .who { font-weight: bold; text-transform: uppercase; font-size: 9pt; }
        .c { text-align: center; } .r { text-align: right; } .small { font-size: 8pt; }
        @media print { body { background: #fff; } .toolbar { display: none; } .page { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; } }
    </style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print</button><span>@yield('title')</span></div>
<div class="page">@yield('content')</div>
</body>
</html>
