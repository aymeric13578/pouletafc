<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Agent;

class GeolocalisationController extends Controller
{
     public function getLocationAgent()
     {
         
         $data = Agent::Where('in_activity',1)->select('actual_lat_position_agent','actual_lon_position_agent','agent_name')->get();
         
          return response()->json([
                "response"=>200,
                "data"=>$data,
                "lenght"=>count($data),
    
            ]);
         
     }
}
