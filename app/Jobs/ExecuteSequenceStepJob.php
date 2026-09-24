<?php

namespace App\Jobs;

use App\Models\SequenceEnrollment;
use App\Models\SequenceStep;
use App\Services\Sequence\SequenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteSequenceStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $enrollmentId,
        public int $stepId
    ) {}

    public function handle(SequenceService $sequenceService): void
    {
        $enrollment = SequenceEnrollment::with(['sequence', 'contact', 'lead'])->find($this->enrollmentId);
        $step = SequenceStep::find($this->stepId);

        if (! $enrollment) {
            Log::info("ExecuteSequenceStepJob: Enrollment #{$this->enrollmentId} not found. Skipping.");

            return;
        }

        if (! $step) {
            Log::warning("ExecuteSequenceStepJob: Step #{$this->stepId} not found for Enrollment #{$this->enrollmentId}. Skipping.");

            return;
        }

        $sequenceService->executeStep($enrollment, $step);
    }
}
