<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tehnilise SEO ülevaade — {{ $site }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.4; color: #333; margin: 0; padding: 20px; }
        h1 { font-size: 18px; margin: 0; color: #2563eb; }
        h2 { font-size: 14px; margin: 18px 0 4px; }
        h3 { font-size: 12px; margin: 12px 0 6px; }
        .muted { color: #666; }
        .header { border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 14px; }
        .header img { max-height: 40px; }
        .summary { background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; padding: 10px; white-space: pre-line; }
        .score { font-size: 22px; font-weight: bold; }
        .good { color: #15803d; } .mid { color: #b45309; } .bad { color: #b91c1c; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        .tag { font-size: 9px; padding: 1px 5px; border-radius: 3px; white-space: nowrap; }
        .tag-fail { background: #fee2e2; color: #b91c1c; }
        .tag-ok { background: #dcfce7; color: #15803d; }
        .found { font-size: 10px; color: #888; }
        .ok-list { columns: 2; font-size: 10px; color: #555; }
        .page { page-break-inside: avoid; }
        .footer { margin-top: 24px; font-size: 9px; color: #666; border-top: 1px solid #ddd; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="border: none; padding: 0;">
                    @if($settings->logo_path && is_file(storage_path('app/public/' . $settings->logo_path)))
                        <img src="{{ storage_path('app/public/' . $settings->logo_path) }}" alt="{{ $settings->company_name }}">
                    @endif
                </td>
                <td style="border: none; padding: 0; text-align: right;">
                    <h1>Tehnilise SEO ülevaade</h1>
                    <div class="muted">{{ $site }} · seis {{ now()->format('d.m.Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <p>Vaatasime üle, kuidas Google näeb allolevaid lehti: mis on korras ja mida oleks vaja parandada,
        et leht otsingus paremini leitav oleks. Iga puuduse juures on lühidalt kirjas, miks see oluline on.</p>

    @if($summary)
        <h3>Kokkuvõte</h3>
        <div class="summary">{{ $summary }}</div>
    @endif

    @foreach($sections as $s)
        @php $score = $s['score'] ?? 0; @endphp
        <div class="page">
            <h2>{{ $s['url'] }}</h2>
            <table>
                <tr>
                    <td style="border: none; padding: 0;">
                        @if($s['keyword'])<span class="muted">Märksõna:</span> „{{ $s['keyword'] }}“<br>@endif
                        <span class="muted">Vajab parandamist:</span> <strong>{{ count($s['failed']) }}</strong>
                        · <span class="muted">korras:</span> {{ count($s['passed']) }}
                    </td>
                    <td style="border: none; padding: 0; text-align: right; width: 90px;">
                        <span class="score {{ $score >= 80 ? 'good' : ($score >= 50 ? 'mid' : 'bad') }}">{{ $s['score'] ?? '—' }}</span><span class="muted">/100</span>
                    </td>
                </tr>
            </table>

            @if($s['failed'])
                <h3>Vajab parandamist</h3>
                <table>
                    @foreach($s['failed'] as $r)
                        <tr>
                            <td style="width: 70px;"><span class="tag tag-fail">Parandada</span></td>
                            <td>
                                <strong>{{ $r['label'] }}</strong>
                                @if($r['explanation'])<div>{{ $r['explanation'] }}</div>@endif
                                @if($r['note'])<div class="found">{{ $r['note'] }}</div>@endif
                                @if(($r['value'] ?? '') !== '' && $r['value'] !== null)<div class="found">Leitud: {{ \Illuminate\Support\Str::limit($r['value'], 160) }}</div>@endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            @else
                <p class="good">Kõik kontrollitud punktid on korras.</p>
            @endif

            @if($s['passed'])
                <h3>Korras</h3>
                <div class="ok-list">
                    @foreach($s['passed'] as $r)<div>✓ {{ $r['label'] }}</div>@endforeach
                </div>
            @endif
        </div>
    @endforeach

    <div class="footer">
        {{ $settings->company_name }}
        @if($settings->email) · {{ $settings->email }}@endif
        @if($settings->phone) · {{ $settings->phone }}@endif
        @if($settings->website) · {{ $settings->website }}@endif
    </div>
</body>
</html>
