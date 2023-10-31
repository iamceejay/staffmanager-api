<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;

class RemoveDirectBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffmanager:remove-direct-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove direct booking invoices.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = getenv('SMOOBU_KEY');

        $invoices = Invoice::get();

        foreach($invoices as $invoice) {
            $booking = Http::acceptJson()->withHeaders([
                'Api-Key'       => $key,
                'Cache-Control' => 'no-cache'
            ])->get('https://login.smoobu.com/api/reservations/' . $invoice->smoobu_id);

            if($booking['channel']['id'] === 61551) {
                Invoice::where('smoobu_id', $invoice->smoobu_id)->delete();
            }
        }
    }
}
