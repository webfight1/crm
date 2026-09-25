<?php

namespace App\Seo\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
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
