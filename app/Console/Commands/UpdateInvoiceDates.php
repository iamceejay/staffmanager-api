<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\SmoobuJob;
use Carbon\Carbon;

class UpdateInvoiceDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffmanager:update-invoice-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update invoice created-at date.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jobs = SmoobuJob::all();

        foreach($jobs as $job) {
            $invoice = Invoice::where('smoobu_id', $job->smoobu_id)->first();

            if($invoice) {
                $newCreatedAt = Carbon::parse($job->end_date)->addDay();

                if($invoice->created_at->eq($newCreatedAt)) {
                    echo "Skipped: $job->smoobu_id \r\n";
                    continue;
                }

                $invoice->created_at = $newCreatedAt;
                $invoice->save();

                echo "Updated: $job->smoobu_id \r\n";
            }
        }
    }
}
