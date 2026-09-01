<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Clando;
use App\Models\Deposit;
use App\Models\order_detail;
use App\Models\Payment;
use App\Fonction\Fonction;
use DB;

class PaymentController extends Controller
{
    // Fonction utilitaire pour nettoyer les cha�nes et forcer l'encodage UTF-8
    private function ensureUtf8($value)
    {
        if (is_string($value)) {
            // D�tecter l'encodage et convertir en UTF-8 si n�cessaire
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'auto');
            }
            return $value;
        } elseif (is_array($value)) {
            // Si c'est un tableau, appliquer r�cursivement
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
                    "message" => "Aucune r�ponse de l'op�rateur !!! Veuillez recommencer le paiement"
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

            // Nettoyer la r�ponse de Phase 3 pour s'assurer qu'elle est en UTF-8
            $phase3Response = $this->ensureUtf8($phase3Response);

            return response()->json([
                "response" => $phase3Response['response'],
                "message" => $phase3Response['message'],
                "data"=> $data
            ]);
        }

        return response()->json([
            "response" => "error",
            "message" => "�chec de l'obtention du jeton d'acc�s"
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

        /*
         | Le montant réellement débité doit venir de la commande/course
         | elle-même, jamais du téléphone du client : cette route n'a aucune
         | authentification (v1.0), id_order est un entier devinable, et
         | l'ancien code stockait $request->amount tel quel puis marquait la
         | commande entière "payée" sur cette seule confirmation — un appel
         | direct avec amount=1 suffisait à faire passer n'importe quelle
         | commande en status_paiement=Success. On retrouve ici le prix
         | réel, et on refuse la demande s'il est introuvable ou incohérent
         | avec ce que le client prétend payer (au cas où l'app afficherait
         | un montant obsolète, pour que l'échec soit visible côté client
         | plutôt qu'un paiement silencieusement accepté pour le mauvais
         | montant).
         */
        $orderType = $request->input('order_type', 'order_details');
        $commande = $orderType === 'clando'
            ? Clando::find($request->id_order)
            : order_detail::find($request->id_order);

        if (! $commande) {
            return response()->json([
                "response" => "error",
                "message" => "Commande introuvable",
            ]);
        }

        $montantReel = (float) $commande->price;
        $montantDemande = (float) $request->amount;

        // Tolérance d'un franc pour l'arrondi éventuel côté app — pas une
        // marge pour sous-payer délibérément.
        if ($montantReel <= 0 || abs($montantReel - $montantDemande) > 1) {
            return response()->json([
                "response" => "error",
                "message" => "Le montant ne correspond pas au prix de la commande",
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
                    "message" => "Aucune r�ponse de l'op�rateur !!! Veuillez recommencer le paiement"
                ]);
            }

            $data = Payment::create([
                "access_token" => $this->ensureUtf8($rep['access_token']),
                "scope" => $this->ensureUtf8($rep['scope']),
                "expires_in" => $this->ensureUtf8($rep['expires_in']),
                "id_operator" => $operator[0]->id,
                "paytoken" => $this->ensureUtf8($phase2),
                "id_user" => $request->id_user,
                // Le prix réel de la commande (vérifié ci-dessus), jamais
                // celui envoyé par le téléphone.
                "amount" => $montantReel,
                "num_transaction" => $this->ensureUtf8($request->number),
                 "id_order_details" => $this->ensureUtf8($request->id_order),
                 // 'order_details' par défaut : les appelants existants
                 // (commande boutique, course coursier) n'envoient pas ce
                 // paramètre et doivent continuer à cibler order_details
                 // exactement comme avant.
                 "order_type" => $orderType,
            ]);

            $phase3Response = $phase2function->OrangePhase3($data->id,"paymentUser");

            // Nettoyer la r�ponse de Phase 3 pour s'assurer qu'elle est en UTF-8
            $phase3Response = $this->ensureUtf8($phase3Response);

            return response()->json([
                "response" => $phase3Response['response'],
                "message" => $phase3Response['message'],
                "data"=> $data
            ]);
        }

    return response()->json([
    "response" => "error",
    "message" => "�chec de l'obtention du jeton d'acc�s"
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
                "message" => "Transaction d�j� valide"
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
                    "message" => "Paiement en cours de traitement !!! Veuillez valider le paiement apr�s r�ception du SMS pour compl�ter la commande ou saisissez #150*50#"
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

     // Double écriture Phase 1 (App\Support\LivreDeComptes) : le dépôt
     // validé crédite le compte de l'agent au livre. Idempotent par
     // (type, source) — revérifier la même transaction n'écrit rien.
     $ligneDepot = Deposit::find($request->id);
     if ($ligneDepot) {
         app(\App\Support\LivreDeComptes::class)->depot(
             (int) $ligneDepot->id_agent,
             (float) $ligneDepot->amount,
             (int) $ligneDepot->id,
         );
     }

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

     // order_type distingue la table réellement visée par
     // id_order_details : 'clando' pour une course moto, sinon
     // order_details comme avant (commande boutique, course coursier).
     if ($deposit->order_type === 'clando') {
         Clando::where('id', $deposit->id_order_details)->update([
             'status_paiement' => 'Success',
         ]);
     } else {
         order_detail::where('id', $deposit->id_order_details)->update([
             'status_paiement' => 'Success',
         ]);

         /*
          | Phase 2 (livre de comptes) : une commande payée Orange Money
          | crédite chaque boutique concernée de SES lignes de panier, net
          | de majoration — la part de majoration, figée à la vente
          | (cart_items.majoration_unitaire), revient à la société.
          | Idempotent par (boutique, commande) : revérifier le même
          | paiement n'écrit rien deux fois.
          */
         \App\Support\VentilationVenteOm::crediter((int) $deposit->id_order_details);
     }

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