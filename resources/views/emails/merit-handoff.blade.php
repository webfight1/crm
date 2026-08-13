<!DOCTYPE html>
<html lang="et">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5; padding:24px 0;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e7eb;">
                <tr><td style="padding:16px 32px; background:#b45309; color:#fff; font-size:16px; font-weight:bold;">
                    ⚠️ {{ __('Automaatsed meeldetuletused ammendatud') }}
                </td></tr>
                <tr><td style="padding:24px 32px; font-size:15px; line-height:1.6;">
                    <p>{{ __('Kliendile') }} <strong>{{ $debtor->name ?: '—' }}</strong> {{ __('on saadetud') }}
                       <strong>{{ $count }}</strong> {{ __('automaatset meeldetuletust, kuid võlg on endiselt tasumata.') }}</p>

                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0; font-size:14px;">
                        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">{{ __('Tasumata kokku:') }}</td><td style="padding:2px 0;"><strong>{{ $debtor->formattedTotal() }}</strong></td></tr>
                        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">{{ __('Üle tähtaja:') }}</td><td style="padding:2px 0;">{{ $debtor->maxOverdueDays }} {{ __('päeva') }}</td></tr>
                        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">{{ __('Arveid:') }}</td><td style="padding:2px 0;">{{ count($debtor->invoices) }}</td></tr>
                        @if($debtor->email)
                        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">{{ __('Kliendi e-post:') }}</td><td style="padding:2px 0;">{{ $debtor->email }}</td></tr>
                        @endif
                    </table>

                    <p style="font-size:16px;"><strong>{{ __('Palun helista nüüd kliendile.') }}</strong></p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
