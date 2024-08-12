<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
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
        $invoices = Invoice::all();

        foreach($invoices as $invoice) {
            if (strpos($invoice->departure, '.') !== false) {
                $endDate = Carbon::createFromFormat('Y.m.d', $invoice->departure);
            } else {
                $endDate = Carbon::parse($invoice->departure);
            }

            $newCreatedAt = $endDate->addDay();

            if($invoice->created_at->eq($newCreatedAt)) {
                echo "Skipped: $invoice->smoobu_id \r\n";
                continue;
            }

            $invoice->created_at = $newCreatedAt;
            $invoice->save();

            echo "Updated: $invoice->smoobu_id \r\n";
        }
    }
}
