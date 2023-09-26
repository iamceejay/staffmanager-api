<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\SmoobuJob;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;

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

        $invoices = $invoices->paginate(10);

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
                    ]
                );

                return $pdf->download(1110 + $invoice->id . '.pdf');
            } else {
                $pdf = PDF::loadView(
                    'invoice-cancelled',
                    [
                        'invoice'   => $booking,
                        'number'    => 1110 + $invoice->id,
                    ]
                );

                return $pdf->download(1110 + $invoice->id . '.pdf');
            }
        }
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
