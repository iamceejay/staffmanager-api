<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\SmoobuJob;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Zip;

class InvoiceController extends Controller
{
    public function index(Request $request) {
        $invoices = SmoobuJob::select('smoobu_jobs.*')
                ->join('invoices', 'invoices.smoobu_id', '=', 'smoobu_jobs.smoobu_id')
                ->orderBy('invoices.id', 'desc')
                ->with('invoices');

        if($request->keyword) {
            $invoices = $invoices->where('smoobu_id', 'LIKE', '%' . $request->keyword . '%')
                        ->orWhere('smoobu_created_at', 'LIKE', '%' . $request->keyword . '%')
                        ->orWhereHas('invoices', function($query) use($request) {
                            if(is_numeric($request->keyword)) {
                                $query->where('arrival', 'LIKE', '%' . $request->keyword . '%')
                                    ->orWhere('departure', 'LIKE', '%' . $request->keyword . '%')
                                    ->orWhere('customer_name', 'LIKE', '%' . $request->keyword . '%')
                                    ->orWhere('id', 'LIKE', '%' . (intval($request->keyword) - 1110) . '%');
                            } else {
                                $query->where('arrival', 'LIKE', '%' . $request->keyword . '%')
                                    ->orWhere('departure', 'LIKE', '%' . $request->keyword . '%')
                                    ->orWhere('customer_name', 'LIKE', '%' . $request->keyword . '%');
                            }
                        });
        }

        $invoices = $invoices->has('invoices')->paginate(10);

        return response()->json([
            'message'   => 'Listing invoices',
            'invoices'  => $invoices
        ]);
    }

    public function download(Request $request) {
        $key = getenv('SMOOBU_KEY');

        $invoice = Invoice::where('id', $request->id)->first();

        $booking = Http::acceptJson()->withHeaders([
            'Api-Key'       => $key,
            'Cache-Control' => 'no-cache'
        ])->get('https://login.smoobu.com/api/reservations/' . $invoice->smoobu_id);

        if($booking) {
            if($booking['type'] !== 'cancellation') {
                $pdf = PDF::loadView(
                    'invoice-confirmation',
                    [
                        'invoice'   => $booking,
                        'number'    => 1110 + $invoice->id,
                        'address'   => $invoice->customer_address,
                        'customer'  => $invoice->customer_name,
                    ]
                );

                return $pdf->download(1110 + $invoice->id . '.pdf');
            } else {
                $pdf = PDF::loadView(
                    'invoice-cancelled',
                    [
                        'invoice'   => $booking,
                        'number'    => 1110 + $invoice->id,
                        'address'   => $invoice->customer_address,
                        'customer'  => $invoice->customer_name,
                    ]
                );

                return $pdf->download(1110 + $invoice->id . '.pdf');
            }
        }
    }

    public function csv(Request $request) {
        $key = getenv('SMOOBU_KEY');
        $storage = Storage::disk('public');
        $folder_name = md5(strtotime('now'));
        $path = 'invoices-temp/' . $folder_name . '/';
        $zip_name = storage_path('app/public/') . 'invoices-temp/' . $folder_name . '.zip';

        if(!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $invoices = [];
        $zip = Zip::create($zip_name);

        $jobs = SmoobuJob::withTrashed()
            ->whereBetween('smoobu_created_at', [$request->start, $request->end])
            ->get();

        foreach($jobs as $job) {
            $invoice = Invoice::where('smoobu_id', $job->smoobu_id)->first();

            if(!$invoice) {
                continue;
            }

            $booking = Http::acceptJson()->withHeaders([
                'Api-Key'       => $key,
                'Cache-Control' => 'no-cache'
            ])->get('https://login.smoobu.com/api/reservations/' . $invoice->smoobu_id);

            if(isset($booking['status']) && ($booking['status'] == 404 || $booking['status'] == 401)) {
                continue;
            }

            $total = number_format($booking['price'], 2, ",", ".");
            $net = ($booking['price'] / 110) * 100;
            $percentage = $booking['price'] - $net;

            $konto = '';
            
            if($booking['apartment']['id'] == 62521) {
                $konto = '200032';
            }

            if($booking['apartment']['id'] == 62522) {
                $konto = '200031';
            }

            $percentage = number_format($percentage, 2, ",", ".");
            $net = number_format($net, 2, ",", ".");

            $data = [
                'satzart'       => '0',
                'konto'         => $konto,
                'gkonto'        => '4030',
                'belegnr'       => 1110 + $invoice->id,
                'belegdatum'    => date('Y.m.d', strtotime($job->smoobu_created_at)),
                'buchsymbol'    => 'AR',
                'buchcode'      => '1',
                'prozent'       => '10',
                'steuercode'    => '1',
                'betrag'        => $total,
                'steuer'        => $percentage ,
                'text'          => $booking['apartment']['name'],
                'dokument'      => (1110 + $invoice->id) . '.pdf',
                'channel'       => $booking['channel']['name']
            ];

            array_push($invoices, $data);

            // Make PDF
            if($booking['type'] !== 'cancellation') {
                $pdf = PDF::loadView(
                    'invoice-confirmation',
                    [
                        'invoice'   => $booking,
                        'number'    => 1110 + $invoice->id,
                        'address'   => $invoice->customer_address,
                        'customer'  => $invoice->customer_name,
                    ]
                );

                $storage->put($path . (1110 + $invoice->id) . '.pdf', $pdf->output());
                $zip->add(storage_path('app/public/') . $path . (1110 + $invoice->id) . '.pdf');
            } else {
                $pdf = PDF::loadView(
                    'invoice-cancelled',
                    [
                        'invoice'   => $booking,
                        'number'    => 1110 + $invoice->id,
                        'address'   => $invoice->customer_address,
                        'customer'  => $invoice->customer_name,
                    ]
                );

                $storage->put($path . (1110 + $invoice->id) . '.pdf', $pdf->output());
                $zip->add(storage_path('app/public/') . $path . (1110 + $invoice->id) . '.pdf');
            }
        }

        $zip->close();

        usort($invoices, function($a, $b) {
            return $a['belegnr'] - $b['belegnr'];
        });

        return response()->json([
            'invoices'  => $invoices,
            'zip'       => $folder_name . '.zip'
        ]);
    }

    public function details(Request $request) {
        $invoice = Invoice::where('id', $request->id)->first();

        return response()->json([
            'status'    => 'success',
            'invoice'   => $invoice
        ]);
    }

    public function update(Request $request) {
        try {
            $update = Invoice::where('id', $request->id)->update([
                'customer_name'     => $request->name,
                'customer_address'  => $request->address
            ]);

            return response()->json([
                'status'    => 'success',
                'update'    => $update
            ]);
        } catch(Throwable $e) {
            return response()->json([
                'status'    => 'error',
                'message'   => 'Internal server error. Please try again.'
            ], 500);
        }
    }

    public function downloadZip(Request $request) {
        return response()->download(storage_path('app/public/') . 'invoices-temp/' . $request->file);
    }
}
