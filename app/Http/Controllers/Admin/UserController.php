<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class UserController extends Controller
{
    public function listUser()
   {
          $listUser = DB::table('users')
          ->orderBy('id','desc')
          ->get();
      return view('admin.users.listUsers', ['listUsers'=>$listUser]);
   }

public function addUser()
   {
      //
   }


   
}
