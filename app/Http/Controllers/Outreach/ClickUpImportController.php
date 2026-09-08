<?php

namespace App\Http\Controllers\Outreach;

use App\Http\Controllers\Controller;
use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Services\OutreachCsvImportService;
use App\Services\ClickUpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * UI for the ClickUp lead import — the same thing `php artisan clickup:fetch`
 * does, but reachable from the Outreach menu so it does not live only in
 * somebody's shell history.
 *
 * Preview → download CSV → import into a campaign. Preview and import both
 * re-fetch from ClickUp rather than caching the rows between requests: the
 * lists are a few hundred tasks, and a stale preview importing yesterday's
 * data is a worse failure than a couple of seconds' wait.
 */
class ClickUpImportController extends Controller
{
    public function __construct(private readonly ClickUpService $clickup)
    {
    }

    public function index(Request $request): \Illuminate\View\View
    {
        return view('outreach.clickup.index', [
            'campaigns' => OutreachCampaign::orderBy('name')->get(),
            'sources'   => config('services.clickup.lists', []),
            'configured' => filled(config('services.clickup.token')),
            'rows'      => null,
            'source'    => $request->old('source', ''),
        ]);
    }

    /** Fetch and show what would be imported. Writes nothing. */
    public function preview(Request $request): \Illuminate\View\View
    {
        $data = $this->validated($request);

        try {
            $result = $this->clickup->fetchRows(
                $data['source'],
                perContact: $data['per_contact'],
                withEmpty: $data['with_empty'],
            );
        } catch (\Throwable $e) {
            return $this->indexWithError($request, $e->getMessage());
        }

        return view('outreach.clickup.index', [
            'campaigns'  => OutreachCampaign::orderBy('name')->get(),
            'sources'    => config('services.clickup.lists', []),
            'configured' => true,
            'rows'       => $result['rows'],
            'taskCount'  => $result['tasks'],
            'source'     => $data['source'],
            'perContact' => $data['per_contact'],
            'withEmpty'  => $data['with_empty'],
        ]);
    }

    /** Stream the same rows as a CSV download. */
    public function download(Request $request): StreamedResponse|RedirectResponse
    {
        $data = $this->validated($request);

        try {
            $result = $this->clickup->fetchRows(
                $data['source'],
                perContact: $data['per_contact'],
                withEmpty: $data['with_empty'],
            );
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['source' => $e->getMessage()]);
        }

        $rows = $result['rows'];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            // Excel on Windows needs the BOM to read UTF-8 correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ClickUpService::CSV_HEADERS, ',', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($out, $this->clickup->csvRow($row), ',', '"', '\\');
            }

            fclose($out);
        };

        return response()->streamDownload($callback, $this->filename($data['source'], $result['id']), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** Import the rows into a campaign through the normal CSV import path. */
    public function import(Request $request, OutreachCsvImportService $importer): RedirectResponse
    {
        $data = $this->validated($request);

        $request->validate(['campaign_id' => ['required', 'integer', 'exists:outreach_campaigns,id']]);

        try {
            $result = $this->clickup->fetchRows(
                $data['source'],
                perContact: $data['per_contact'],
                withEmpty: $data['with_empty'],
            );

            $tmp = tempnam(sys_get_temp_dir(), 'clickup_') . '.csv';
            $this->clickup->writeCsv($result['rows'], $tmp);

            $count = $importer->import($tmp, (int) $request->input('campaign_id'));

            @unlink($tmp);
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['source' => $e->getMessage()]);
        }

        $campaign = OutreachCampaign::find($request->input('campaign_id'));

        return redirect()
            ->route('outreach.campaigns.leads.index', $campaign)
            ->with('success', "ClickUpist imporditud {$count} rida. Duplikaadid jäeti vahele.");
    }

    /** @return array{source: string, per_contact: bool, with_empty: bool} */
    private function validated(Request $request): array
    {
        $request->validate([
            'source' => ['required', 'string', 'max:500'],
        ], [], ['source' => 'ClickUpi list']);

        return [
            'source'      => trim((string) $request->input('source')),
            'per_contact' => $request->boolean('per_contact'),
            'with_empty'  => $request->boolean('with_empty'),
        ];
    }

    private function indexWithError(Request $request, string $message): \Illuminate\View\View
    {
        return view('outreach.clickup.index', [
            'campaigns'  => OutreachCampaign::orderBy('name')->get(),
            'sources'    => config('services.clickup.lists', []),
            'configured' => filled(config('services.clickup.token')),
            'rows'       => null,
            'source'     => (string) $request->input('source'),
            'error'      => $message,
        ]);
    }

    private function filename(string $source, string $id): string
    {
        $label = collect(config('services.clickup.lists', []))
            ->first(fn ($l) => str_contains((string) ($l['url'] ?? ''), $id))['label'] ?? null;

        $slug = $label ? Str::slug($label) : "clickup-{$id}";

        return "{$slug}-" . now()->format('Y-m-d') . '.csv';
    }
}
