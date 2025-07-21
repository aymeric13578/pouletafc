<?php

namespace App\Http\Controllers\Merchand;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class FinanceController extends Controller
{

    public function financeList()
    {
        /* $finances = DB::table('finances')
            ->orderBy('id', 'desc')
            ->get(); */
        return view('merchand.finances.finance-list', ['finances' => []]);
    }
}