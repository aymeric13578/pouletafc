<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Clando;
use App\Models\order_detail;
use App\Models\CreditAgent;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;
use App\Fonction\Fonction;
class FinanceController extends Controller
{
    /**
     * Bouton "Demander un retrait" (finances, app agent). Ne déclenche rien
     * d'automatique : journalise la demande pour validation manuelle au
     * tableau de bord — voir la migration creer_table_withdrawal_requests.
     *
     * Une seule demande 'pending' à la fois par agent : rappeler ce endpoint
     * pendant qu'une demande est déjà en attente renvoie celle-ci telle
     * quelle plutôt que d'en empiler une deuxième.
     */
    public function requestWithdrawal(Request $request)
    {
        $idAgent = $request->id_user;

        $existante = WithdrawalRequest::where('id_agent', $idAgent)
            ->where('status', 'pending')
            ->first();

        if ($existante) {
            return response()->json(['response' => 200, 'data' => $existante, 'already_pending' => true]);
        }

        $solde = (new Fonction())->solde($idAgent);

        $demande = WithdrawalRequest::create([
            'id_agent' => $idAgent,
            'amount' => $solde['solde'],
            'status' => 'pending',
        ]);

        return response()->json(['response' => 200, 'data' => $demande, 'already_pending' => false]);
    }

    /**
     * Demande de retrait en attente pour cet agent, si elle existe — permet
     * à l'app agent de savoir, dès l'ouverture de l'écran Finances, si le
     * bouton doit s'afficher désactivé sans avoir à mémoriser l'état
     * localement (une désinstallation/réinstallation le perdrait sinon).
     */
    public function getWithdrawalStatus(Request $request)
    {
        $demande = WithdrawalRequest::where('id_agent', $request->id_user)
            ->where('status', 'pending')
            ->first();

        return response()->json(['response' => 200, 'data' => $demande]);
    }
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

        // Demande de retrait en attente, s'il y en a une — voir
        // FinanceController::requestWithdrawal. Renvoyée ici pour que l'app
        // agent connaisse l'état du bouton "Demander un retrait" dès le
        // chargement de l'écran, sans appel séparé.
        $retraitEnAttente = WithdrawalRequest::where('id_agent', $request->id_user)
            ->where('status', 'pending')
            ->first();

        // Argent effectivement encaissé par l'agent (espèces ou Orange
        // Money confirmé) au moment de "Terminer" une course — voir
        // ClandoController::terminatedCourse. N'était renvoyé nulle part
        // jusqu'ici : aucun écran ne pouvait afficher ce chiffre, donc rien
        // ne pouvait jamais signaler qu'il était resté à zéro par erreur.
        $depotRecu = (float) (\App\Models\Agent::where('id_user', $request->id_user)->value('deposit_recu') ?? 0);

        return response()->json(['response' => 200,
        'totalearn'=> $totalearnClando +  $totalearnCommand,
        'totalcredit'=> $totalcredit,
        'totaldeposit'=> $totaldeposit,
        "solde" =>  $solde,
        "historiqueClando"=> $historiqueClando,
        "historiqueCommand"=> $historiqueCommand,
         "historiquecredit"=> $historiqueCredit,
         "historiquedeposit"=> $historiquedeposit,
         "retraitEnAttente" => $retraitEnAttente,
         "depositRecu" => $depotRecu,

        ]);
    }
}
