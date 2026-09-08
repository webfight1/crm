<!DOCTYPE html>
<html lang="et">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5; padding:24px 0;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e7eb;">
                <tr><td style="padding:16px 32px; background:#b45309; color:#fff; font-size:16px; font-weight:bold;">
                    📞 {{ __('Palun võta kliendiga ühendust') }}
                </td></tr>
                <tr><td style="padding:24px 32px; font-size:15px; line-height:1.6;">
                    <p>{{ __('Arve on') }} <strong>{{ $invoice->daysOverdue }}</strong> {{ __('päeva üle tähtaja ja meeldetuletusi on saadetud. Palun helista kliendile.') }}</p>

                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:16px 0; font-size:14px;">
                        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">{{ __('Klient:') }}</td><td style="padding:2px 0;"><strong>{{ $invoice->customerName ?: '—' }}</strong></td></tr>
                        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">{{ __('Arve nr:') }}</td><td style="padding:2px 0;">{{ $invoice->invoiceNo }}</td></tr>
                        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">{{ __('Tasumata:') }}</td><td style="padding:2px 0;"><strong>{{ $invoice->formattedUnpaid() }}</strong></td></tr>
                        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">{{ __('Tähtaeg:') }}</td><td style="padding:2px 0;">{{ $invoice->dueDateFormatted() }}</td></tr>
                        @if($invoice->email)
                        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">{{ __('Kliendi e-post:') }}</td><td style="padding:2px 0;">{{ $invoice->email }}</td></tr>
                        @endif
                        @if($invoice->contact)
                        <tr><td style="padding:2px 12px 2px 0; color:#6b7280;">{{ __('Kontakt:') }}</td><td style="padding:2px 0;">{{ $invoice->contact }}</td></tr>
                        @endif
                    </table>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
