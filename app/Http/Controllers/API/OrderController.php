<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Cart;
use App\Models\order_detail;
use App\Fonction\Fonction;
use App\Models\CartItem;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificationMail;
use App\Models\Clando;
use App\Models\Agent;
use App\Models\Parameter;
use DB;
class OrderController extends Controller
{
    public function CreateOrder(Request $request)
    {
        
          
          
           do {
            $ref = 'REF_' . (new Fonction())->genUniqueID('10');
            $find_ref = \DB::select('select * from order_details where ref="' . $ref . '"');
               } while (!empty($find_ref));
        
        
         $cartItems = CartItem::where('cart_id', $request->cart_id)
        ->with('product')
        ->where('status','Success')
        ->get();
         $totalamount = 0 ;
         $quantity = 0;
         
           
    
    foreach($cartItems as $data)
    {
        $totalamount = $data->amount*$data->quantity +  $totalamount;
        $quantity = $quantity + $data->quantity ;
    }
    
    $cart = Cart::where('id', $request->cart_id)->update(
        
        [
            'status' => "Success"
            
            
        ]);
        
        
     $orderverified = order_detail::where('id_user',$request->user_id)->where('id_cart',$request->cart_id)->first();
     
 
    $user = User::where('id',$request->user_id)->first();
              
    $lat = $user->latitude;
    $lon = $user->longitude;
              
     
     
     
     
      $parameter = Parameter::where('status','Success')->first();
        $commission_agent = 0;
        if(isset($parameter))
        {
            $commission_agent = $request->price*$parameter->clando_agent_commission/100;
        }
        
     
     
     if(!isset($orderverified))
     {
         
         
             $order  = order_detail::create([
                'id_user' => $request->user_id,
                'id_cart' => $request->cart_id,
                'qty' =>  $quantity, 
                'price' =>$totalamount + $request->delivery_fees,
                'panier_price'=>$totalamount,
                'ref'=>$ref,
                'status'=>'pending',
                'latitude'=> $lat,
                'longitude'=> $lon,
                'delivery_code'=>rand(0, 10000),
                'address'=>$request->delivery_address,
                'commission_agent'=>$commission_agent,
                'delivery_fees'=>$request->delivery_fees,
            ]);
            
            
            $agent = User::Where('id',$request->user_id)->first();
            
            $content = "Votre commande N° ".$ref.". a été reçu .Le service client poulet AFC vous contacteras d'ici quelques instants .... Merci de patienter.
Contact service client : 697 526 980";
            //$content = "Vous venez de passer une commande N° ".$ref.". La direction POULET AFC vous contactera dans quelques instants .... Merci de patienter";
            $title = "POULET AFC COMMAND";
            $object = 'POULET AFC COMMAND';
            Mail::to($agent->email)
                ->send(new NotificationMail($object, $content, $title));
                
                
         $function  = new Fonction();
         $function->sendSms("Votre commande N° ".$ref.". a été reçu .Le service client poulet AFC vous contacteras d'ici quelques instants .... Merci de patienter.
Contact service client : 697 526 980",$user->phone);
                
     }
     else
     {
         $order = $orderverified;
     }
        
        
     
            
        if($order) return response()->json(['response' => 200, 'data'=>  $order ]);
        else return response()->json(['response' => 404]);
        
    }
    
    
    
    
    
    public function createclandoorder(Request $request)
    {
        
         
        
         $order = order_detail::where('ref', $request->ref)->with('carts')->first();
         
         
         
          
          
         $verified = Clando::where('id_user',$order->id_user)->where('status',"want")->first();
        
        if(isset($verified))
        {
            $verified->update(
                [
                    'status'=> "declin"    ]);
        }
        
           do {
            $ref = 'REF_' . (new Fonction())->genUniqueID('10');
            $find_ref = \DB::select('select * from clando where ref="' . $ref . '"');
        } while (!empty($find_ref));
        
         $clando  = Clando::create([
                'ref'=> $ref,
                'id_user' => $order->id_user,
                'latMyPosition' => 9.45932019927271,
                'lonMyPosition' =>   13.385917196974097,
                'latDestination' => $order->latitude,
                'lonDestination'=> $order->longitude,
                'status'=>'declin',
                'price'=>$order->price,
                'times'=>$request->times,
                'distance'=>$request->distance,
                'destinationName'=>$request->destinationName,
                 'type'=>'vip',
                 'delivery_type'=>'delivery',
                 'id_order'=>$order->id,
             
            ]);
            
          if($order) return response()->json(['response' => 200, 'data'=>  $clando ]);
        else return response()->json(['response' => 404]);
        
    }
    

     public function getOrder(Request $request)
         {
        
             $code="";
             $codeLiveur = "en attente";
             $order = order_detail::where('ref', $request->ref_order)->with('carts.cartItems.product')->with('user')->get();
             

             if($order[0]->id_agent != null) 
             {
                 $code = User::where('id', $order[0]->id_agent)->get();
                  $codeLiveur = $code[0]->ref;
                 
             }
             
             
             if($order) return response()->json(['response' => 200, 'data'=> $order  , 'code_agent'=>$codeLiveur, 'info_agent'=>$code]);
             else return response()->json(['response' => 404]);
        
         }
    
      public function declinOrderCommand(Request $request)
        {
        
         $order = DB::table('declin_command')->insert([
             'id_user' => $request->id_user, 
             'id_order'=>$request->id_order
             ]);
         if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
            
        }
    
     public function takeOrderCommand(Request $request)
    {
        
          $order = order_detail::where('ref',$request->ref)->first();
          
        
       
       
          
          $solde =(new Fonction())->solde($request->id_agent);
         
          if($solde['solde'] < $order->price)
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
    
    
    
    
    
    
    
    
    
    
    
    
    public function getAllOrder(Request $request)
    {
        
             
             $order = order_detail::where('status', "pending")->with('carts')->with('user')->get();
             if($order) return response()->json(['response' => 200, 'data'=> $order ]);
             else return response()->json(['response' => 404]);
        
    }
    
    
    public function updateOrderStatus(Request $request)
    {
         $order = order_detail::where('ref',$request->ref_order)->update([
                  'status'=>  $request->status
                  ]);
                  
                  
         if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
        
    }
    
    
    
    public function getAllOrderWithoutCondition()
    {
        $order = order_detail::where('status',"!=" ,"failed")->with('carts')->with('user')->orderBy('id','desc')->get();
        
         if($order) return response()->json(['response' => 200, 'data'=> $order ]);
         else return response()->json(['response' => 404]);
        
    }
    
    
    
    
    
    
    
    
    
     public function getAllWithoutSellerOrder(Request $request)
    {
             $order = order_detail::where('id_agent','=',null)->where('status','!=',"pending")->with('carts')->with('user')->get();
             if($order) return response()->json(['response' => 200, 'data'=> $order]);
             else return response()->json(['response' => 404]);
        
    }
    
         public function updatePositionAgentOrder(Request $request)
    {
        
          $order = order_detail::where('ref',$request->ref);
          
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
    
    
     public function mapAftertakeOrder(Request $request)
    {
         $order = order_detail::where('ref',$request->ref)->update([
                
                  'status'=>  'take'
                  
                  
                  ]);
        
          if($order)
         {
             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);
    }
    
    
       public function getSellerOrder(Request $request)
    {
        
        
             $order = order_detail::where('id_agent', $request->id_agent)->with('carts')->with('user')->get();

            
             if($order) return response()->json(['response' => 200, 'data'=> $order]);
             else return response()->json(['response' => 404]);
        
    }
    
       public function takeOrderBySeller(Request $request)
    {
        
        
             $order = order_detail::where('ref', $request->ref)->first();
             
             
             $orderUpdate = $order->update([
                 
                 'id_agent' => $request->id_seller
                 
                 
                 ]);
                 
                 
                 
        
                
                

            
             if( $orderUpdate) 
             {
                 
            $agent = User::Where('id',$request->id_seller)->first();
            $content = "Félicitation vous venez de prendre une commande ".$order->ref;
            $title = "POULET AFC AGENT";
            $object = 'POULET AFC AGENT';
            Mail::to($agent->email)
                ->send(new NotificationMail($object, $content, $title));
                
                
            $user = User::Where('id',$order->id_user)->first();
            $content = "Un agent vient de prendre la commande  ".$order->ref.".Rendez-vous dans votre historique pour voir les détails" ;
            $title = "POULET AFC COMMANDE";
            $object = 'POULET AFC COMMANDE';
            Mail::to($user->email)
                ->send(new NotificationMail($object, $content, $title));
                
                
                  return response()->json(['response' => 200, 'message'=> "requête effectuée avec success"]);
                 
             }
             
             
            
             else return response()->json(['response' => 404]);
        
    }
    
    
       public function getUserOrder(Request $request)
    {
        
        
            
             $order = order_detail::where('id_user', $request->id_user)->with('carts.cartItems.product')->with('agent')->orderBy('id','desc')->get();

            
             if($order) return response()->json(['response' => 200, 'data'=> $order]);
        else return response()->json(['response' => 404]);
        
    }
    
    public function insertPosition(Request $request)
    {
        
         $order = User::where('id', $request->id_user)->update(
            [
                
              'latitude' => $request->latitude,
              'longitude'=> $request->longitude,
                
                ] );

        
        
           if($order) return response()->json(['response' => 200]);
        else return response()->json(['response' => 404]);
        
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
          public function declinCommandAfterTakeOrder(Request $request)
    {
        
        $order = order_detail::where('ref',$request->ref)->update([
                
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
    
  
       public function terminatedCourseOrder(Request $request)
    {
        
        
        
         $order = order_detail::where('ref',$request->ref)->first();
         
         if($order->delivery_code == $request->delivery_code)
         {
             $update =$order->update([
                
                  'status'=>  'Success'
                  
                  ]);
                  
                  
                       
                   $freeStatusAgent = Agent::where('id_user',$request->id_user)->update([
            
            'freeStatus' => 1
            
            ]);
        
          if($update)
         {
             return response()->json(['response' => 200]);
         }
         
         return response()->json(['response' => 400 , 'message'=> 'Une erreur est survenue' ]);
         
         
         }
         
         
          return response()->json(['response' => 400 , 'message'=> 'code incorrect !!! 3 essai restants']);
    }
    
    
    public function verifiedDeliveryCode(Request $request)
    {
         
        
    }
    
    
      public function getCommandAgent(Request $request)
        {
            $order = order_detail::where('id_agent',$request->id_user)->where('status',"!=","Success")->get();
            
        if($order) return response()->json(['response' => 200, 'data'=>  $order ]);
        else return response()->json(['response' => 404]);
        }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}
