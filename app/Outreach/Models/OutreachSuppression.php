<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Global do-not-contact list. An entry is either one e-mail or a whole
 * domain; either way no campaign imports or sends to a matching address.
 *
 * Filled automatically when a lead unsubscribes, bounces or replies
 * "not interested" (see OutreachLead::booted), and by hand from
 * Outreach → Loobujad.
 */
class OutreachSuppression extends Model
{
    protected $table = 'outreach_suppressions';

    const TYPE_EMAIL  = 'email';
    const TYPE_DOMAIN = 'domain';

    const REASONS = [
        'unsubscribed'   => 'Loobus',
        'bounced'        => 'Tagastus (bounce)',
        'not_interested' => 'Pole huvitatud',
        'manual'         => 'Käsitsi',
    ];

    protected $fillable = ['value', 'type', 'reason', 'lead_id', 'note'];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(OutreachLead::class, 'lead_id');
    }

    /**
     * Add an e-mail or domain. Existing entries are kept as they are (the
     * first reason wins). Active leads with a matching address in any
     * campaign are stopped right away.
     */
    public static function add(string $value, string $reason, ?int $leadId = null, ?string $note = null): ?self
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^(mailto:|[a-z]+://)?(www\.)?#', '', $value);
        $value = rtrim(explode('/', $value)[0], '.');

        $type = str_contains($value, '@') ? self::TYPE_EMAIL : self::TYPE_DOMAIN;
        $valid = $type === self::TYPE_EMAIL
            ? filter_var($value, FILTER_VALIDATE_EMAIL)
            : preg_match('/^[a-z0-9\-]+(\.[a-z0-9\-]+)+$/', $value);
        if (! $valid) {
            return null;
        }

        $entry = self::firstOrCreate(['value' => $value], [
            'type'    => $type,
            'reason'  => array_key_exists($reason, self::REASONS) ? $reason : 'manual',
            'lead_id' => $leadId,
            'note'    => $note,
        ]);

        // Query-builder update: no model events, so no loop back into add().
        OutreachLead::query()
            ->where('status', OutreachLead::STATUS_ACTIVE)
            ->when(
                $type === self::TYPE_EMAIL,
                fn ($q) => $q->where('email', $value),
                fn ($q) => $q->where('email', 'like', '%@' . $value),
            )
            ->update(['status' => OutreachLead::STATUS_UNSUBSCRIBED, 'updated_at' => now()]);

        return $entry;
    }

    /**
     * Which of these e-mails are suppressed (by address or by domain).
     *
     * @param  string[]  $emails  lower-case
     * @return array<string, true>
     */
    public static function matching(array $emails): array
    {
        $emails = array_values(array_unique(array_filter($emails)));
        if (! $emails) {
            return [];
        }

        $domains = array_values(array_unique(array_map(fn ($e) => substr(strrchr($e, '@') ?: '', 1), $emails)));

        $hits = [];
        foreach (array_chunk(array_merge($emails, $domains), 1000) as $chunk) {
            foreach (self::whereIn('value', $chunk)->pluck('value') as $v) {
                $hits[$v] = true;
            }
        }

        $out = [];
        foreach ($emails as $e) {
            if (isset($hits[$e]) || isset($hits[substr(strrchr($e, '@') ?: '', 1)])) {
                $out[$e] = true;
            }
        }

        return $out;
    }

    public static function isSuppressed(string $email): bool
    {
        $email = strtolower(trim($email));

        return isset(self::matching([$email])[$email]);
    }
}
