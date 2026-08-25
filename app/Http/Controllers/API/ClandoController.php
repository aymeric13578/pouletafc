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
              return response()->json([
                  'response' => 404,
                  'message' => "Veuillez valider votre compte (code reçu par SMS ou e-mail) avant de commander une course.",
              ]);
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
                 'commission_agent'=>$commission_agent,
                 // 'pending' par défaut — même convention que
                 // order_details/CoursierController::statutPaiement() pour
                 // « pas encore payé » : le paiement Orange Money (voir
                 // PaymentController::verifiedOrangePaymentStatus) le
                 // repasse à 'Success' une fois la transaction confirmée.
                 // Une course payée en espèces à l'agent reste 'pending'
                 // ici, ce champ ne concerne que l'aller-retour OM.
                 'status_paiement'=>'pending'

            ]);
            
          if($order) {
              if ($order->status === 'want') {
                  \App\Support\OffresDeCourse::calculerVagues($order);
              }

              return response()->json(['response' => 200, 'data'=>  $order ]);
          }
        else return response()->json(['response' => 404, 'message' => "Impossible de créer la course. Veuillez réessayer."]);


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

    /**
     * Historique complet des courses Clando d'un agent, tous statuts
     * confondus — équivalent de getSellerOrder (order_details) mais pour
     * clando. getClandoAgent ci-dessus exclut délibérément 'Success' (il
     * sert aux courses actives, pas à un historique) ; getfinanceAgent ne
     * garde que 'Success' (il sert au calcul des gains). Aucun des deux ne
     * convient à un écran d'historique, qui doit montrer aussi les courses
     * annulées/refusées comme le fait déjà getSellerOrder côté commandes.
     */
    public function getSellerClando(Request $request)
    {
        // TEMPORAIRE (diagnostic) — 500 sans corps observé en production sur
        // ce nouvel endpoint, cause inconnue faute d'accès aux logs serveur.
        try {
            // Colonnes explicitement listées sur "users" : cette route v1.0 n'a
            // aucune authentification (CLAUDE.md règle 8), et un ->with('users')
            // sans restriction renverrait confirmation_code/recoveryPass_code en
            // clair — l'app agent ne lit que le nom du client.
            $clando = Clando::where('id_agent', $request->id_agent)
                ->with(['users' => fn ($q) => $q->select('id', 'name', 'last_name')])
                ->orderByDesc('id')
                ->get();

            if ($clando) return response()->json(['response' => 200, 'data' => $clando]);
            else return response()->json(['response' => 404]);
        } catch (\Throwable $e) {
            return response()->json([
                'response' => 500,
                'debug_message' => $e->getMessage(),
                'debug_file' => $e->getFile() . ':' . $e->getLine(),
            ]);
        }
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
            
            
            
            
            
            
            
            
            
            
            
            
            
        
        
                    /*
          | Une course annulée entre-temps ne se prend plus.
          |
          | La fenêtre de l'agent s'ouvre au moment où la course sonne, et rien
          | ne la refermait ensuite : si le comptoir annulait dans l'intervalle,
          | l'agent appuyait sur « Prendre » sans rien savoir et partait pour une
          | course qui n'existait plus. Le contrôle porte sur le statut, et non
          | sur l'agent : « déjà prise » et « annulée » ne se disent pas pareil à
          | celui qui attend.
          */
          if(!isset($order))
          {
              return response()->json(['response' => 404, 'message' => "Cette course est introuvable.", 'retour' => 1, 'indisponible' => true]);
          }

          if($order->status === \App\Support\AnnulationDeCommande::STATUT)
          {
              $motif = $order->cancel_reason ?? null;

              return response()->json([
                  'response' => 404,
                  'message' => $motif ? "Course annulée : " . $motif : "Cette course a été annulée.",
                  'retour' => 1,
                  'indisponible' => true,
              ]);
          }

          if($order->status !== 'want' && $order->status !== 'pending')
          {
              return response()->json(['response' => 404, 'message' => "Cette course n'est plus à prendre.", 'retour' => 1, 'indisponible' => true]);
          }

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
     * « want » signifie « à enlever » : c'est le statut que pose le bouton
     * « Colis prêt » du mur, et c'est bien lui qu'il faut guetter. La sonnerie
     * part donc après le geste du comptoir, jamais à la prise de commande — une
     * commande fraîche reste en « pending » et ne réveille personne.
     *
     * Les courses clando partagent ce statut pour la même raison : une demande
     * de course est prête à être prise dès qu'elle est passée.
     *
     * Ajout : la borne du jour. Sans elle, une ligne oubliée dans ce statut
     * sonnait sur tous les téléphones à chaque redémarrage de l'application,
     * indéfiniment — sa déduplication ne tient qu'en mémoire et repart à zéro à
     * chaque lancement.
     */
    public function getActiveCommand(Request $request)
    {
         $debutDuJour = now()->setTimezone('Africa/Douala')->startOfDay();
         $bornes = [$debutDuJour->copy()->utc(), $debutDuJour->copy()->endOfDay()->utc()];

         $order = Clando::where('status',"want")
             ->where('id_agent',null)
             ->whereBetween('created_at', $bornes)
             ->first();

         $order_detail = order_detail::where('status',"want")
             ->where('id_agent',null)
             ->whereBetween('created_at', $bornes)
             ->orderBy('id','desc')
             ->first();
         
         
         
         
         
         $agent = Agent::where('id_user',$request->id_user)->first();

          /*
           | Le test du compte agent commençait à « $agent->freeStatus », sans
           | vérifier que la recherche avait trouvé quelque chose. Un identifiant
           | qui n'est pas un agent renvoie null, et la lecture de propriété sur
           | null faisait répondre 500 — une panne serveur là où la réponse
           | attendue est simplement « rien pour vous ».
           |
           | in_activity se lit maintenant sur users, pas sur agents : la colonne
           | agents.in_activity ne vaut 1 que par defaut de creation et n'est
           | jamais remise a jour ensuite (users.in_activity, elle, est ecrite en
           | continu par takeDay/takeDayDesactive) - ce filtre par statut de
           | service n'a donc jamais reellement fonctionne jusqu'ici.
           */
          $enService = User::where('id', $request->id_user)->value('in_activity');

          if(!$agent || $agent->freeStatus == 0  || (int) $enService === 0)

             {
                 return response()->json(['response' => 400 ]);
             }

         /*
          | Vague d'offre : un agent absent de course_offer_waves pour cette
          | course precise (hors du pool de candidats, ou vague pas encore
          | ouverte) ne la voit pas encore - c'est ce qui fait que les mieux
          | classes par DistributionScore voient l'offre en premier. Applique
          | independamment a $order et $order_detail : l'un peut etre visible
          | pendant que l'autre ne l'est pas encore.
          */
         if ($order && ! $this->visiblePourAgent($request->id_user, 'id_clando', $order->id)) {
             $order = null;
         }

         if ($order_detail && ! $this->visiblePourAgent($request->id_user, 'id_order', $order_detail->id)) {
             $order_detail = null;
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

    /** L'agent a-t-il une vague ouverte (visible_at <= maintenant) pour cette course ? */
    private function visiblePourAgent(int $idUser, string $colonne, int $idCible): bool
    {
        return DB::table('course_offer_waves')
            ->where('id_user', $idUser)
            ->where($colonne, $idCible)
            ->where('visible_at', '<=', now())
            ->exists();
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
             /*
              | Jusqu'ici cette route n'écrivait que la ligne d'audit
              | ci-dessus : le statut de la course restait "want", donc
              | parfaitement prenable par n'importe quel agent malgré
              | l'annulation du client. On applique la même annulation que
              | le mur des commandes/la carte clando, via la classe déjà
              | prévue pour ça — takeClandoCommand et les listes d'agents
              | (getClandoWithoutAgent, getActiveCommand) savent déjà exclure
              | ce statut, elles n'attendaient que cette écriture.
              */
             $clando = Clando::find($request->id_clando);

             if ($clando && \App\Support\AnnulationDeCommande::annulableParLeClient($clando)) {
                 \App\Support\AnnulationDeCommande::appliquer($clando, 'Annulé par le client', 'client');
             }

             return response()->json(['response' => 200]);
         }
          return response()->json(['response' => 400 ]);

    }
    
      public function declinCommandAfterTake(Request $request)
        {
        /*
         | L'agent rend la commande : c'est une annulation, et le motif est ce
         | qui la rend exploitable. « declin » sans un mot ne dit pas si le
         | client était absent, l'adresse fausse, ou l'agent en panne — trois
         | situations qui n'appellent pas la même suite.
         |
         | Le statut reste « declin » : c'est celui que lisent l'historique et
         | les écrans existants, et le changer casserait leur lecture.
         */
        $ligne = Clando::where('ref', $request->ref)->first();

        if (! $ligne) {
            return response()->json(['response' => 400, 'message' => 'Course introuvable.']);
        }

        $motif = (string) $request->input('reason', $request->input('motif'));

        $champs = ['status' => 'declin'];

        foreach ([
            'cancel_reason' => \App\Support\AnnulationDeCommande::motifValide($motif)
                ? \App\Support\AnnulationDeCommande::nettoyerLeMotif($motif)
                : null,
            'cancelled_at' => now(),
            'cancelled_by' => 'agent',
        ] as $colonne => $valeur) {
            if ($valeur !== null && \App\Support\ColonnesDisponibles::existe($ligne->getTable(), $colonne)) {
                $champs[$colonne] = $valeur;
            }
        }

        $order = $ligne->update($champs);

        $freeStatusAgent = Agent::where('id_user', $request->id_user)->update([
            'freeStatus' => 1,
        ]);

        if ($order) {
            return response()->json(['response' => 200]);
        }

        return response()->json(['response' => 400]);
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
         $clando = Clando::where('ref',$request->ref)->first();

         if (! $clando) {
             return response()->json(['response' => 400, 'message' => 'Course introuvable']);
         }

         /*
          | Le client a pu annuler pendant que l'agent était déjà en route :
          | ce bouton écrivait "Success" sans jamais le vérifier, ce qui
          | ressuscitait silencieusement une course que le client croyait
          | annulée (les traces d'annulation restaient sur la ligne, mais le
          | statut repassait à "Success" par-dessus). L'agent est quand même
          | libéré : il n'a plus rien à livrer sur cette course.
          */
         if (\App\Support\AnnulationDeCommande::estAnnulee($clando)) {
             Agent::where('id_user', $request->id_user)->update(['freeStatus' => 1]);

             return response()->json([
                 'response' => 409,
                 'message' => 'Cette course a été annulée par le client entre-temps.',
             ]);
         }

         $order = $clando->update([

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
