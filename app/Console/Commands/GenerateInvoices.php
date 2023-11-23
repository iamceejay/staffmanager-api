<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use App\Models\SmoobuJob;
use Illuminate\Support\Facades\Http;

class GenerateInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffmanager:generate-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices based on Smoobu bookings.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = getenv('SMOOBU_KEY');

        $jobs = SmoobuJob::orderBy('arrival')->get();
        
        // Invoice::truncate();

        foreach($jobs as $job) {
            $invoice = Invoice::where('smoobu_id', $job->smoobu_id)->first();

            if($invoice) {
                continue;
            }

            $booking = Http::acceptJson()->withHeaders([
                'Api-Key'       => $key,
                'Cache-Control' => 'no-cache'
            ])->get('https://login.smoobu.com/api/reservations/' . $job->smoobu_id);

            Invoice::create([
                'smoobu_id'     => $booking['id'],
                'customer_name' => $booking['guest-name'],
                'arrival'       => date('Y.m.d', strtotime($booking['arrival'])),
                'departure'     => date('Y.m.d', strtotime($booking['departure']))
            ]);
        }
    }
}
