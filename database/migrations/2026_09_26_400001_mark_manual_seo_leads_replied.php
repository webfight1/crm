<?php

use App\Seo\Services\WarmClientService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Hand-added warm clients count as having replied (see WarmClientService::addManual),
// so their next mail doesn't start the SEO pipeline a second time.
return new class extends Migration
{
    public function up(): void
    {
        $campaignIds = DB::table('outreach_campaigns')->where('name', WarmClientService::MANUAL_CAMPAIGN)->pluck('id');

        DB::table('outreach_leads')->whereIn('campaign_id', $campaignIds)->where('replied', false)
            ->update(['replied' => true, 'replied_at' => DB::raw('COALESCE(replied_at, enrolled_at, created_at)')]);
    }

    public function down(): void {}
};
