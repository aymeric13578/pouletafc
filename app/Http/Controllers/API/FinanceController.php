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

        $existante = WithdrawalRequest::where('acteur_type', 'agent')->where('id_agent', $idAgent)
            ->where('status', 'pending')
            ->first();

        // Vérifié avant la validation du montant : tant qu'une demande est en
        // attente, le montant envoyé n'a de toute façon aucun effet — refuser
        // sur un montant invalide induirait l'agent en erreur alors que sa
        // vraie situation est "vous avez déjà une demande en cours".
        if ($existante) {
            return response()->json(['response' => 200, 'data' => $existante, 'already_pending' => true]);
        }

        $solde = (new Fonction())->solde($idAgent);

        /*
         | Le montant est validé contre le solde recalculé ici, jamais contre
         | une valeur envoyée par l'app : celle-ci n'affiche qu'une copie
         | (potentiellement périmée, et modifiable par un client trafiqué).
         |
         | Le numéro n'est obligatoire que pour Orange Money, et vaut 9
         | chiffres — la longueur d'un numéro camerounais sans indicatif,
         | même règle que la saisie côté app (coursier_request_screen.dart).
         | Il n'est pas forcément celui de l'agent : le dépôt peut viser un
         | autre numéro, c'est pour ça qu'on le demande au lieu de le déduire.
         */
        $valide = $request->validate([
            'montant' => ['required', 'numeric', 'gt:0', 'max:' . $solde['solde']],
            'mode' => ['required', 'in:cash,om'],
            'numero' => ['required_if:mode,om', 'nullable', 'digits:9'],
        ], [
            'montant.max' => 'Le montant demandé dépasse votre solde disponible.',
            'montant.gt' => 'Le montant doit être supérieur à zéro.',
            'numero.required_if' => 'Le numéro Orange Money est obligatoire.',
            'numero.digits' => 'Le numéro doit contenir 9 chiffres.',
        ]);

        $demande = WithdrawalRequest::create([
            'id_agent' => $idAgent,
            'amount' => $valide['montant'],
            'mode' => $valide['mode'],
            'phone' => $valide['mode'] === 'om' ? $valide['numero'] : null,
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
        $demande = WithdrawalRequest::where('acteur_type', 'agent')->where('id_agent', $request->id_user)
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
        $retraitEnAttente = WithdrawalRequest::where('acteur_type', 'agent')
            ->where('id_agent', $request->id_user)
            ->where('status', 'pending')
            ->first();

        // Argent effectivement encaissé par l'agent (espèces ou Orange
        // Money confirmé) au moment de "Terminer" une course — voir
        // ClandoController::terminatedCourse. N'était renvoyé nulle part
        // jusqu'ici : aucun écran ne pouvait afficher ce chiffre, donc rien
        // ne pouvait jamais signaler qu'il était resté à zéro par erreur.
        $depotRecu = (float) (\App\Models\Agent::where('id_user', $request->id_user)->value('deposit_recu') ?? 0);

        // Les derniers mouvements du livre de comptes — la même liste que sur
        // la page finance du marchand (getMyShopFinance) : c'est ce que le
        // solde ci-dessus additionne réellement depuis la bascule, donc la
        // seule vue qui explique le chiffre affiché (commissions débitées,
        // gains OM crédités, primes, dépôts, retraits validés).
        $mouvements = \App\Models\MouvementFinancier::where('acteur_type', \App\Models\MouvementFinancier::ACTEUR_AGENT)
            ->where('acteur_id', $request->id_user)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['sens', 'type', 'montant', 'libelle', 'created_at']);

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
         "mouvements" => $mouvements,

        ]);
    }
}
