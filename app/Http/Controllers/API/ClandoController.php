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
use App\Models\User;
use DB;

class ClandoController extends Controller
{
    public function Insertclando(Request $request)
    {
        
        
        
        $verified = Clando::where('id_user',$request->id_user)->where('status',"want")->first();
        
         $user = User::where('id',$request->id_user)->first();
          
          if($user->status != 'Success')
          {
              return response()->json(['response' => 404]);
          }
          
          
        
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
                'price'=>$request->price,
                'times'=>$request->times,
                'distance'=>$request->distance,
                'destinationName'=>$request->destinationName,
                 /*
                  | La clé 'status' était écrite deux fois dans ce tableau :
                  | 'pending' d'abord, puis $request->status. En PHP la seconde
                  | l'emporte, si bien que la première n'a jamais rien fait et que
                  | le statut d'une demande de course était en réalité dicté par
                  | l'appelant. On garde ce comportement — l'application envoie
                  | 'want', c'est ce qui met la course devant les agents — mais on
                  | l'écrit une seule fois, avec un repli explicite plutôt qu'un
                  | statut nul si le champ venait à manquer.
                  */
                 'status'=>$request->input('status', 'want'),
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
          $agent = '';
          
         
          
          if(isset($order[0]->users->id) )
          {
              $AgentCoordonate = DB::table('begin_agent_days')->where('id_user',$order[0]->users->id)->get();
          }
           if($order[0]->id_agent != null )
          {
              $agent = DB::table('users')->where('id',$order[0]->id_agent)->get();
          }
            
          if($order) return response()->json(['response' => 200, 'data'=>  $order , 'agentCoordonate'=>  $AgentCoordonate ,'agent'=> $agent   ]);
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
    
    
    /**
     * Courses à prendre, limitées à la journée en cours.
     *
     * La requête ne portait aucune borne de date : elle renvoyait toutes les
     * courses restées « want » depuis l'ouverture du service. L'accueil de
     * l'agent affichait ainsi des dizaines de demandes vieilles de plusieurs
     * mois, jamais closes, au milieu desquelles les vraies passaient inaperçues.
     *
     * La journée est celle du Cameroun, convertie en UTC pour interroger la
     * base : sans cette conversion, la liste basculerait à minuit UTC, soit une
     * heure du matin à Garoua — les courses de la soirée disparaîtraient de
     * l'écran des agents encore en service.
     */
    public function getClandoWithoutAgent()
    {
        $clando = Clando::where('id_agent','=',null)
            ->where('status','want')
            ->whereBetween('created_at', $this->bornesDuJour())
            ->orderByDesc('id')
            ->get();

        if($clando) return response()->json(['response' => 200, 'data'=> $clando]);
        else return response()->json(['response' => 404]);
    }

    /**
     * Début et fin de la journée camerounaise, exprimés en UTC.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function bornesDuJour(): array
    {
        $debut = now()->setTimezone('Africa/Douala')->startOfDay();

        return [$debut->copy()->utc(), $debut->copy()->endOfDay()->utc()];
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
    
    /**
     * Ce qui doit faire sonner le téléphone d'un agent, maintenant.
     *
     * Deux corrections ici, et elles vont dans des sens opposés.
     *
     * Les commandes étaient guettées sur le statut « want ». Or rien, nulle part
     * dans l'API, ne pose ce statut sur une commande : le bouton « Colis prêt »
     * du mur pose « waiting ». La sonnerie censée prévenir qu'un colis attend
     * d'être enlevé ne se déclenchait donc jamais — le colis rejoignait
     * seulement la liste de l'accueil, en silence. C'est « waiting » qu'il faut
     * guetter, c'est-à-dire après le geste du comptoir, et pas avant.
     *
     * Les courses clando, elles, gardent « want » : une demande de course doit
     * atteindre les agents dès qu'elle est passée, c'est tout l'objet du service.
     *
     * Ajout commun : la borne du jour. Sans elle, une ligne oubliée dans l'un de
     * ces statuts sonnait sur tous les téléphones à chaque redémarrage de
     * l'application, indéfiniment — la déduplication de l'application ne tient
     * qu'en mémoire et repart à zéro à chaque lancement.
     */
    public function getActiveCommand(Request $request)
    {
         $debutDuJour = now()->setTimezone('Africa/Douala')->startOfDay();
         $bornes = [$debutDuJour->copy()->utc(), $debutDuJour->copy()->endOfDay()->utc()];

         $order = Clando::where('status',"want")
             ->where('id_agent',null)
             ->whereBetween('created_at', $bornes)
             ->first();

         $order_detail = order_detail::where('status',"waiting")
             ->where('id_agent',null)
             ->whereBetween('created_at', $bornes)
             ->orderBy('id','desc')
             ->first();
         
         
         
         
         
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
    
    
    
    
    
        
    public function historiqueClandoUser(Request $request)
    {
        $clando = Clando::where('id_user',$request->id_user)->get();

        if($clando) return response()->json(['response' => 200, 'data'=> $clando]);
        else return response()->json(['response' => 200, 'data'=> null]);
    }
    
    public function storeHistory(Request $request)
    {
        $insert = DB::table('historyClando')
        ->insert([
            
            'name' => $request->name,
            'longitude' => $request->longitude,
             'latitude' => $request->latitude,
              'id_user' => $request->id_user,
            
            ]);
        
    }
    
      public function getClandoHistoryResearch(Request $request)
    {
        $clando = DB::table('historyClando')
        ->where('id_user',$request->id_user)->get();
        
        
          if($clando) return response()->json(['response' => 200, 'data'=> $clando]);
        else return response()->json(['response' => 200, 'data'=> null]);
        
    }
    
    
      public function takePosition(Request $request)
    {
        $insert = DB::table('lieux')
        ->insert([
            
            'name' => $request->name,
            'longitude' => $request->longitude,
             'latitude' => $request->latitude,
              'id_agent' => $request->id_agent,
            
            ]);
            
               if($insert)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
        
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}
