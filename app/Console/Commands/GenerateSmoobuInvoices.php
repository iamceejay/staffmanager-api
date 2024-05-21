<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmoobuJob;
use Carbon\Carbon;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;

class GenerateSmoobuInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffmanager:generate-smoobu-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices for SmoobuJobs with end date of yesterday';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = getenv('SMOOBU_KEY');

        $yesterday = Carbon::yesterday()->format('Y-m-d');
        $smoobuJobs = SmoobuJob::whereDate('end', $yesterday)->get();

        foreach ($smoobuJobs as $job) {
            $resp = Http::acceptJson()->withHeaders([
                'Api-Key'       => $key,
                'Cache-Control' => 'no-cache'
            ])->get('https://login.smoobu.com/api/reservations/' . $job->smoobu_id);

            if($resp['is-blocked-booking']) {
                return false;
            }

            if($resp['channel']['id'] !== 61551) {
                Invoice::create([
                    'smoobu_id' => $job->id,
                    'customer_name' => $resp['guest-name'],
                    'arrival' => $resp['arrival'],
                    'departure' => $resp['departure'],
                ]);
            }
        }

        $this->info('Invoices generated successfully for jobs ending on ' . $yesterday);

        return 0;
    }
}
