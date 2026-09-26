<?php

namespace App\Seo\Services;

use App\Outreach\Models\OutreachLead;
use App\Seo\Models\SeoAudit;
use App\Seo\Playbook;

/**
 * Won SEO client → project in SEO-monitor (seo.webfight.ee): domain, a
 * Search Console property guess (sc-domain:…), the main keyword with its
 * landing page, the client's extra keywords, and (monitor.client_account)
 * a client login with a one-time "set your password" link for the e-mail.
 * Idempotent per lead (outreach_leads.seo_monitor_project_id).
 */
class SeoMonitorSyncService
{
    public function __construct(private readonly SeoMonitorClient $monitor) {}

    /**
     * An extra page of a client who already has a project: its keyword →
     * „Positsioonid“ (with the page as target), the page → „Lehed“ (PageSpeed).
     * Called when the page's audit is done; no-op without a project.
     */
    public function syncPage(SeoAudit $page): void
    {
        $projectId = $page->lead?->seo_monitor_project_id;
        if (! $projectId || ! $page->main_audit_id || $page->status !== SeoAudit::STATUS_DONE
            || ! Playbook::bool('monitor.enabled') || ! $this->monitor->enabled()
        ) {
            return;
        }
        $this->trackPage($projectId, $page);
    }

    private function trackPage(int $projectId, SeoAudit $page): void
    {
        $url = SeoMonitorClient::onSite($page->url, $this->monitor->projectSite($projectId));
        if ($page->keyword) {
            $this->monitor->addKeyword($projectId, $page->keyword, $url);
        }
        // The keyword's target already adds the page — unless the keyword was tracked before.
        $this->monitor->addPage($projectId, $url);
    }

    /** @return array{project_url:?string, invite_url:?string, note:string} */
    public function sync(OutreachLead $lead): array
    {
        if (! Playbook::bool('monitor.enabled')) {
            return ['project_url' => null, 'invite_url' => null, 'note' => ''];
        }
        if (! $this->monitor->enabled()) {
            return ['project_url' => null, 'invite_url' => null,
                    'note' => 'SEO-monitori projekti ei loodud — CRM-i .env-is puudub SEO_MONITOR_API_TOKEN.'];
        }
        if ($lead->seo_monitor_project_id) {
            if ($this->monitor->projectExists($lead->seo_monitor_project_id)) {
                return ['project_url' => $this->monitor->projectUrl($lead->seo_monitor_project_id), 'invite_url' => null,
                        'note' => 'SEO-monitori projekt oli juba olemas.'];
            }
            $lead->update(['seo_monitor_project_id' => null]); // deleted in SEO-monitor → create again
        }

        $audit = SeoAudit::main()->where('lead_id', $lead->id)->latest('id')->first();
        $site = $lead->website ?: $audit?->url;
        $origin = $site ? SiteCrawler::origin($site) : null;
        if (! $origin) {
            return ['project_url' => null, 'invite_url' => null, 'note' => 'SEO-monitori projekti ei loodud — kodulehe aadress puudub.'];
        }
        $domain = SiteCrawler::host($origin);

        $projectId = $this->monitor->createProject(
            $lead->company ?: $domain,
            $domain,
            $origin . '/',
            'sc-domain:' . $domain,
        );
        $lead->update(['seo_monitor_project_id' => $projectId]);

        // Keywords: the main one on its landing page + the client's extras.
        $landing = $audit && in_array($audit->page_source, ['csv', 'found', 'home', 'client'], true)
            ? SeoMonitorClient::onSite($audit->url, $origin . '/') : null;
        if ($lead->serp_keyword) {
            $this->monitor->addKeyword($projectId, $lead->serp_keyword, $landing);
        }
        foreach (Playbook::parseLines((string) $lead->seo_extra_keywords) as $line) {
            [$kw, $url] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            if ($kw !== '') {
                $this->monitor->addKeyword($projectId, $kw, $url ?: null);
            }
        }

        // Extra pages audited for this client (audits/{id} „Lisa lehti“).
        if ($audit) {
            foreach ($audit->root()->pages()->where('status', SeoAudit::STATUS_DONE)->get() as $page) {
                $this->trackPage($projectId, $page);
            }
        }

        $invite = null;
        $note = 'SEO-monitori projekt loodud.';
        if (Playbook::bool('monitor.client_account') && $lead->email) {
            $name = trim(($lead->first_name !== 'Friend' ? $lead->first_name : '') . ' ' . $lead->last_name) ?: ($lead->company ?: $lead->email);
            $userId = $this->monitor->grantClient($projectId, $lead->email, $name);
            $invite = $this->monitor->inviteUrl($userId);
            $note .= $invite
                ? ' Kliendikonto + paroolilink on ligipääsukirjas.'
                : ' Kliendikonto loodud, aga SEO-monitor ei anna veel paroolilinki (kutsed pole seal veel valmis).';
        }

        return ['project_url' => $this->monitor->projectUrl($projectId), 'invite_url' => $invite, 'note' => $note];
    }
}
