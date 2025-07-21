<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;


class NotificationController extends Controller
{
    public function getNotification()
    {
        
        $notification = Notification::where('status','Success')->get();
        
      
        
          if($notification->isNotEmpty()) return response()->json(['response' => 200, 'data'=>  $notification ]);
        else return response()->json(['response' => 404]);
        
    }
}
