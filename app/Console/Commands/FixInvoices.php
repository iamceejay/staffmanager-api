<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\SmoobuJob;

class FixInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffmanager:fix-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Correct the smoobu_id in the Invoices table to match the smoobu_id from the SmoobuJobs table';

    /**
     * Execute the console command.
     */
    public function handle() {
        $invoices = Invoice::all();
        $correctedCount = 0;

        foreach ($invoices as $invoice) {
            $smoobuJob = SmoobuJob::find($invoice->smoobu_id);

            if ($smoobuJob) {
                if ($smoobuJob->smoobu_id !== $invoice->smoobu_id) {
                    $invoice->smoobu_id = $smoobuJob->smoobu_id;
                    $invoice->save();
                    
                    $correctedCount++;
                }
            }
        }

        $this->info("Corrected $correctedCount invoices.");
        return 0;
    }
}
