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
        $slug = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'domeenid';

        $found = tempnam(sys_get_temp_dir(), 'domain_emails_');
        $missing = tempnam(sys_get_temp_dir(), 'domain_emails_');
        $zip = tempnam(sys_get_temp_dir(), 'domain_emails_');

        try {
            $lookup->enrichFile($file->getRealPath(), $found, $missing);

            // Two files: rows with an e-mail, and the rest to search elsewhere.
            $archive = new \ZipArchive();
            $archive->open($zip, \ZipArchive::OVERWRITE);
            $archive->addFile($found, "{$slug}-emailidega.csv");
            $archive->addFile($missing, "{$slug}-emailita.csv");
            $archive->close();
        } catch (\Throwable $e) {
            @unlink($zip);

            return back()->withErrors(['file' => $e->getMessage()]);
        } finally {
            @unlink($found);
            @unlink($missing);
        }

        return response()->download($zip, "{$slug}.zip", [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend();
    }
}
