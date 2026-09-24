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

class SendCampaignBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $campaignId,
        public int $batchNumber
    ) {}

    public function handle(CampaignService $campaignService): void
    {
        $campaign = Campaign::find($this->campaignId);

        if (! $campaign) {
            Log::warning("SendCampaignBatchJob: Campaign #{$this->campaignId} not found. Skipping batch.");

            return;
        }

        $campaignService->processBatch($campaign, $this->batchNumber);
    }
}
