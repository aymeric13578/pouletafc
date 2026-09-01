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
    
    /*
     | Bascule du 2026-09-01 (Phase 2, décision explicite du propriétaire) :
     | « solde » est désormais lu au livre de comptes
     | (App\Support\LivreDeComptes / mouvements_financiers) — nouvelles
     | règles cash/OM, primes, report à nouveau compris. App agent, tableau
     | de bord, barrière de prise de commande et validation de retrait
     | basculent donc tous en même temps, par ce seul point.
     |
     | Les autres clés (totalearn*, totaldeposit…) restent calculées comme
     | avant : plusieurs écrans les affichent comme statistiques, elles ne
     | prétendent pas être un solde.
     */
    public function solde($id)
    {
        $ancien = $this->soldeAncienneFormule($id);

        $ancien['solde'] = app(\App\Support\LivreDeComptes::class)
            ->solde(\App\Models\MouvementFinancier::ACTEUR_AGENT, (int) $id);

        return $ancien;
    }

    /**
     * L'ancienne formule (dépôts + crédits − prix pleins − retraits),
     * conservée pour la commande finances:reconcilier — elle sert de point
     * de comparaison au livre, plus jamais d'affichage.
     */
    public function soldeAncienneFormule($id)
    {


        $totalearnClando = DB::table('clando')->where('id_agent',$id)->where('status','Success')->select(DB::raw('COALESCE(sum(price),0) as total'))->get();
        $totalearnCommand = DB::table('order_details')->where('id_agent',$id)->where('status','Success')->select(DB::raw('COALESCE(sum(price),0) as total'))->get();
        $totalcredit = DB::table('credit_agents')->where('id_agent',$id)->select(DB::raw('COALESCE(sum(amount),0) as total'))->get();
        $totaldeposit = DB::table('deposits')->where('id_agent',$id)->where('status','Success')->select(DB::raw('COALESCE(sum(amount),0) as total'))->get();
        // Retraits déjà validés au tableau de bord (voir
        // FinanceController::requestWithdrawal / resources/views/pages/
        // dashboard/retraits.blade.php) : sans cette ligne, valider un
        // retrait ne faisait bouger aucun chiffre nulle part — l'agent
        // pouvait redemander aussitôt le même solde et être payé deux fois.
        $totalWithdrawn = DB::table('withdrawal_requests')->where('id_agent',$id)->where('status','validated')->select(DB::raw('COALESCE(sum(amount),0) as total'))->get();

        $solde = $totaldeposit[0]->total +  $totalcredit[0]->total - $totalearnClando[0]->total - $totalearnCommand[0]->total - $totalWithdrawn[0]->total;

         return  $data =
        [
            "solde" => $solde,
            'totalearnclando'=>$totalearnClando[0]->total,
            'totalearncommand'=>$totalearnCommand[0]->total,
            'totalcredit'=>$totalcredit[0]->total,
            'totaldeposit'=> $totaldeposit[0]->total,
            'totalwithdrawn'=> $totalWithdrawn[0]->total,

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
            CURLOPT_URL => config('orange_sms.token_url'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => config('orange_sms.verify_ssl'),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/x-www-form-urlencoded",
                "Authorization: " . config('orange_sms.authorization'),
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
     * Numéro au format attendu par l'API Orange : neuf chiffres, sans indicatif.
     *
     * L'envoi préfixe « +237 » au contact reçu. Un numéro déjà saisi avec son
     * indicatif — le champ de l'application est une simple zone de texte, sans
     * contrôle — produisait « +237+237690… » ou « +237237690… », que l'opérateur
     * ne remet à personne. Six numéros en base sont dans ce cas.
     */
    public function numeroLocal($contact)
    {
        $chiffres = preg_replace('/[^0-9]/', '', (string) $contact);

        // Un numéro camerounais fait neuf chiffres : au-delà, l'indicatif est
        // en tête et on le retire.
        if (strlen($chiffres) > 9 && str_starts_with($chiffres, '237')) {
            $chiffres = substr($chiffres, 3);
        }

        return $chiffres;
    }

    /**
     * Corps de la demande d'envoi.
     *
     * Sans nom d'expéditeur, volontairement. Orange refuse tout nom non
     * enregistré sur le contrat, et en déclarer un ferait échouer entièrement
     * des envois aujourd'hui acceptés. Il n'apporterait de toute façon rien à
     * la remise : l'API accepte à l'identique le remplissage, un numéro réel ou
     * un numéro inventé comme adresse d'émission.
     *
     * @return array<string, mixed>
     */
    public function corpsDeLEnvoi($message, $contact)
    {
        return ["outboundSMSMessageRequest" => [
            /*
             | « tel: » fait partie de l'adresse attendue par Orange, au même
             | titre que l'indicatif. L'omettre produit une adresse qu'il
             | n'associe à aucun abonné.
             */
            "address" => 'tel:' . config('orange_sms.country_code') . $this->numeroLocal($contact),
            "senderAddress" => config('orange_sms.sender_address'),
            "outboundSMSTextMessage" => [
                "message" => (string) $message,
            ],
        ]];
    }

    public function sendSms($message, $contact)
    {
        $curl = curl_init();
        $getToken = $this->getToken();

        // L'adresse d'émission fait partie du chemin, encodée.
        $url = 'https://api.orange.com/smsmessaging/v1/outbound/'
             . rawurlencode(config('orange_sms.sender_address'))
             . '/requests';

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => config('orange_sms.verify_ssl'),
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer " . ($getToken['access_token'] ?? ''),
                "Content-Type: application/json",
            ),
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($this->corpsDeLEnvoi($message, $contact)),
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response, true);
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}





?>