<?php

namespace App\Http\Controllers\Outreach;

use App\Http\Controllers\Controller;
use App\Services\DomainEmailLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Upload a CSV of websites/domains, get the same CSV back with e-mails,
 * company name, reg. code and phone from the company register appended.
 */
class DomainEmailController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        return view('outreach.domain-emails.index');
    }

    public function enrich(Request $request, DomainEmailLookupService $lookup)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [], ['file' => 'CSV fail']);

        $file = $request->file('file');
        $out = tempnam(sys_get_temp_dir(), 'domain_emails_');

        try {
            $lookup->enrichFile($file->getRealPath(), $out);
        } catch (\Throwable $e) {
            @unlink($out);

            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-emailidega.csv';

        return response()->download($out, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend();
    }
}
