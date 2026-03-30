<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QuotationItemController extends Controller
{
    /**
     * Display a listing of quotation items.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quotation_id' => 'nullable|integer|exists:quotations,id',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $query = DB::table('quotation_items');

        // Apply quotation_id filter
        if ($request->has('quotation_id')) {
            $query->where('quotation_id', $request->quotation_id);
        }

        // Apply limit
        $limit = $request->input('limit', 100);
        $query->limit($limit);

        // Order by id ascending (order in quotation)
        $query->orderBy('id', 'asc');

        $quotationItems = $query->get();

        // Calculate total amount
        $totalAmount = $quotationItems->sum(function($item) {
            return $item->quantity * $item->unit_price;
        });

        return response()->json([
            'success' => true,
            'data' => $quotationItems,
            'count' => $quotationItems->count(),
            'total_amount' => $totalAmount
        ]);
    }
}
