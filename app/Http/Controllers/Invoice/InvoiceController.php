<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\SmoobuJob;

class InvoiceController extends Controller
{
    public function index(Request $request) {
        $invoices = SmoobuJob::with('invoices')->paginate(10);

        return response()->json([
            'message'   => 'Listing invoices',
            'invoices'  => $invoices
        ]);
    }
}
