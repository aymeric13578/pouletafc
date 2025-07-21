<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Agent;


use DB;

class StatusController extends Controller
{
    public function changeStatus(Request $request)
    {

        $insert = DB::table(''.$request->base)
        ->where('id',$request->id)
        ->update([
            'status'=>$request->status
        ]);


        if($insert)  session()->flash("good","Requête effectuée avec success");

       else  session()->flash("error","Impossible de continuer");

       return back();



    }

    public function changeRole(Request $request)
    {


      
        $user =  User::where('id',$request->id)
        ->first();

        if($request->role == "agent")
        {
            $agent = Agent::where('id_user',$user->id)
            ->first();
    
    
            if(isset($agent))
            {
                $agent->update([
                    'status'=> "Success"
                ]);
            }
            else
            {
                Agent::create(
                    [
                            'agent_name'=> $user->name,
                            'phone'=>$user->phone,
                            'id_user'=> $user->id,
                            'ref'=> $user->ref,
                            'latitude'=> 9.298160757436905,
                            'longitude'=> 13.399066915343388,
                    ]
                    );
            }

        }

       

        
        $insert =  User::where('id',$request->id)
        ->update([
            'role'=>$request->role
        ]);



        if($insert)  session()->flash("good","Requête effectuée avec success");

        else  session()->flash("error","Impossible de continuer");
 
        return back();


    }
}
