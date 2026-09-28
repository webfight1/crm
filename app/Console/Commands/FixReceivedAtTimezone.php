<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-off: inbound messages whose Date header was UTC (Gmail) were stored with
 * the UTC wall-clock time (14:07 instead of 17:07). A row is fixed only when
 * reading its time as UTC puts it just before we fetched it (created_at).
 */
class FixReceivedAtTimezone extends Command
{
    protected $signature = 'outreach:fix-received-tz {--dry-run}';

    protected $description = 'Shift inbound received_at values that were stored as UTC to the app timezone';

    public function handle(): int
    {
        $tz = config('app.timezone');
        $changed = 0;

        DB::table('outreach_messages')->where('direction', 'inbound')->whereNotNull('received_at')
            ->select('id', 'received_at', 'created_at')
            ->chunkById(200, function ($rows) use ($tz, &$changed) {
                foreach ($rows as $row) {
                    $stored  = Carbon::parse($row->received_at, $tz);
                    $fetched = Carbon::parse($row->created_at, $tz);
                    $asUtc   = Carbon::parse($row->received_at, 'UTC')->setTimezone($tz);
                    // Already right (fetched within the poll window), or the UTC reading doesn't fit either.
                    if ($stored->diffInMinutes($fetched, false) <= 30 || $asUtc->diffInMinutes($fetched, false) < 0
                        || $asUtc->diffInMinutes($fetched, false) > 30) {
                        continue;
                    }
                    $new = $asUtc->format('Y-m-d H:i:s');
                    $this->line("#{$row->id}: {$row->received_at} → {$new}");
                    if (! $this->option('dry-run')) {
                        DB::table('outreach_messages')->where('id', $row->id)->update(['received_at' => $new]);
                    }
                    $changed++;
                }
            });
        $this->info("Parandatud: {$changed}");

        return self::SUCCESS;
    }
}
