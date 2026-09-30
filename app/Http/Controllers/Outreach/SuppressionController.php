<?php

namespace App\Http\Controllers\Outreach;

use App\Http\Controllers\Controller;
use App\Outreach\Models\OutreachSuppression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Outreach → Loobujad: the global do-not-contact list. */
class SuppressionController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $q = trim((string) $request->query('q'));

        return view('outreach.suppressions.index', [
            'entries' => OutreachSuppression::query()
                ->when($q !== '', fn ($w) => $w->where('value', 'like', '%' . strtolower($q) . '%'))
                ->when($request->query('reason'), fn ($w, $r) => $w->where('reason', $r))
                ->latest('id')
                ->paginate(50)
                ->withQueryString(),
            'counts'  => OutreachSuppression::selectRaw('reason, COUNT(*) AS n')->groupBy('reason')->pluck('n', 'reason'),
            'q'       => $q,
        ]);
    }

    /** Add e-mails/domains from the textarea and/or an uploaded file. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'values' => ['nullable', 'string', 'max:200000'],
            'file'   => ['nullable', 'file', 'mimes:csv,txt', 'max:10240'],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);

        $text = (string) $request->input('values');
        if ($request->hasFile('file')) {
            $text .= "\n" . file_get_contents($request->file('file')->getRealPath());
        }

        // A file may be any CSV — take every e-mail in it; a textarea line
        // without "@" is a domain.
        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text, $m);
        $values = $m[0];
        if (! $request->hasFile('file')) {
            foreach (preg_split('/[\s,;]+/', $text) as $token) {
                if ($token !== '' && ! str_contains($token, '@')) {
                    $values[] = $token;
                }
            }
        }

        $added = 0;
        foreach (array_unique(array_map('strtolower', $values)) as $v) {
            $entry = OutreachSuppression::add($v, 'manual', note: $request->input('note') ?: null);
            if ($entry?->wasRecentlyCreated) {
                $added++;
            }
        }

        return back()->with('success', "Lisatud {$added} loobujat" . ($added < count($values) ? ' (ülejäänud olid juba nimekirjas või vigased).' : '.'));
    }

    public function destroy(OutreachSuppression $suppression): RedirectResponse
    {
        $suppression->delete();

        return back()->with('success', "{$suppression->value} eemaldati loobujate nimekirjast. Tema lead'e ei aktiveerita automaatselt uuesti.");
    }
}
