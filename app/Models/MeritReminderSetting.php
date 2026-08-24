<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Meriti võlgnike meeldetuletuste seaded — üherealine konfiguratsioon
 * (sama muster nagu App\Models\Setting).
 */
class MeritReminderSetting extends Model
{
    protected $fillable = [
        'enabled',
        'min_overdue_days',
        'first_reminder_days',
        'repeat_interval_days',
        'max_reminders',
        'handoff_recipient',
        'min_days_between',
        'send_hour',
        'step1_enabled', 'step1_days', 'step1_subject', 'step1_body',
        'step2_enabled', 'step2_days', 'step2_subject', 'step2_body',
        'step3_enabled', 'step3_days', 'step3_subject', 'step3_body',
        'step4_days', 'step4_subject', 'step4_body',
        'notify_step',
        'attach_from_step',
        'company_name',
        'from_name',
        'from_email',
        'attach_pdfs',
        'max_attachments',
        'test_recipient',
    ];

    protected $casts = [
        'enabled'              => 'boolean',
        'min_overdue_days'     => 'integer',
        'first_reminder_days'  => 'integer',
        'repeat_interval_days' => 'integer',
        'max_reminders'        => 'integer',
        'min_days_between'     => 'integer',
        'send_hour'            => 'integer',
        'step1_enabled'    => 'boolean',
        'step1_days'       => 'integer',
        'step2_enabled'    => 'boolean',
        'step2_days'       => 'integer',
        'step3_enabled'    => 'boolean',
        'step3_days'       => 'integer',
        'step4_days'       => 'integer',
        'notify_step'      => 'integer',
        'attach_from_step' => 'integer',
        'attach_pdfs'      => 'boolean',
        'max_attachments'  => 'integer',
    ];

    /** Astmete arv (per-arve mudel): 1..4. */
    public const STEP_COUNT = 4;

    public static function getSettings(): self
    {
        $settings = static::first();

        if ($settings === null) {
            $settings = static::create(self::defaults());
            $settings->refresh();
        }

        return $settings;
    }

    /** Vaikeväärtused uue paigalduse jaoks (Mariuse tekstid, päevad 0/2/9/12). */
    public static function defaults(): array
    {
        return [
            'company_name'    => 'Kind Studio OÜ',
            'notify_step'     => 3,
            'attach_from_step' => 1,
            'step1_days' => 0,  'step1_subject' => self::defaultSubject(1), 'step1_body' => self::defaultBody(1),
            'step2_days' => 2,  'step2_subject' => self::defaultSubject(2), 'step2_body' => self::defaultBody(2),
            'step3_days' => 9,  'step3_subject' => self::defaultSubject(3), 'step3_body' => self::defaultBody(3),
            'step4_days' => 12, 'step4_subject' => self::defaultSubject(4), 'step4_body' => self::defaultBody(4),
        ];
    }

    /** Kuhu läheb teavitus Mariusele (notify_step astmes). */
    public function handoffRecipient(): string
    {
        return $this->handoff_recipient ?: 'marius@kind.ee';
    }

    public function companyName(): string
    {
        return $this->company_name ?: config('app.name');
    }

    /** Astmete päevakünnised [aste => päeva üle tähtaja], sorteeritud. */
    public function stepDays(): array
    {
        $days = [];
        foreach (range(1, self::STEP_COUNT) as $level) {
            $days[$level] = (int) $this->{"step{$level}_days"};
        }
        asort($days);

        return $days;
    }

    /**
     * @return array{days: int, subject: ?string, body: ?string}
     */
    public function step(int $level): array
    {
        return [
            'days'    => (int) $this->{"step{$level}_days"},
            'subject' => $this->{"step{$level}_subject"},
            'body'    => $this->{"step{$level}_body"},
        ];
    }

    public static function defaultSubject(int $level): string
    {
        return match ($level) {
            1 => '{{ettevote}} Arve nr {{arve_nr}} – täna on tasumise tähtaeg',
            2 => 'Meeldetuletus: {{ettevote}} Arve nr {{arve_nr}}',
            default => 'Korduv meeldetuletus: {{ettevote}} Arve nr {{arve_nr}}',
        };
    }

    public static function defaultBody(int $level): string
    {
        $sign = "Heade soovidega,\nMarius-Guy Allik\nmarius@kind.ee\n53486097\nKIND";

        return match ($level) {
            1 => "Tere!\n\nTuletame meelde, et {{ettevote}} arve nr {{arve_nr}} tasumise tähtaeg on täna.\n\nArve on kirjaga PDF-formaadis kaasas.\n\n{$sign}",
            2 => "Tere!\n\nMeie andmetel on arve nr {{arve_nr}} veel tasumata. Palume arve tasuda.\n\nKirjaga on kaasas PDF-formaadis arve nr {{arve_nr}} ettevõttelt {{ettevote}}.\n\n{$sign}",
            default => "Tere!\n\nMeie andmetel on arve nr {{arve_nr}} endiselt tasumata. Palume arve tasuda.\n\nKirjaga on kaasas PDF-formaadis arve nr {{arve_nr}} ettevõttelt {{ettevote}}.\n\n{$sign}",
        };
    }
}
