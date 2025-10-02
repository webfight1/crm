<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Setting;
use App\Models\Quotation;
use App\Mail\QuotationMail;
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
            ->paginate(15);

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
        $quotation->load(['deal', 'deal.customer', 'deal.company', 'items']);
        
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

    public function sendByEmail(Quotation $quotation)
    {
        if ($quotation->status !== 'draft') {
            return back()->with('error', __('Pakkumine on juba saadetud!'));
        }

        $quotation->load(['deal', 'deal.customer']);
        $settings = Setting::getSettings();

        // Genereeri PDF
        $pdf = PDF::loadView('quotations.pdf', compact('quotation', 'settings'));
        $pdfPath = storage_path("app/temp/pakkumine_{$quotation->number}.pdf");
        $pdf->save($pdfPath);

        try {
            // Saada e-kiri
            Mail::to($quotation->deal->customer->email)
                ->send(new QuotationMail($quotation, $pdfPath));

            // Uuenda staatus
            $quotation->update(['status' => 'sent']);

            return back()->with('success', __('Pakkumine on saadetud!'));
        } catch (\Exception $e) {
            return back()->with('error', __('Pakkumise saatmine ebaõnnestus!'));
        } finally {
            // Kustuta ajutine PDF
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
        }
    }
}
