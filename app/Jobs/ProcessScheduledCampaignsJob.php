<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Services\Campaign\CampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduledCampaignsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CampaignService $campaignService): void
    {
        $dueCampaigns = Campaign::scheduled()->get();

        foreach ($dueCampaigns as $campaign) {
            try {
                Log::info("ProcessScheduledCampaignsJob: Launching scheduled campaign #{$campaign->id} ('{$campaign->name}').");
                $campaignService->launchCampaign($campaign);
            } catch (\Exception $e) {
                Log::error("ProcessScheduledCampaignsJob: Failed to launch scheduled campaign #{$campaign->id}: {$e->getMessage()}");
            }
        }
    }
}
