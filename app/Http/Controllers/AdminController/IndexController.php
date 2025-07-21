<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Auth;

class IndexController extends Controller
{
    public function index()
    {
        return view('admin.index');
    }
    public function logoutUser()
    {
        Auth::logout();
        return redirect()->route('welcome');
    }
}
