<?php

namespace App\Console\Commands;

use App\Models\EmailCampaign;
use App\Services\Email\EmailCampaignService;
use Illuminate\Console\Command;
use Throwable;

class SendScheduledCampaignsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'email:send-scheduled-campaigns';

    /**
     * The console command description.
     */
    protected $description = 'Trigger queued sending for all email campaigns scheduled on or before the current time.';

    /**
     * Execute the console command.
     */
    public function handle(EmailCampaignService $campaignService): int
    {
        $campaigns = EmailCampaign::query()->readyToSend()->get();

        if ($campaigns->isEmpty()) {
            $this->info('No scheduled email campaigns are due for dispatch.');

            return self::SUCCESS;
        }

        $this->info("Found {$campaigns->count()} scheduled campaign(s) due for dispatch.");

        foreach ($campaigns as $campaign) {
            $this->line("Dispatching Campaign #{$campaign->id}: '{$campaign->name}'...");

            try {
                $campaignService->dispatchCampaign($campaign);
                $this->info("Successfully dispatched Campaign #{$campaign->id}.");
            } catch (Throwable $e) {
                $this->error("Failed dispatching Campaign #{$campaign->id}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
