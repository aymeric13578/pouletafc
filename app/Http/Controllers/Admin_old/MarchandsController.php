<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchand;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MarchandsController extends Controller
{
    
    public function index()
    {
        
        $merchands = Merchand::with('user')->get();
   
        return Inertia::render('Admin/Page/Marchands/ListMarchands', ['merchands'=>$merchands]);
    }
}
