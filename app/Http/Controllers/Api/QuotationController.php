<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QuotationController extends Controller
{
    /**
     * Display a listing of quotations.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|integer|exists:customers,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'status' => 'nullable|string|in:draft,sent,accepted,rejected,expired',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $query = DB::table('quotations');

        // Apply customer_id filter
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Apply company_id filter
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Apply limit
        $limit = $request->input('limit', 100);
        $query->limit($limit);

        // Order by created_at descending
        $query->orderBy('created_at', 'desc');

        $quotations = $query->get();

        return response()->json([
            'success' => true,
            'data' => $quotations,
            'count' => $quotations->count()
        ]);
    }
}
