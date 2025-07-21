<?php

namespace App\Http\Controllers\Merchand;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
   public function allCustomers()
     {
       return view('merchand.customers.allCustomers');
     }

   public function customerDetail()
     {
       return view('merchand.customers.customer_details');
     }
}
