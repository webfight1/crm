<?php

namespace App\Console\Commands;

use App\Outreach\Support\MimeHeader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** One-off: decode raw "=?utf-8?Q?…" subjects/names stored before MimeHeader existed. */
class FixMimeSubjects extends Command
{
    protected $signature = 'outreach:fix-mime-subjects {--dry-run}';

    protected $description = 'Decode RFC 2047 encoded words left in stored subjects and sender names';

    private const COLUMNS = [
        'outreach_messages' => ['subject', 'from_name'],
        'outreach_scheduled_replies' => ['subject'],
    ];

    public function handle(): int
    {
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $changed = 0;
                DB::table($table)->where($column, 'like', '%=?%?=%')->select('id', $column)
                    ->chunkById(100, function ($rows) use ($table, $column, &$changed) {
                        foreach ($rows as $row) {
                            $new = MimeHeader::decode($row->{$column});
                            if ($new === $row->{$column}) {
                                continue;
                            }
                            $this->line("{$table}#{$row->id}: {$new}");
                            if (! $this->option('dry-run')) {
                                DB::table($table)->where('id', $row->id)->update([$column => $new]);
                            }
                            $changed++;
                        }
                    });
                $this->info("{$table}.{$column}: {$changed}");
            }
        }

        return self::SUCCESS;
    }
}
