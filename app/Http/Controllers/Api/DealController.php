<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DealController extends Controller
{
    /**
     * Display a listing of deals.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'nullable|integer|exists:customers,id',
            'status' => 'nullable|string|in:lead,qualified,proposal,negotiation,töös,valmis,arveldatud,closed_won,closed_lost,tühistatud',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $query = DB::table('deals');

        // Apply client_id filter
        if ($request->has('client_id')) {
            $query->where('customer_id', $request->client_id);
        }

        // Apply status filter
        if ($request->has('status')) {
            $query->where('stage', $request->status);
        }

        // Apply limit
        $limit = $request->input('limit', 100);
        $query->limit($limit);

        // Order by created_at descending
        $query->orderBy('created_at', 'desc');

        $deals = $query->get();

        return response()->json([
            'success' => true,
            'data' => $deals,
            'count' => $deals->count()
        ]);
    }
}
