<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\QuotationEmailSend;
use App\Models\Setting;
use App\Models\Quotation;
use App\Mail\QuotationMail;
use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Services\OutreachMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationController extends Controller
{
    public function index()
    {
        $quotations = Quotation::with(['deal', 'deal.customer'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('quotations.index', compact('quotations'));
    }

    public function create()
    {
        $deals = Deal::with('customer')->get();
        $settings = Setting::getSettings();
        
        return view('quotations.create', compact('deals', 'settings'));
    }

    public function createForDeal(Deal $deal)
    {
        $settings = Setting::getSettings();
        
        return view('quotations.create', compact('deal', 'settings'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'deal_id' => 'required|exists:deals,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'valid_until' => 'nullable|date',
            'terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $quotation = DB::transaction(function () use ($validated, $request) {
            // Loo pakkumine
            $quotation = new Quotation([
                'deal_id' => $validated['deal_id'],
                'user_id' => auth()->id(),
                'title' => $validated['title'],
                'description' => $validated['description'],
                'vat_rate' => $validated['vat_rate'],
                'valid_until' => $validated['valid_until'],
                'terms' => $validated['terms'],
                'notes' => $validated['notes'],
                'status' => 'draft',
                'subtotal' => 0,
                'vat_amount' => 0,
                'total' => 0
            ]);

            // Genereeri pakkumise number
            $lastQuotation = Quotation::withTrashed()
                ->whereYear('created_at', now()->year)
                ->orderBy('id', 'desc')
                ->first();
            
            $number = $lastQuotation 
                ? 'Q' . now()->year . str_pad((intval(substr($lastQuotation->number, -3)) + 1), 3, '0', STR_PAD_LEFT)
                : 'Q' . now()->year . '001';
            
            $quotation->number = $number;
            $quotation->save();

            // Lisa pakkumise read
            foreach ($request->items as $item) {
                $quotation->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            // Arvuta summad
            $quotation->calculateTotals()->save();

            return $quotation;
        });

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', __('Pakkumine on loodud!'));
    }

    public function show(Quotation $quotation)
    {
        $quotation->load(['deal', 'deal.customer', 'deal.company', 'items', 'emailSends.senderAccount']);

        return view('quotations.show', compact('quotation'));
    }

    public function edit(Quotation $quotation)
    {
        $deals = Deal::with('customer')->get();
        $settings = Setting::getSettings();
        $quotation->load('items');
        
        return view('quotations.edit', compact('quotation', 'deals', 'settings'));
    }

    public function update(Request $request, Quotation $quotation)
    {
        $validated = $request->validate([
            'deal_id' => 'required|exists:deals,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'vat_rate' => 'required|numeric|min:0|max:100',
            'valid_until' => 'nullable|date',
            'terms' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.unit' => 'required|string|max:50',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $request, $quotation) {
            // Uuenda pakkumist
            $quotation->update([
                'deal_id' => $validated['deal_id'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'vat_rate' => $validated['vat_rate'],
                'valid_until' => $validated['valid_until'],
                'terms' => $validated['terms'],
                'notes' => $validated['notes'],
            ]);

            // Kustuta vanad read
            $quotation->items()->delete();

            // Lisa uued read
            foreach ($request->items as $item) {
                $quotation->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                ]);
            }

            // Arvuta summad
            $quotation->calculateTotals()->save();
        });

        return redirect()
            ->route('quotations.show', $quotation)
            ->with('success', __('Pakkumine on uuendatud!'));
    }

    public function destroy(Quotation $quotation)
    {
        if ($quotation->status !== 'draft') {
            return back()->with('error', __('Ainult mustandit saab kustutada!'));
        }

        $quotation->delete();

        return redirect()
            ->route('quotations.index')
            ->with('success', __('Pakkumine on kustutatud!'));
    }

    public function downloadPdf(Quotation $quotation)
    {
        $quotation->load(['deal', 'deal.customer', 'deal.company', 'items']);
        $settings = Setting::getSettings();

        $pdf = PDF::loadView('quotations.pdf', compact('quotation', 'settings'));
        
        return $pdf->download("pakkumine_{$quotation->number}.pdf");
    }

    /**
     * Pre-send composer: lets the operator inspect/edit every part of the
     * outgoing email before it actually leaves the server. Prefills:
     *   - to     = customer email (fallback to contact email)
     *   - subject = "Pakkumine #Q...."
     *   - body    = a sane default Estonian template
     *   - sender  = primary OutreachEmailAccount (signature_html shown)
     *   - PDF    = generated at send time, name shown here as preview
     * The actual send happens in sendByEmail() once the form is submitted.
     */
    public function composeEmail(Quotation $quotation)
    {
        // Re-sends are allowed: operator may want to send to a corrected
        // recipient, send themselves a test copy, or forward to a second
        // contact. Status stays "sent" after the first successful send.

        $quotation->load(['deal.customer', 'deal.contact', 'deal.company', 'user']);
        $settings = Setting::getSettings();

        // Only SMTP-capable accounts — Zone Relay can't carry PDF attachments
        // with the current relay endpoint schema, so we exclude those here.
        $accounts = OutreachEmailAccount::where('is_active', true)
            ->where(function ($q) {
                $q->where('provider', '!=', 'zone_relay')
                  ->orWhereNull('provider');
            })
            ->whereNotNull('smtp_host')
            ->orderByDesc('is_primary_reply_account')
            ->orderBy('name')
            ->get();

        $sender = $accounts->first();

        // Pick the first non-empty contact among the deal's links.
        $recipientEmail = $quotation->deal->customer?->email
            ?? $quotation->deal->contact?->email;
        $recipientName  = $quotation->deal->customer?->full_name
            ?? trim(($quotation->deal->contact?->first_name ?? '') . ' ' . ($quotation->deal->contact?->last_name ?? ''));

        // Default subject + body. The user can edit before sending so this
        // is just a sane starting point — Estonian B2B tone.
        $defaultSubject = __('Pakkumine') . ' #' . $quotation->number;

        $defaultBody = "Tere" . ($recipientName ? " {$recipientName}" : "") . ",\n\n"
            . "Saadan teile lubatud pakkumise: {$quotation->title}.\n\n"
            . ($quotation->description ? $quotation->description . "\n\n" : "")
            . "PDF on lisatud kirja manusena. Pakkumine kehtib "
            . ($quotation->valid_until ? "kuni " . $quotation->valid_until->format('d.m.Y') : "30 päeva") . ".\n\n"
            . "Annan hea meelega teada, kui on küsimusi või soovid täpsustusi.\n\n"
            . "Lugupidamisega,\n"
            . ($quotation->user?->name ?? '');

        return view('quotations.email-compose', [
            'quotation'      => $quotation,
            'settings'       => $settings,
            'sender'         => $sender,
            'accounts'       => $accounts,
            'recipientEmail' => $recipientEmail,
            'defaultSubject' => $defaultSubject,
            'defaultBody'    => $defaultBody,
            'pdfFilename'    => "pakkumine_{$quotation->number}.pdf",
        ]);
    }

    public function sendByEmail(Request $request, Quotation $quotation, OutreachMailer $mailer)
    {
        // Re-sends are allowed: operator may want to send to a corrected
        // recipient, send themselves a test copy, or forward to a second
        // contact. Status stays "sent" after the first successful send.

        $data = $request->validate([
            'to'         => 'required|email',
            'subject'    => 'required|string|max:500',
            'body'       => 'required|string|max:20000',
            'account_id' => 'required|integer|exists:outreach_email_accounts,id',
        ]);

        // Use the operator-selected outreach account. We route through
        // OutreachMailer because Laravel's default MAIL_MAILER on this VPS
        // is "log" — it would write to laravel.log instead of delivering.
        // The compose form only offers SMTP-capable accounts; defend against
        // a tampered form by re-checking here.
        $sender = OutreachEmailAccount::where('id', $data['account_id'])
            ->where('is_active', true)
            ->whereNotNull('smtp_host')
            ->first();

        if (! $sender) {
            return back()->withInput()
                ->with('error', __('Valitud saatja konto pole aktiivne või ei toeta manuseid.'));
        }

        $quotation->load(['deal.customer', 'deal.contact', 'deal.company']);
        $settings = Setting::getSettings();

        // Render and save the PDF temporarily so it can be attached.
        @mkdir(storage_path('app/temp'), 0775, true);
        $pdf     = Pdf::loadView('quotations.pdf', compact('quotation', 'settings'));
        $pdfPath = storage_path("app/temp/pakkumine_{$quotation->number}_" . time() . ".pdf");
        $pdf->save($pdfPath);

        try {
            // Convert plain-text body to HTML for the email — keep linebreaks,
            // escape user input so a stray < doesn't break the layout. The
            // mailer separately appends the account signature_html.
            $htmlBody = nl2br(e($data['body']));

            $recipientName = $quotation->deal->customer?->full_name
                ?? trim(($quotation->deal->contact?->first_name ?? '') . ' ' . ($quotation->deal->contact?->last_name ?? ''))
                ?: $data['to'];

            $mailer->send(
                account:   $sender,
                toEmail:   $data['to'],
                toName:    $recipientName,
                subject:   $data['subject'],
                htmlBody:  $htmlBody,
                attachments: [
                    [
                        'path' => $pdfPath,
                        'name' => "pakkumine_{$quotation->number}.pdf",
                        'mime' => 'application/pdf',
                    ],
                ],
            );

            // Audit row — one entry per successful send shown on /quotations/{id}.
            QuotationEmailSend::create([
                'quotation_id'      => $quotation->id,
                'sender_account_id' => $sender->id,
                'to_email'          => $data['to'],
                'subject'           => $data['subject'],
                'status'            => 'sent',
                'sent_at'           => now(),
            ]);

            $quotation->update(['status' => 'sent']);

            return redirect()->route('quotations.show', $quotation)
                ->with('success', __('Pakkumine saadetud aadressile') . ' ' . $data['to']);
        } catch (\Throwable $e) {
            \Log::error('[Quotation] send failed', [
                'quotation_id' => $quotation->id,
                'to'           => $data['to'],
                'error'        => $e->getMessage(),
            ]);

            // Audit the failure too so the operator sees attempts that didn't
            // make it out, with the error reason for debugging.
            QuotationEmailSend::create([
                'quotation_id'      => $quotation->id,
                'sender_account_id' => $sender->id,
                'to_email'          => $data['to'],
                'subject'           => $data['subject'],
                'status'            => 'failed',
                'error_message'     => $e->getMessage(),
                'sent_at'           => now(),
            ]);

            return back()
                ->withInput()
                ->with('error', __('Pakkumise saatmine ebaõnnestus') . ': ' . $e->getMessage());
        } finally {
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
        }
    }
}
