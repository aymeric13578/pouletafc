<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
   public function allCustomers()
     {
       return view('admin.customers.allCustomers');
     }

   public function customerDetail()
     {
       return view('admin.customers.customer_details');
     }
}
