<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Deposit;
use App\Models\order_detail;
use App\Models\Payment;
use App\Fonction\Fonction;
use DB;

class PaymentController extends Controller
{
    // Fonction utilitaire pour nettoyer les chaînes et forcer l'encodage UTF-8
    private function ensureUtf8($value)
    {
        if (is_string($value)) {
            // Détecter l'encodage et convertir en UTF-8 si nécessaire
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'auto');
            }
            return $value;
        } elseif (is_array($value)) {
            // Si c'est un tableau, appliquer récursivement
            return array_map([$this, 'ensureUtf8'], $value);
        }
        return $value;
    }

    public function OrangePhase1(Request $request)
    {
        $curl = curl_init();

        $operator = DB::table('operator')
            ->where('code', $request->operator)
            ->get();

        if ($operator->isEmpty()) {
            return response()->json([
                "response" => "error",
                "message" => "Operateur inexistant"
            ]);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-s1.orange.cm/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: Basic N3VjTHdfcDBhaExnTkJ1Tko0bUV3bmlvQWVVYTpJRlo1TjhzV1ZFMHowY2Z3UDZHb0hSN01RUjRh",
            ),
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=client_credentials",
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $rep = json_decode($response, true);

        if (isset($rep['access_token'])) {
            $phase2function = new PaymentController();
            $phase2 = $phase2function->OrangePhase2($rep['access_token']);

            if ($phase2 == "0") {
                return response()->json([
                    "response" => "error",
                    "message" => "Aucune réponse de l'opérateur !!! Veuillez recommencer le paiement"
                ]);
            }

            $data = Deposit::create([
                "access_token" => $this->ensureUtf8($rep['access_token']),
                "scope" => $this->ensureUtf8($rep['scope']),
                "expires_in" => $this->ensureUtf8($rep['expires_in']),
                "id_operator" => $operator[0]->id,
                "paytoken" => $this->ensureUtf8($phase2),
                "id_agent" => $request->id_agent,
                "amount" => $this->ensureUtf8($request->amount),
                "num_transaction" => $this->ensureUtf8($request->number),
            ]);

            $phase3Response = $phase2function->OrangePhase3($data->id,"deposit");

            // Nettoyer la réponse de Phase 3 pour s'assurer qu'elle est en UTF-8
            $phase3Response = $this->ensureUtf8($phase3Response);

            return response()->json([
                "response" => $phase3Response['response'],
                "message" => $phase3Response['message'],
                "data"=> $data
            ]);
        }

        return response()->json([
            "response" => "error",
            "message" => "Échec de l'obtention du jeton d'accès"
        ]);
    }






         public function OrangePhaseUser(Request $request)
    {
        $curl = curl_init();

        $operator = DB::table('operator')
            ->where('code', $request->operator)
            ->get();

        if ($operator->isEmpty()) {
            return response()->json([
                "response" => "error",
                "message" => "Operateur inexistant"
            ]);
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-s1.orange.cm/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: Basic N3VjTHdfcDBhaExnTkJ1Tko0bUV3bmlvQWVVYTpJRlo1TjhzV1ZFMHowY2Z3UDZHb0hSN01RUjRh",
            ),
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=client_credentials",
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $rep = json_decode($response, true);

        if (isset($rep['access_token'])) {
            $phase2function = new PaymentController();
            $phase2 = $phase2function->OrangePhase2($rep['access_token']);

            if ($phase2 == "0") {
                return response()->json([
                    "response" => "error",
                    "message" => "Aucune réponse de l'opérateur !!! Veuillez recommencer le paiement"
                ]);
            }

            $data = Payment::create([
                "access_token" => $this->ensureUtf8($rep['access_token']),
                "scope" => $this->ensureUtf8($rep['scope']),
                "expires_in" => $this->ensureUtf8($rep['expires_in']),
                "id_operator" => $operator[0]->id,
                "paytoken" => $this->ensureUtf8($phase2),
                "id_user" => $request->id_user,
                "amount" => $this->ensureUtf8($request->amount),
                "num_transaction" => $this->ensureUtf8($request->number),
                 "id_order_details" => $this->ensureUtf8($request->id_order),
            ]);

            $phase3Response = $phase2function->OrangePhase3($data->id,"paymentUser");

            // Nettoyer la réponse de Phase 3 pour s'assurer qu'elle est en UTF-8
            $phase3Response = $this->ensureUtf8($phase3Response);

            return response()->json([
                "response" => $phase3Response['response'],
                "message" => $phase3Response['message'],
                "data"=> $data
            ]);
        }

    return response()->json([
    "response" => "error",
    "message" => "Échec de l'obtention du jeton d'accès"
]);







    }




public function testfunction()

{
    $function  = new Fonction();
    
   return $function->sendSms("votre code de confirmation est 2125", "657316683");
}

















    public function OrangePhase2($rep)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-s1.orange.cm/omcoreapis/1.0.2/mp/init',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                'X-AUTH-TOKEN: WU5PVEVIRUFEOllOT1RFSEVBRDIwMjA=',
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: Bearer " . $rep,
            ),
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "",
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $rep = json_decode($response, true);

        if (isset($rep['data']['payToken'])) {
            return $rep['data']['payToken'];
        } else {
            return "0";
        }
    }

    public function OrangePhase3($id,$type)
    {
        $curl = curl_init();
            
            
            if($type == "deposit")
            {
                 $data = DB::table('deposits')
            ->where('id', $id)
            ->get();
                
            }
        if($type == "paymentUser")
            {
            $data = DB::table('payments')
            ->where('id', $id)
            ->get();
                
            }



        if ($data->isEmpty()) {
            return [
                "response" => "error",
                "message" => "Transaction manquante"
            ];
        }

        if ($data[0]->status == "Success") {
            return [
                "response" => "error",
                "message" => "Transaction déjà valide"
            ];
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-s1.orange.cm/omcoreapis/1.0.2/mp/pay',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                'X-AUTH-TOKEN: WU5PVEVIRUFEMjpAWU5vVGVIRUBEMlBST0RBUEk=',
                "Authorization: Bearer " . $data[0]->access_token,
                "Content-Type: application/json"
            ),
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode([
                "notifUrl" => "https://www.y-note.cm/notification",
                "channelUserMsisdn" => "655428869",
                "amount" => $this->ensureUtf8($data[0]->amount),
                "subscriberMsisdn" => $this->ensureUtf8($data[0]->num_transaction),
                "pin" => "3158",
                "orderId" => "POULETAFC",
                "description" => "Depot",
                "payToken" => $this->ensureUtf8($data[0]->paytoken)
            ])
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $rep = json_decode($response, true);
        
     
        if (isset($rep['message'])) {
            if ($rep['message'] == "Merchant payment successfully initiated" || 
                $rep['message'] == "A transaction associated with the payToken " . $data[0]->paytoken . " has already been initiated") {
                return [
                    "response" => 200,
                    "message" => "Paiement en cours de traitement !!! Veuillez valider le paiement après réception du SMS pour compléter la commande ou saisissez #150*50#"
                ];
            }
            return [
                "response" => 404,
                "message" => "Une erreur est survenueeeee !!! Veuillez recommencer"
            ];
        }

        return [
            "response" => 404,
            "message" =>"Une erreur est survenueeeee !!! Veuillez recommencer"
        ];
    }
    
    
    
    
    
    
        public function verifiedOrangePaymentStatus(Request $request)
        {
            $paymentToken ='';
          
            
            if($request->type == 'deposit')
            {
                $deposit = Deposit::where('id',$request->id)->first();
                
                $paymentToken = $deposit->paytoken;
                
         
            
            
            
        $statutMod = new PaymentController();
        $accessToken = $statutMod->OrangeGetToken();
       
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api-s1.orange.cm/omcoreapis/1.0.2/mp/paymentstatus/'.$paymentToken,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => 
        array(
                
            'X-AUTH-TOKEN: WU5PVEVIRUFEMjpAWU5vVGVIRUBEMlBST0RBUEk=',
            "Authorization: Bearer ".$accessToken,
            'Content-Type: application/json'
        ),
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
       
    
       
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $rep = json_decode($response, true);
    
  
    
    
    if(isset($rep['message']))
    {
        if($rep['data']['status'] =="SUCCESSFULL" )
        {

 $deposit = Deposit::where('id',$request->id)->update([
     
     
     'status'=>'Success'
     
     
     ]);
     
     
                 return response()->json([
                        'response'=> 200,
                        'message'=>"Transaction validee avec success"
                        
                    ]);



        }
        
        return response()->json([
                        'response'=> 400,
                        'message'=>"Echec de paiement"
                        
                    ]);
        
        
    }

            
            
             return response()->json([
                        'response'=> 400,
                        'message'=>"Echec de paiement"
                        
                    ]);  
            
            
            
            }
            
             if($request->type == 'userpaiement')
            {
                $deposit = Payment::where('id',$request->id)->first();
                
                $paymentToken = $deposit->paytoken;
                
         
            
            
            
        $statutMod = new PaymentController();
        $accessToken = $statutMod->OrangeGetToken();
       
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api-s1.orange.cm/omcoreapis/1.0.2/mp/paymentstatus/'.$paymentToken,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => 
        array(
                
            'X-AUTH-TOKEN: WU5PVEVIRUFEMjpAWU5vVGVIRUBEMlBST0RBUEk=',
            "Authorization: Bearer ".$accessToken,
            'Content-Type: application/json'
        ),
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
       
    
       
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $rep = json_decode($response, true);
    
  
    
    
    if(isset($rep['message']))
    {
        if($rep['data']['status'] =="SUCCESSFULL" )
        {

      Payment::where('id',$request->id)->update([
     'status'=>'Success'
     ]);
     
     
     $order = order_detail::where('id',$deposit->id_order_details)->update([
     'status_paiement'=>'Success'
     ]);
     
                 return response()->json([
                        'response'=> 200,
                        'message'=>"Transaction validee avec success"
                        
                    ]);



        }
        
        return response()->json([
                        'response'=> 400,
                        'message'=>"Echec de paiement"
                        
                    ]);
        
        
    }

            
            
             return response()->json([
                        'response'=> 400,
                        'message'=>"Echec de paiement"
                        
                    ]);  
            
            
            
            }
            
            
            
             return response()->json([
                        'response'=> 400,
                        'message'=>"Echec de paiement"
                        
                    ]);  
            
        }
    
    
    
    
    
     public function OrangeGetToken()
    {
        $curl = curl_init();

     
     
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api-s1.orange.cm/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => 
            array(
                
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: Basic N3VjTHdfcDBhaExnTkJ1Tko0bUV3bmlvQWVVYTpJRlo1TjhzV1ZFMHowY2Z3UDZHb0hSN01RUjRh",
              
            ),
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "grant_type=client_credentials",
           
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        $rep = json_decode($response, true);
        if(isset($rep['access_token']))
        {
             return $rep['access_token'];
             
        }
        else
        {
            return 0;
        }
      
    }

    
    
    
    
    
    
    
    
    
    
    
    
    
}