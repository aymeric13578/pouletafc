<?php
namespace App\Fonction;
use Auth;
use Illuminate\Support\Facades\DB;


class Fonction
{
  
    public  function genUniqueID($lenth)
    {
        $is_unique = 0;
        $randID = (new Fonction())->randID($lenth);
        return $randID;
    }


 public   function randID($length)
    {
        $vowels = 'AEUYaeuy0123456789';
        $consonants = 'BbCcDdFfGgHhJjKkLlMmNnPpQqRrSsTtVvWwXxZz';
        $idnumber = '';
        $alt = time() % 2;
        for ($i = 0; $i < $length; $i++) {
            if ($alt == 1) {
                $idnumber .= $consonants[(rand() % strlen($consonants))];
                $alt = 0;
            } else {
                $idnumber .= $vowels[(rand() % strlen($vowels))];
                $alt = 1;
            }
        }

        return $idnumber;
    }


    public function cutRef($ref)
    {
        
    }
    
    public function solde($id)
    {
        
             
        $totalearnClando = DB::table('clando')->where('id_agent',$id)->where('status','Success')->select(DB::raw('COALESCE(sum(price),0) as total'))->get();
        $totalearnCommand = DB::table('order_details')->where('id_agent',$id)->where('status','Success')->select(DB::raw('COALESCE(sum(price),0) as total'))->get();
        $totalcredit = DB::table('credit_agents')->where('id_agent',$id)->select(DB::raw('COALESCE(sum(amount),0) as total'))->get();
        $totaldeposit = DB::table('deposits')->where('id_agent',$id)->where('status','Success')->select(DB::raw('COALESCE(sum(amount),0) as total'))->get();
        
        $solde = $totaldeposit[0]->total +  $totalcredit[0]->total - $totalearnClando[0]->total - $totalearnCommand[0]->total ;
        
         return  $data = 
        [
            "solde" => $solde,
            'totalearnclando'=>$totalearnClando[0]->total,
            'totalearncommand'=>$totalearnCommand[0]->total,
            'totalcredit'=>$totalcredit[0]->total,
            'totaldeposit'=> $totaldeposit[0]->total,
          
        ];
        
        
    }

    public function userNbrShare($ref)
    {
        $filleul = DB::table('payments')
        ->where('payments.status','Success')
        ->where('payments.type_paiement','!=','Bonus')
        ->join('users','users.id','payments.id_user')
        ->selectRaw('users.ref,COALESCE(sum(share),0) as share')
        ->where('users.ref',$ref)
        ->groupBy('payments.id_user','users.ref')
        ->orderBy('users.id')
        ->get();
if($filleul->isNotEmpty())
{
    return $data = [
        "share"  => $filleul[0]->share,
        "transactions" =>count($filleul)
    ];
}
else
{
    return $data = [
        "share"  => 0,
        "transactions" => 0
    ];
}
        
    }
    
public function getToken()
    {
        
        $curl = curl_init();
         curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.orange.com/oauth/v3/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: Basic UEJhQXVKMWUzemtzc2JvWTJHTWRUM0hIS2FNUEhCcVY6Yk1rVEZIbVJlWnJvakI1bkFjeGNlZTgxbkpRSndxUFIwU0xVWjVBZDRTWmw=",
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
        
        return $rep;

    }
    
    
    
    
    /**
     * Ramène un numéro saisi librement à sa forme locale, sans indicatif.
     *
     * L'adresse envoyée à Orange est construite en préfixant "+237" : un numéro
     * déjà saisi "+237690000000" ou "00237690000000" — ce que les clients tapent
     * couramment dans le champ WhatsApp, libre de tout format — produisait
     * "tel:+237237690000000", que l'opérateur rejette sans que rien ne le
     * signale à l'utilisateur.
     */
    public function numeroLocal($contact)
    {
        $chiffres = preg_replace('/\D+/', '', (string) $contact);

        if ($chiffres === '') {
            return '';
        }

        if (str_starts_with($chiffres, '00237')) {
            $chiffres = substr($chiffres, 5);
        } elseif (str_starts_with($chiffres, '237') && strlen($chiffres) > 9) {
            $chiffres = substr($chiffres, 3);
        }

        // Certains saisissent le numéro à la française, avec un 0 en tête.
        return ltrim($chiffres, '0');
    }

    /**
     * Numéro sur lequel joindre un compte par SMS.
     *
     * L'application client ne collecte qu'un numéro WhatsApp : sans ce repli,
     * les comptes créés depuis cette application n'avaient aucun numéro et ne
     * recevaient ni code d'inscription ni code de récupération.
     */
    public function numeroContact($phone, $whatsapp)
    {
        $numero = $this->numeroLocal($phone);

        return $numero !== '' ? $numero : $this->numeroLocal($whatsapp);
    }

    public function sendSms($message,$contact)
    {
         $contact = $this->numeroLocal($contact);
         $curl = curl_init();
         $function = new Fonction();
         $getToken = $function->getToken();
        
         
           curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2370000000/requests',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false, // Note : D�sactiver la v�rification SSL n'est pas recommand� en production
    CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer " . $getToken['access_token'],
        "Content-Type: application/json"
    ),
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode([
        "outboundSMSMessageRequest" => [
            "address" => "tel:+237".$contact,
            "senderAddress" => "tel:+2370000000",
            "outboundSMSTextMessage" => [
                "message" => "".$message
            ]
        ]
    ])
));

        $response = curl_exec($curl);
        curl_close($curl);

        $rep = json_decode($response, true);
         
         
        return $rep;
        

        
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}





?>