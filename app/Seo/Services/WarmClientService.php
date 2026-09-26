<?php

namespace App\Seo\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachLead;
use App\Seo\Playbook;
use Illuminate\Support\Facades\DB;

/**
 * Turns an SEO outreach lead that answered positively into CRM records:
 * Company (if named) + Customer (status prospect) + Deal at the Playbook
 * stage. Idempotent — a lead that already has a deal is returned as-is.
 */
class WarmClientService
{
    private const DEAL_STAGES = ['lead', 'qualified', 'proposal', 'negotiation'];

    /** Hidden holder campaign for hand-added warm clients: inactive, no steps — never sends. */
    public const MANUAL_CAMPAIGN = 'SEO – käsitsi lisatud kliendid';

    /**
     * A warm client the operator knows about from elsewhere (phone, referral).
     * Stored as an outreach lead so the rest of the pipeline — audit,
     * clarification e-mail, answer handling, funnel — treats it like any other.
     * Adding the same e-mail again updates that lead.
     */
    public function addManual(array $data): OutreachLead
    {
        $campaign = OutreachCampaign::firstOrCreate(
            ['name' => self::MANUAL_CAMPAIGN],
            ['description' => 'Hoiab SEO lehelt käsitsi lisatud sooje kliente. Ära aktiveeri — siit ei saadeta midagi.',
             'is_active' => false, 'daily_limit' => 0, 'reply_stop_enabled' => true, 'use_ai_line' => false],
        );
        if ($campaign->is_active) {
            $campaign->update(['is_active' => false]);
        }

        // Picked from the business register: create the Company now so the
        // registry code is kept (convert() then finds it by name).
        if (! empty($data['registrikood']) && ! Company::where('name', $data['company'])->exists()) {
            Company::firstOrCreate(['registrikood' => $data['registrikood']], [
                'name'    => $data['company'],
                'email'   => $data['email'],
                'website' => $data['website'] ?? null,
                'status'  => 'prospect',
            ]);
        }

        $position = isset($data['position']) && $data['position'] !== null ? (int) $data['position'] : null;

        $lead = OutreachLead::firstOrNew(['campaign_id' => $campaign->id, 'email' => strtolower(trim($data['email']))]);
        $lead->fill([
            'first_name'       => ($data['first_name'] ?? null) ?: 'Friend',
            'last_name'        => $data['last_name'] ?? null,
            'company'          => $data['company'],
            'website'          => $data['website'] ?? null,
            'serp_keyword'     => $data['keyword'],
            'serp_position'    => $position,
            'serp_page'        => $position ? intdiv($position - 1, 10) + 1 : null,
            'serp_url'         => $data['ranking_url'] ?? null,
            'notes'            => $data['notes'] ?? null,
            'qualification'    => OutreachLead::QUALIFICATION_LEAD,
            'status'           => OutreachLead::STATUS_COMPLETED,
            'current_step'     => 0,
            // Adding again = a fresh round: drafts, answers and the
            // extra keywords of the previous one are dropped.
            'seo_stage'          => null,
            'seo_clarify_body'   => null,
            'seo_access_body'    => null,
            'seo_extra_keywords' => null,
            'reply_intent'       => null,
            'reply_intent_reason' => null,
            // A warm client has already talked to us: their next mail must not
            // look like a first reply and start the pipeline again.
            'replied'            => true,
        ]);
        $lead->replied_at ??= now();
        // Start of this round — older mail with the same address is history.
        $lead->enrolled_at = now();
        // Quietly: flipping `replied` here must not fire the reply pipeline —
        // the caller starts it itself (HandleSeoReplyJob, manual).
        $lead->saveQuietly();

        return $lead;
    }

    public function convert(OutreachLead $lead): Deal
    {
        if ($lead->deal_id && ($deal = Deal::find($lead->deal_id))) {
            return $deal;
        }

        return DB::transaction(function () use ($lead) {
            $company = null;
            if ($lead->company) {
                $company = Company::where('name', $lead->company)->first()
                    ?? Company::create([
                        'name'     => $lead->company,
                        'email'    => $lead->email,
                        'website'  => $lead->website,
                        'industry' => $lead->industry,
                        'status'   => 'prospect',
                    ]);
            }

            $customer = Customer::whereRaw('LOWER(email) = ?', [strtolower($lead->email)])->first();
            if (! $customer) {
                $firstName = $lead->first_name && strcasecmp($lead->first_name, 'Friend') !== 0
                    ? $lead->first_name
                    : ($lead->companyShort() ?: 'Klient');

                $customer = Customer::create([
                    'first_name' => $firstName,
                    'last_name'  => (string) ($lead->last_name ?? ''),
                    'email'      => $lead->email,
                    'status'     => 'prospect',
                    'company_id' => $company?->id,
                    'notes'      => $this->notes($lead),
                ]);
            }

            $stage = Playbook::get('auto.deal_stage');
            $deal = Deal::create([
                'title'       => 'SEO – ' . ($lead->company ?: $lead->website ?: $lead->email),
                'description' => $this->notes($lead),
                'value'       => 0,
                'stage'       => in_array($stage, self::DEAL_STAGES, true) ? $stage : 'qualified',
                'probability' => 30,
                'customer_id' => $customer->id,
                'company_id'  => $company?->id,
                'user_id'     => $this->ownerId(),
            ]);

            $lead->update(['customer_id' => $customer->id, 'deal_id' => $deal->id]);

            return $deal;
        });
    }

    private function notes(OutreachLead $lead): string
    {
        return trim(implode("\n", array_filter([
            'Allikas: SEO outreach' . ($lead->campaign?->name ? " ({$lead->campaign->name})" : ''),
            $lead->serp_keyword ? "Märksõna: {$lead->serp_keyword}" : null,
            $lead->serp_position ? "Positsioon: {$lead->serp_position}" . ($lead->serp_page ? " ({$lead->serp_page}. leht)" : '') : null,
            $lead->website ? "Veeb: {$lead->website}" : null,
            $lead->reply_intent_reason ? "Vastus: {$lead->reply_intent_reason}" : null,
        ])));
    }

    private function ownerId(): int
    {
        $id = Playbook::int('auto.owner_user_id');
        if ($id > 0 && User::whereKey($id)->exists()) {
            return $id;
        }

        return (int) User::orderBy('id')->value('id');
    }
}
