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
                            $query->where('arrival', 'LIKE', '%' . $request->keyword . '%')
                                ->orWhere('departure', 'LIKE', '%' . $request->keyword . '%')
                                ->orWhere('customer_name', 'LIKE', '%' . $request->keyword . '%');
                        });
            
            if(is_int($request->keyword)) {
                $invoices = $invoices->orWhereHas('invoices', function($query) use($request) {
                    $query->where('id', 'LIKE', '%' . (intval($request->keyword) + 1110) . '%');
                });
            }
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
}
