<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\SmoobuJob;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function index(Request $request) {
        $invoices = SmoobuJob::with('invoices');

        if($request->keyword) {
            $invoices = $invoices->where('smoobu_id', 'LIKE', '%' . $request->keyword . '%')
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

        $invoices = $invoices->orderBy('arrival')->paginate(10);

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
        $storage = Storage::disk('invoice');
        $path = md5(strtotime('now'));

        $invoices = [];

        $jobs = SmoobuJob::whereYear('arrival', $request->year)
                    ->whereMonth('arrival', $request->month)
                    ->get();

        foreach($jobs as $job) {
            $invoice = Invoice::where('smoobu_id', $job->smoobu_id)->first();

            $booking = Http::acceptJson()->withHeaders([
                'Api-Key'       => $key,
                'Cache-Control' => 'no-cache'
            ])->get('https://login.smoobu.com/api/reservations/' . $invoice->smoobu_id);

            $total = number_format($booking['price'], 2, ",", ".");
            $net = ($booking['price'] / 110) * 100;
            $percentage = $booking['price'] - $net;

            $percentage = number_format($percentage, 2, ",", ".");
            $net = number_format($net, 2, ",", ".");

            $data = [
                'satzart'       => '0',
                'konto'         => 1110 + $invoice->id,
                'gkonto'        => '',
                'belegnr'       => '',
                'belegdatum'    => $invoice->arrival,
                'buchsymbol'    => '',
                'buchcode'      => '',
                'prozent'       => '10',
                'steuercode'    => '1',
                'betrag'        => $total,
                'steuer'        => $percentage ,
                'text'          => $booking['apartment']['name'],
                'dokument'      => (1110 + $invoice->id) . '.pdf'
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

                $path = $path . '/' . 1110 + $invoice->id . '.pdf';

                $storage->put($path, $pdf->output());

                // return $pdf->download(1110 + $invoice->id . '.pdf');
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

                $path = $path . '/' . 1110 + $invoice->id . '.pdf';

                $storage->put($path, $pdf->output());

                // return $pdf->download(1110 + $invoice->id . '.pdf');
            }
        }

        return response()->json([
            'invoices'  => $invoices,
            'zip'       => ''
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
}
