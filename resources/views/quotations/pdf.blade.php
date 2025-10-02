<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Pakkumine') }} #{{ $quotation->number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .header img {
            max-height: 40px;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 18px;
            margin: 0;
            color: #2563eb;
        }
        .info-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .company-info, .customer-info {
            width: 48%;
        }
        .info-box {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            background-color: #f8f9fa;
            margin-bottom: 10px;
        }
        .info-box h3 {
            font-size: 12px;
            margin: 0 0 5px 0;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        th, td {
            padding: 6px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f1f5f9;
            font-weight: 600;
        }
        .totals {
            margin-left: auto;
            width: 200px;
        }
        .totals td {
            padding: 4px;
        }
        .totals tr:last-child {
            font-weight: bold;
            background-color: #f1f5f9;
        }
        .footer {
            margin-top: 20px;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="margin: 0; border: none;">
            <tr>
                <td style="border: none; padding: 0; width: 50%;">
                    @if($settings->logo_path)
                        <img src="{{ storage_path('app/public/' . $settings->logo_path) }}" alt="{{ $settings->company_name }}">
                    @endif
                </td>
                <td style="border: none; padding: 0; text-align: right;">
                    <h1>{{ __('Pakkumine') }} #{{ $quotation->number }}</h1>
                    <div style="font-size: 11px; color: #666;">
                        {{ __('Kuupäev:') }} {{ now()->format('d.m.Y') }}<br>
                        @if($quotation->valid_until)
                            {{ __('Kehtiv kuni:') }} {{ $quotation->valid_until->format('d.m.Y') }}
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table style="margin: 0 0 20px 0; border: none;">
        <tr>
            <td style="width: 48%; border: none; padding: 0; vertical-align: top;">
                <div style="font-size: 10px; line-height: 1.3;">
                    <strong style="color: #666; font-size: 11px;">{{ __('Pakkuja:') }}</strong><br>
                    <strong>{{ $settings->company_name }}</strong><br>
                    @if($settings->registration_number)
                        {{ __('Reg. nr:') }} {{ $settings->registration_number }}<br>
                    @endif
                    @if($settings->vat_number)
                        {{ __('KM nr:') }} {{ $settings->vat_number }}<br>
                    @endif
                    @if($settings->address)
                        {{ $settings->address }}<br>
                    @endif
                    @if($settings->phone)
                        {{ __('Tel:') }} {{ $settings->phone }}<br>
                    @endif
                    @if($settings->email)
                        {{ __('E-post:') }} {{ $settings->email }}<br>
                    @endif
                    @if($settings->website)
                        {{ $settings->website }}
                    @endif
                </div>
            </td>
            <td style="width: 4%; border: none;"></td>
            <td style="width: 48%; border: none; padding: 0; vertical-align: top;">
                <div style="font-size: 10px; line-height: 1.3;">
                    <strong style="color: #666; font-size: 11px;">{{ __('Klient:') }}</strong><br>
                    <strong>{{ $quotation->deal->customer->full_name }}</strong><br>
                    @if($quotation->deal->company)
                        {{ $quotation->deal->company->name }}<br>
                        @if($quotation->deal->company->registrikood)
                            {{ __('Reg. nr:') }} {{ $quotation->deal->company->registrikood }}<br>
                        @endif
                    @endif
                    @if($quotation->deal->customer->email)
                        {{ __('E-post:') }} {{ $quotation->deal->customer->email }}<br>
                    @endif
                    @if($quotation->deal->customer->phone)
                        {{ __('Tel:') }} {{ $quotation->deal->customer->phone }}
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-bottom: 15px;">
        <h2 style="font-size: 14px; margin: 0 0 5px 0; color: #2563eb;">{{ $quotation->title }}</h2>
        @if($quotation->description)
            <p style="font-size: 11px; margin: 0; color: #666;">{{ $quotation->description }}</p>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 40%;">{{ __('Kirjeldus') }}</th>
                <th style="width: 15%;">{{ __('Kogus') }}</th>
                <th style="width: 15%;">{{ __('Ühik') }}</th>
                <th style="width: 15%;">{{ __('Ühiku hind') }}</th>
                <th style="width: 15%;">{{ __('Summa') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td>{{ number_format($item->quantity, 2) }}</td>
                    <td>{{ $item->unit }}</td>
                    <td>€{{ number_format($item->unit_price, 2) }}</td>
                    <td>€{{ number_format($item->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div style="display: flex; justify-content: flex-end;">
        <table class="totals" style="margin-bottom: 10px;">
            <tr>
                <td style="color: #666;">{{ __('Summa käibemaksuta:') }}</td>
                <td align="right">€{{ number_format($quotation->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td style="color: #666;">{{ __('Käibemaks') }} ({{ $quotation->vat_rate }}%):</td>
                <td align="right">€{{ number_format($quotation->vat_amount, 2) }}</td>
            </tr>
            <tr>
                <td style="font-weight: bold;">{{ __('Kokku:') }}</td>
                <td align="right" style="font-weight: bold;">€{{ number_format($quotation->total, 2) }}</td>
            </tr>
        </table>
    </div>

    <table style="margin-top: 20px; border: none;">
        <tr>
            <td style="width: 48%; border: none; padding: 0; vertical-align: top;">
                @if($quotation->terms || $settings->quotation_terms)
                    <div class="info-box" style="max-height: 80px; overflow: hidden;">
                        <h3 style="margin: 0 0 5px 0;">{{ __('Maksetingimused') }}</h3>
                        <p style="font-size: 10px; margin: 0; line-height: 1.2;">{{ $quotation->terms ?: $settings->quotation_terms }}</p>
                    </div>
                @endif
            </td>
            <td style="width: 4%; border: none;"></td>
            <td style="width: 48%; border: none; padding: 0; vertical-align: top;">
                @if($settings->bank_name && $settings->bank_account)
                    <div class="info-box">
                        <h3 style="margin: 0 0 5px 0;">{{ __('Pangaandmed') }}</h3>
                        <p style="font-size: 10px; margin: 0; line-height: 1.2;">
                            {{ $settings->bank_name }}<br>
                            {{ __('IBAN:') }} {{ $settings->bank_account }}
                            @if($settings->swift)<br>{{ __('SWIFT/BIC:') }} {{ $settings->swift }}@endif
                        </p>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    @if($quotation->notes)
        <div class="footer">
            <p style="font-style: italic; margin: 0;">{{ $quotation->notes }}</p>
        </div>
    @endif
</body>
</html>
