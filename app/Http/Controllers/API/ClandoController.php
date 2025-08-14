<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Clando;
use App\Models\Agent;
use App\Fonction\Fonction;
use App\Models\begin_agent_days;
use App\Models\order_detail;
use App\Models\Parameter;
use DB;

class ClandoController extends Controller
{
    public function Insertclando(Request $request)
    {
        
        
        
        $verified = Clando::where('id_user',$request->id_user)->where('status',"want")->first();
        
        if(isset($verified))
        {
            $verified->update(
                [
                    'status'=> "declin"    ]);
        }
        
        
        $parameter = Parameter::where('status','Success')->first();
        $commission_agent = 0;
        if(isset($parameter))
        {
            $commission_agent = $request->price*$parameter->clando_agent_commission/100;
        }
        
     
           do {
            $ref = 'REF_' . (new Fonction())->genUniqueID('10');
            $find_ref = \DB::select('select * from clando where ref="' . $ref . '"');
        } while (!empty($find_ref));
        
         $order  = Clando::create([
                'ref'=> $ref,
                'id_user' => $request->id_user,
                'latMyPosition' => $request->latMyPosition,
                'lonMyPosition' =>   $request->lonMyPosition,
                'latDestination' => $request->latDestination,
                'lonDestination'=> $request->lonDestination,
                'status'=>'pending',
                'price'=>$request->price,
                'times'=>$request->times,
                'distance'=>$request->distance,
                'destinationName'=>$request->destinationName,
                 'status'=>$request->status,
                 'type'=>$request->type,
                 'commission_agent'=>$commission_agent
             
            ]);
            
          if($order) return response()->json(['response' => 200, 'data'=>  $order ]);
        else return response()->json(['response' => 404]);
        
        
    }
    
    public function getClandoHistorique(Request $request)
    {
        $order = order_detail::where('ref', $request->ref)->first();
        
        
        $clando = Clando::where('id_order',$order->id)->orderBy('id', 'desc')->first();
      
         if($order) return response()->json(['response' => 200, 'data'=>  $clando ]);
          else return response()->json(['response' => 404]);
        
        
    }
    
        public function getClandoAgent(Request $request)
        {
            $order = Clando::where('id_agent',$request->id_user)->where('status',"!=","Success")->get();
            
        if($order) return response()->json(['response' => 200, 'data'=>  $order ]);
        else return response()->json(['response' => 404]);
        }
    
    
      public function getclando(Request $request)
    {
        
          $order = Clando::where('ref',$request->ref)->with('users')->get();
          $AgentCoordonate= '';
          
         
          
          if(isset($order[0]->users->id) )
          {
              $AgentCoordonate = DB::table('begin_agent_days')->where('id_user',$order[0]->users->id)->get();
          }
            
          if($order) return response()->json(['response' => 200, 'data'=>  $order , 'agentCoordonate'=>  $AgentCoordonate  ]);
        else return response()->json(['response' => 404]);
        
        
    }
    
    
    
    
        public function updatePositionAgent(Request $request)
    {
        
          $order = Clando::where('ref',$request->ref);
          
          $update = $order
          ->update([
              
              'latAgent'=>$request->latAgent,
              'lonAgent'=>$request->lonAgent,
              ]);
           
          
          if($update)
          {
                if($order) return response()->json(['response' => 200, 'data'=>  $order->get()  ]);
          }
            

        else return response()->json(['response' => 404]);
        
        
    }
    
    
    public function getClandoWithoutAgent()
    {
        $clando = Clando::where('id_agent','=',null)->where('status','want')->get();

        if($clando) return response()->json(['response' => 200, 'data'=> $clando]);
        else return response()->json(['response' => 404]);
    }
    
    
    
    
    
    
     public function takeClandoCommand(Request $request)
    {
        
          $order = Clando::where('ref',$request->ref)->first();
          
          
          

         
         
         
          $solde =(new Fonction())->solde($request->id_agent);
          
         
          
          
          if($solde['solde'] < $order->commission_agent)
        {
                   return response()->json(['response' => 404,'message' => "Solde insuffisant !! veuillez recharger votre compte", 'retour' => 0, 'solde'=> 'true']); 
        }
         
         
          
          $agent = Agent::where('id_user',$request->id_agent)->first();
        
        if(!isset($agent))
        {
                  return response()->json(['response' => 404,'message' => "Vous n'êtes pas un agent", 'retour' => 0]); 
        }
        
        
        
        
        $freeStatusAgent = Agent::where('id_user',$request->id_agent)->update([
            
            'freeStatus' => 0
            
            ]);
            
            
            
            
            
            
            
            
            
            
            
            
            
        
        
          if($order->id_agent==null)
          {
             $insert =  $order->update([
                  'id_agent'=> $request->id_agent,
                  'status'=>  'process',
                  'latAgent'=> $request->latAgent,
                  'lonAgent'=> $request->lonAgent,
                  'matricule_vehicule'=> $agent->matricule_vehicule
                  
                  
                  ]);
                  
                  
                  if($insert){
                                return response()->json(['response' => 200, 'data'=>$insert ]);
                  }
                  else
                  {
                     return response()->json(['response' => 404,'message' => 'impossible de prendre la commande' , 'retour' => 0]); 
                  }
          }
          
          
          
           
         return response()->json(['response' => 404, 'message'=> 'désolé cette commande est déjà prise','retour' => 1 ]);
        
        
    }
    
    public function getActiveCommand(Request $request)
    {
         $order = Clando::where('status',"want")->where('id_agent',null)->first();
         
         $order_detail = order_detail::where('status',"want")->where('id_agent',null)->orderBy('id','desc')->first();
         
         
         
         
         
         $agent = Agent::where('id_user',$request->id_user)->first();
      
      
          if($agent->freeStatus == 0  || $agent->in_activity == 0)
             
             {
                 return response()->json(['response' => 400 ]);
             }
             
           
             
         
         if($order ||  $order_detail)
         {
               
  
         
             
               if($order && !isset($order_detail))
         {
             
            
            $declin = DB::table('declin_command')->where('id_user',$request->id_user)->where('id_clando',$order->id)->get();
            
            if($declin->isNotEmpty())
             
             {
                 return response()->json(['response' => 400 ]);
             }
             
             
            
         }  
             
             
                 if($order_detail  && !isset($order))
         {
             
            
            $declin_order = DB::table('declin_command')->where('id_user',$request->id_user)->where('id_order',$order_detail->id)->get();
            
            if($declin_order->isNotEmpty())
             
             {
                 return response()->json(['response' => 400]);
             }
            
         }  
        
          
             
             
             
             return response()->json(['response' => 200, 'data'=>$order , 'order_detail'=>$order_detail ]);
         }
         
          return response()->json(['response' => 400 ]);
            
    }
    
    
    
    
         public function updateClandoStatus(Request $request)
    {
         $order = Clando::where('ref',$request->ref_order)->update([
                  'status'=>  $request->status
                  ]);
                  
                  
                  
                  
                  
         if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
        
    }
    
    
         public function getAllClando()
    {
         $clando = Clando::where('status',"!=",'declin')->where('status',"!=",'failed')->get();
                  
                  
         if($clando)
         {
             return response()->json(['response' => 200 , 'data' => $clando]);
         }
          return response()->json(['response' => 400 ]);
        
    }
    
    
    
    
    
     public function declinCommand(Request $request)
    {
        
         $order = DB::table('declin_command')->insert([
             'id_user' => $request->id_user, 
             'id_clando'=>$request->id_clando
             
             
             ]);
             
  
         
         if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
            
    }
    
      public function declinCommandAfterTake(Request $request)
    {
        
        $order = Clando::where('ref',$request->ref)->update([
                
                  'status'=>  'declin'
                  
                  
                  ]);
                  
                  
        $freeStatusAgent = Agent::where('id_user',$request->id_user)->update([
            
            'freeStatus' => 1
            
            ]);
         
         if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
            
    }
    
    public function mapAftertake(Request $request)
    {
         $order = Clando::where('ref',$request->ref)->update([
                
                  'status'=>  'take'
                  
                  
                  ]);
        
          if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
    }
       public function terminatedCourse(Request $request)
    {
         $order = Clando::where('ref',$request->ref)->update([
                
                  'status'=>  'Success'
                  
                  ]);
                  
                                
        $freeStatusAgent = Agent::where('id_user',$request->id_user)->update([
            
            'freeStatus' => 1
            
            ]);
        
          if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
    }
    
}
