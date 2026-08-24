<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Clando;
use App\Models\order_detail;
use App\Models\CreditAgent;
use Illuminate\Support\Facades\DB;
use App\Fonction\Fonction;
class FinanceController extends Controller
{
    /**
     * Paiements mobile money (Orange Money) confirmés pour les commandes et
     * courses de cet agent — sert à l'agent de confirmation visuelle que le
     * client a bien payé, distinct de "Dépôt effectué" (ses propres remises
     * d'espèces à l'entreprise, déjà retourné par getfinanceAgent).
     *
     * payments.id_agent n'est jamais renseigné par OrangePhaseUser : on
     * retrouve l'agent en remontant par la commande/course payée
     * (order_details.id_agent ou clando.id_agent selon payments.order_type).
     */
    public function getPaymentsAgent(Request $request)
    {
        $idAgent = $request->id_user;

        $viaCommandes = DB::table('payments')
            ->join('order_details', 'payments.id_order_details', '=', 'order_details.id')
            ->where('payments.status', 'Success')
            ->where(function ($q) {
                $q->whereNull('payments.order_type')
                  ->orWhere('payments.order_type', 'order_details');
            })
            ->where('order_details.id_agent', $idAgent)
            ->select('payments.id', 'payments.amount', 'payments.created_at', 'order_details.ref');

        $viaClando = DB::table('payments')
            ->join('clando', 'payments.id_order_details', '=', 'clando.id')
            ->where('payments.status', 'Success')
            ->where('payments.order_type', 'clando')
            ->where('clando.id_agent', $idAgent)
            ->select('payments.id', 'payments.amount', 'payments.created_at', 'clando.ref');

        $paiements = $viaCommandes->unionAll($viaClando)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'response' => 200,
            'count' => $paiements->count(),
            'totalAmount' => $paiements->sum(fn ($p) => (float) $p->amount),
            'paiements' => $paiements,
        ]);
    }

    public function getfinanceAgent(Request $request)
    {
        
        
         $solde =(new Fonction())->solde($request->id_user);
         
         
         
        $totalearnClando = $solde['totalearnclando'];
        $totalearnCommand =  $solde['totalearncommand'];
        $totalcredit =  $solde['totalcredit'];
        $totaldeposit =  $solde['totaldeposit'];
        $soldeAgent =  $solde['solde'];
        
        
        $historiqueClando = DB::table('clando')->where('id_agent',$request->id_user)->where('status','Success')->get();
        $historiqueCommand = DB::table('order_details')->where('id_agent',$request->id_user)->where('status','Success')->get();
        $historiqueCredit = DB::table('credit_agents')->where('id_agent',$request->id_user)->get();
        $historiquedeposit = DB::table('deposits')->where('id_agent',$request->id_user)->where('status','Success')->get();
        
       
        
        return response()->json(['response' => 200,
        'totalearn'=> $totalearnClando +  $totalearnCommand,
        'totalcredit'=> $totalcredit, 
        'totaldeposit'=> $totaldeposit,
        "solde" =>  $solde,
        "historiqueClando"=> $historiqueClando,
        "historiqueCommand"=> $historiqueCommand,
         "historiquecredit"=> $historiqueCredit,
         "historiquedeposit"=> $historiquedeposit,
        
        ]);
    }
}
