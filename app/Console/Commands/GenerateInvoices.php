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

        $jobs = SmoobuJob::withTrashed()->orderBy('smoobu_created_at')->get();
        
        Invoice::truncate();

        foreach($jobs as $job) {
            $invoice = Invoice::where('smoobu_id', $job->smoobu_id)->first();

            if($invoice) {
                echo "Skipping $job->smoobu_id \r\n";
                continue;
            }

            $booking = Http::acceptJson()->withHeaders([
                'Api-Key'       => $key,
                'Cache-Control' => 'no-cache'
            ])->get('https://login.smoobu.com/api/reservations/' . $job->smoobu_id);

            if(isset($booking['status']) && ($booking['status'] == 404 || $booking['status'] == 401)) {
                continue;
            }

            if($booking['channel']['id'] === 61551) {
                echo "Skipping Direct Booking: $job->smoobu_id \r\n";
            }

            echo "Generating invoice for $job->smoobu_id \r\n";

            Invoice::create([
                'smoobu_id'     => $booking['id'],
                'customer_name' => $booking['guest-name'],
                'arrival'       => date('Y.m.d', strtotime($booking['arrival'])),
                'departure'     => date('Y.m.d', strtotime($booking['departure']))
            ]);
        }
    }
}
