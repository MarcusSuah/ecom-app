<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 32px 36px 56px; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #12201c; line-height: 1.45; }
        h1 { font-size: 22px; margin: 0 0 4px; letter-spacing: -0.3px; }
        h2 { font-size: 12px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 0.8px; color: #55655f; }
        .muted { color: #55655f; }
        .brand { width: 100%; border-bottom: 3px solid #2447f5; padding-bottom: 12px; margin-bottom: 18px; }
        .brand td { vertical-align: top; }
        .mark { display: inline-block; width: 14px; height: 14px; border-radius: 7px; background: #2447f5; margin-right: 3px; }
        .mark.second { background: #f2b632; margin-left: -9px; }
        .name { font-size: 16px; font-weight: bold; }
        .doc-type { text-align: right; }
        .doc-type .label { font-size: 20px; font-weight: bold; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 9px; background: #edf0ea; font-size: 10px; }
        .pill.good { background: #d9f0e4; color: #1b7f53; }
        .pill.warn { background: #fbedc8; color: #2a1f00; }
        .grid { width: 100%; }
        .grid td { vertical-align: top; width: 50%; padding-right: 12px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.lines th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.6px; color: #55655f; border-bottom: 1px solid #d9dfd7; padding: 6px 6px; }
        table.lines td { border-bottom: 1px solid #edf0ea; padding: 6px 6px; }
        .num { text-align: right; white-space: nowrap; }
        table.totals { width: 45%; margin-left: 55%; margin-top: 10px; border-collapse: collapse; }
        table.totals td { padding: 4px 6px; }
        table.totals tr.grand td { border-top: 2px solid #12201c; font-weight: bold; font-size: 13px; }
        .note { margin-top: 18px; padding: 10px 12px; background: #f5f6f2; border-left: 3px solid #1b7f53; }
        footer { position: fixed; bottom: -36px; left: 0; right: 0; font-size: 9px; color: #55655f; border-top: 1px solid #d9dfd7; padding-top: 6px; }
    </style>
</head>
<body>
    <table class="brand">
        <tr>
            <td>
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $tenant->name }}" style="max-height: 44px; max-width: 180px;"><br>
                    <span class="name">{{ $tenant->name }}</span>
                @else
                    <span class="mark"></span><span class="mark second"></span>
                    <span class="name">{{ $tenant->name }}</span>
                @endif
                <div class="muted">
                    @if ($settings['address']){{ $settings['address'] }}<br>@endif
                    @if ($tenant->support_email){{ $tenant->support_email }}@endif
                    @if ($settings['support_phone']) · {{ $settings['support_phone'] }}@endif
                </div>
            </td>
            <td class="doc-type">
                <div class="label">{{ $documentType }}</div>
                <div class="muted">{{ $documentNumber }}</div>
                <div class="muted">{{ $documentDate }}</div>
            </td>
        </tr>
    </table>

    @yield('content')

    <footer>
        {{ $tenant->name }} · Generated {{ now()->format('j M Y, H:i') }} · Amounts in the currency shown; no conversion applied.
    </footer>
</body>
</html>
