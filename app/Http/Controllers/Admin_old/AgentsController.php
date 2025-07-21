<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentsController extends Controller
{
    public function index()
    {

        return Inertia::render('Admin/Page/Agents/ListAgents',
    ['agents'=> Agent::with('user')->get()]);
    }
}
