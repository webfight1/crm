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
        'min_days_between',
        'send_hour',
        'step1_enabled', 'step1_days', 'step1_subject', 'step1_body',
        'step2_enabled', 'step2_days', 'step2_subject', 'step2_body',
        'step3_enabled', 'step3_days', 'step3_subject', 'step3_body',
        'from_name',
        'from_email',
    ];

    protected $casts = [
        'enabled'          => 'boolean',
        'min_overdue_days' => 'integer',
        'min_days_between' => 'integer',
        'send_hour'        => 'integer',
        'step1_enabled'    => 'boolean',
        'step1_days'       => 'integer',
        'step2_enabled'    => 'boolean',
        'step2_days'       => 'integer',
        'step3_enabled'    => 'boolean',
        'step3_days'       => 'integer',
    ];

    public static function getSettings(): self
    {
        $settings = static::first();

        if ($settings === null) {
            $settings = static::create([
                'step1_subject' => 'Meeldetuletus tasumata arve(te) kohta',
                'step1_body'    => self::defaultBody(1),
                'step2_subject' => 'Korduv meeldetuletus tasumata arve(te) kohta',
                'step2_body'    => self::defaultBody(2),
                'step3_subject' => 'Viimane meeldetuletus tasumata arve(te) kohta',
                'step3_body'    => self::defaultBody(3),
            ]);
            // Lae DB vaikeväärtused (enabled, step-päevad jne) mällu tagasi.
            $settings->refresh();
        }

        return $settings;
    }

    /**
     * Astme konfiguratsioon ühtse struktuurina.
     *
     * @return array{enabled: bool, days: int, subject: ?string, body: ?string}
     */
    public function step(int $level): array
    {
        return [
            'enabled' => (bool) $this->{"step{$level}_enabled"},
            'days'    => (int) $this->{"step{$level}_days"},
            'subject' => $this->{"step{$level}_subject"},
            'body'    => $this->{"step{$level}_body"},
        ];
    }

    /** Sisselülitatud astmed [tase => days], sorteeritud päevade järgi. */
    public function enabledSteps(): array
    {
        $steps = [];
        foreach ([1, 2, 3] as $level) {
            if ($this->{"step{$level}_enabled"}) {
                $steps[$level] = (int) $this->{"step{$level}_days"};
            }
        }
        asort($steps);

        return $steps;
    }

    public static function defaultBody(int $level): string
    {
        $intro = match ($level) {
            1 => 'Soovime meelde tuletada, et Teil on meile tasumata järgmine(sed) arve(d):',
            2 => 'Tuletame korduvalt meelde, et alljärgnev(ad) arve(d) on endiselt tasumata:',
            default => 'Juhime tähelepanu, et vaatamata varasematele meeldetuletustele on alljärgnev(ad) arve(d) jätkuvalt tasumata:',
        };

        $outro = match ($level) {
            1 => 'Palume arve(d) tasuda esimesel võimalusel. Kui olete arve juba tasunud, siis palume seda kirja mitte arvestada.',
            2 => 'Palume võlgnevus tasuda lähipäevil. Kui olete arve juba tasunud, siis palume seda kirja mitte arvestada.',
            default => 'Palume võlgnevus viivitamatult tasuda. Kui makse ei laeku, oleme sunnitud rakendama edasisi meetmeid. Kui olete arve juba tasunud, siis palume seda kirja mitte arvestada.',
        };

        return "Lugupeetud {{nimi}}\n\n{$intro}\n\n{{arved}}\n\nTasumata kokku: {{summa}}\nÜle tähtaja: {{paevad}} päeva\n\n{$outro}\n\nLugupidamisega\n{{ettevote}}";
    }
}
