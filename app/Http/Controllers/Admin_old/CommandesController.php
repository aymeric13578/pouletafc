<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;


class CommandesController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Page/Commandes/ListCommandes');
    }
}
