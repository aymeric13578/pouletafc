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
use App\Support\Idempotence;
use DB;
class OrderController extends Controller
{
    /**
     * Crée la commande d'un panier déjà constitué.
     *
     * Extrait de CreateOrder pour que la validation d'un panier venu du
     * téléphone — panier, articles et commande écrits d'une seule transaction —
     * emprunte exactement le même chemin : même référence, même résolution du
     * point de livraison, même commission, mêmes messages au client. Deux
     * manières de créer une commande auraient fini par diverger.
     */
    public function creerDepuisPanier(Request $request, User $client, $panier, float $totalArticles, int $quantite, string $cleUnique = '')
    {
        do {
            $ref = 'REF_' . (new Fonction())->genUniqueID('10');
            $find_ref = \DB::select('select * from order_details where ref="' . $ref . '"');
        } while (!empty($find_ref));

        [$lat, $lon, $origineDuPoint] = app(\App\Support\PointDeLivraison::class)
            ->resoudre($request, $client);

        $fraisDeLivraison = (float) $request->input('delivery_fees', 0);

        /*
         | Commission retenue sur la livraison.
         |
         | Elle porte sur les seuls frais de livraison dès qu'une grille
         | « livraison » existe : l'entreprise se rémunère sur le portage, pas
         | sur la valeur de marchandises vendues par un marchand tiers. Le
         | calcul précédent prenait le total des articles, et avec le taux
         | clando par-dessus le marché. Sans grille, ce calcul est conservé tel
         | quel — voir App\Support\GrilleTarifaire::commissionLivraison().
         */
        $commission_agent = app(\App\Support\GrilleTarifaire::class)
            ->commissionLivraison($fraisDeLivraison, $totalArticles);

        $commande = order_detail::create([
            'id_user' => $client->id,
            'id_cart' => $panier->id,
            'qty' => $quantite,
            'price' => $totalArticles + $fraisDeLivraison,
            'panier_price' => $totalArticles,
            'ref' => $ref,
            'status' => 'pending',
            'latitude' => $lat,
            'longitude' => $lon,
            'delivery_code' => rand(0, 10000),
            /*
             | L'adresse enregistrée, ou le point de retrait désigné.
             |
             | L'application envoie « Coordonnées non disponibles » quand le
             | client n'a pas choisi de lieu — un message d'erreur, pas une
             | adresse — et c'est ce texte qui s'affichait sur le mur du
             | comptoir en guise de destination. Le point de retrait servait
             | pourtant déjà de repli pour les coordonnées : les deux disaient
             | des choses différentes.
             */
            'address' => app(\App\Support\PointDeLivraison::class)
                ->adresse($request->input('delivery_address')),
            'commission_agent' => $commission_agent,
            'delivery_fees' => $fraisDeLivraison,
        ] + ($cleUnique !== '' && app(\App\Support\CommandeSansDoublon::class)->peutRetenirLaCle()
            ? ['cle_unique' => $cleUnique]
            : []));

        /*
         | Tracer les commandes dont l'adresse n'a pas pu être résolue : le point
         | retenu est alors la position du téléphone, ou rien. C'est le signe que
         | l'adresse choisie ne correspond à aucun lieu enregistré.
         */
        if (in_array($origineDuPoint, ['position_client', 'aucune'], true)) {
            \Illuminate\Support\Facades\Log::info('Commande sans adresse résolue', [
                'ref' => $ref,
                'adresse' => $request->input('delivery_address'),
                'origine_du_point' => $origineDuPoint,
            ]);
        }

        app(\App\Support\NotificationClient::class)->prevenir(
            $client,
            'Poulet AFC - commande ' . $ref,
            "Votre commande N° " . $ref . " a bien été reçue. Le service client Poulet AFC vous contactera d'ici quelques instants. Merci de patienter.\nContact service client : 697 526 980"
        );

        return $commande;
    }

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
    
    /*
     | Le panier était fermé ici, avant même de savoir si la commande allait être
     | créée. Conséquence : une seconde tentative trouvait un panier « Success »,
     | l'écran en ouvrait aussitôt un neuf, et ce panier neuf redonnait un
     | identifiant inédit — le contrôle de doublon, qui compare (client, panier),
     | ne pouvait plus rien voir. On le ferme désormais une fois la commande
     | réellement enregistrée.
     */

     /*
      | Doublon reconnu par le contenu, et non par le panier qui le porte.
      |
      | Constaté en production : trois commandes identiques d'un même client,
      | paniers 542, 543 et 544, créés à 36 secondes d'intervalle. Le client
      | n'avait pas commandé trois fois, il avait appuyé trois fois faute de voir
      | sa confirmation arriver.
      */
     $sansDoublon = app(\App\Support\CommandeSansDoublon::class);

     /*
      | La clé transmise par l'application, quand elle en envoie une.
      |
      | Elle est fabriquée une fois par tentative et renvoyée à l'identique à
      | chaque nouvel essai : deux envois sous la même clé sont la même commande.
      | C'est exact, là où la reconnaissance par le contenu ne fait que présumer
      | — et cela rend au client le droit de commander deux fois la même chose
      | dans la même minute.
      |
      | Les versions déjà installées n'en envoient pas : elles restent couvertes
      | par la reconnaissance du contenu, juste en dessous.
      */
     $cleUnique = trim((string) $request->input('cle_unique', $request->input('idempotency_key')));

     $orderverified = $sansDoublon->parCle($cleUnique);

     $orderverified ??= $sansDoublon->dejaPassee(
         (int) $request->user_id,
         $request->cart_id ? (int) $request->cart_id : null,
         (float) ($totalamount + $request->delivery_fees),
         $request->delivery_address
     );

     if ($orderverified) {
         $sansDoublon->signaler($orderverified, $request->cart_id ? (int) $request->cart_id : null);
     }
     
 
    $user = User::where('id',$request->user_id)->first();

    /*
     | Point de livraison.
     |
     | On copiait ici users.latitude/longitude, c'est-à-dire la dernière position
     | connue du téléphone du client — écrite une fois, quasiment jamais remise à
     | jour. L'adresse choisie au panier n'était gardée qu'en texte et ses
     | coordonnées jetées, alors que les lieux enregistrés par les agents les
     | portent. Conséquence mesurée en production : 65 clients sur 74 avaient
     | toujours le même point de livraison, quelle que soit l'adresse choisie.
     |
     | PointDeLivraison essaie, dans l'ordre : les coordonnées transmises par
     | l'application, le lieu désigné par son identifiant, le lieu retrouvé par le
     | nom de l'adresse choisie, puis seulement la position du téléphone.
     */
    [$lat, $lon, $origineDuPoint] = app(\App\Support\PointDeLivraison::class)
        ->resoudre($request, $user);
              
     
     
     
     
      /*
       | Même règle que dans creerDepuisPanier : la commission d'une livraison
       | porte sur les seuls frais de portage dès qu'une grille « livraison »
       | existe, et retombe sinon sur l'ancien calcul. Les deux chemins de
       | création d'une commande doivent facturer pareil — les laisser
       | diverger réintroduirait l'incohérence que cette grille corrige.
       */
      $commission_agent = app(\App\Support\GrilleTarifaire::class)->commissionLivraison(
          (float) $request->input('delivery_fees', 0),
          (float) $request->price
      );
        
     
     
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
                // Même règle que pour le chemin actuel : un message d'erreur
                // n'est pas une adresse, on retient le point de retrait désigné.
                'address'=> app(\App\Support\PointDeLivraison::class)
                    ->adresse($request->delivery_address),
                'commission_agent'=>$commission_agent,
                'delivery_fees'=>$request->delivery_fees,
            ] + ($cleUnique !== '' && $sansDoublon->peutRetenirLaCle()
                ? ['cle_unique' => $cleUnique]
                : []));

            /*
             | Tracer les commandes dont l'adresse n'a pas pu être résolue : le
             | point retenu est alors la position du téléphone, ou rien. C'est le
             | signe que l'adresse choisie ne correspond à aucun lieu enregistré,
             | et c'est cette liste qu'il faudra compléter côté terrain.
             */
            if (in_array($origineDuPoint, ['position_client', 'aucune'], true)) {
                \Illuminate\Support\Facades\Log::info('Commande sans adresse résolue', [
                    'ref' => $ref,
                    'adresse' => $request->delivery_address,
                    'origine_du_point' => $origineDuPoint,
                ]);
            }

            $agent = User::Where('id',$request->user_id)->first();
            
            $content = "Votre commande N° ".$ref.". a été reçu .Le service client poulet AFC vous contacteras d'ici quelques instants .... Merci de patienter.
Contact service client : 697 526 980";
            //$content = "Vous venez de passer une commande N° ".$ref.". La direction POULET AFC vous contactera dans quelques instants .... Merci de patienter";
            $title = "Poulet AFC - votre commande";
            $object = 'Poulet AFC - votre commande';
            Mail::to($agent->email)
                ->send(new NotificationMail($object, $content, $title));

         /*
          | Le client était prévenu par SMS seulement, alors que le courriel ne
          | partait qu'à l'agent. Comme Orange accepte les SMS sans les remettre,
          | celui qui commandait n'était en pratique prévenu de rien.
          */
         app(\App\Support\NotificationClient::class)->prevenir(
             $user,
             'Poulet AFC - commande ' . $ref,
             "Votre commande N° " . $ref . " a bien été reçue. Le service client Poulet AFC vous contactera d'ici quelques instants. Merci de patienter.\nContact service client : 697 526 980"
         );
                
         /*
          | Le montant est réaligné sur le panier réellement attaché.
          |
          | Il était calculé au début de la méthode, à partir des articles
          | présents à cet instant. Si le panier avait été composé en plusieurs
          | fois — le client commande, revient plus tard, ajoute d'autres
          | produits dans le même panier resté ouvert — la commande gardait le
          | montant de la dernière composition pendant que son panier en portait
          | bien plus. Constaté : 2 500 F facturés pour treize articles en valant
          | 30 000.
          |
          | On relit donc le panier après création, et la commande vaut ce qu'il
          | contient. Deux valeurs qui doivent toujours coïncider n'ont pas à
          | être calculées à deux moments différents.
          */
         $reels = CartItem::where('cart_id', $request->cart_id)->where('status', 'Success')->get();
         $panierReel = (float) $reels->sum(fn ($ligne) => (float) $ligne->amount * (int) $ligne->quantity);

         if ($panierReel > 0 && (int) $panierReel !== (int) $totalamount) {
             $order->update([
                 'panier_price' => $panierReel,
                 'price' => $panierReel + (float) $request->delivery_fees,
                 'qty' => (int) $reels->sum('quantity'),
             ]);
         }

         // Le panier est fermé maintenant, la commande étant enregistrée : le
         // prochain ajout de produit en ouvrira un neuf, ce qui est le bon moment.
         Cart::where('id', $request->cart_id)->update(['status' => 'Success']);
     }
     else
     {
         $order = $orderverified;

         /*
          | Le panier de la tentative en double est refermé lui aussi, sinon il
          | resterait ouvert et l'écran du panier continuerait d'afficher des
          | articles déjà commandés.
          */
         if ($request->cart_id && (int) $request->cart_id !== (int) $orderverified->id_cart) {
             Cart::where('id', $request->cart_id)->update(['status' => 'failed']);
         }
     }

        // « doublon » dit à l'application qu'elle n'a pas créé de commande :
        // sans lui, elle affiche une confirmation pour une commande qui existait
        // déjà, et le client croit en avoir deux.
        if($order) return response()->json(['response' => 200, 'data'=>  $order, 'doublon' => $orderverified !== null ]);
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
             /*
              | Colonnes explicitement listées sur 'user' : cette route v1.0
              | n'a aucune authentification (CLAUDE.md règle 8), n'importe qui
              | connaissant une ref peut l'appeler pour n'importe quelle
              | commande. Un with('user') sans restriction renvoyait en clair
              | l'email, la date de naissance, le sexe et la ville du client —
              | aucun de ces champs n'est affiché par les apps qui lisent
              | cette réponse (name/last_name/phone/whatsapp seulement, voir
              | commandOrder.dart côté pouletafc_agent), ils n'ont donc rien à
              | faire ici.
              */
             $order = order_detail::where('ref', $request->ref_order)
                 ->with('carts.cartItems.product')
                 ->with(['user' => fn ($q) => $q->select('id', 'name', 'last_name', 'phone', 'whatsapp')])
                 ->get();
             

             if($order[0]->id_agent != null) 
             {
                 $code = User::where('id', $order[0]->id_agent)->get();
                  $codeLiveur = $code[0]->ref;
                 
             }
             
             
             /*
              | L'écran de course de l'agent lit cette réponse. La colonne
              | « image » ne contient qu'un nom de fichier : sans l'URL complète,
              | l'application ne pouvait pas afficher la photo du colis, et
              | l'agent partait enlever un paquet qu'il n'avait jamais vu.
              */
             $colis = $order->isNotEmpty() ? $order[0] : null;

             /*
              | La photo de profil d'un agent est saisie côté dashboard
              | (agents.blade.php) et stockée sur agents.photo, PAS
              | users.photo (colonne jamais renseignée pour un agent) — lire
              | cette dernière renvoyait toujours null à l'app cliente même
              | quand une photo existait bel et bien en base. Même défaut
              | corrigé côté ClandoController::getclando.
              */
             $photoAgent = $order[0]->id_agent != null
                 ? Agent::where('id_user', $order[0]->id_agent)->value('photo')
                 : null;

             if($order) return response()->json([
                 'response' => 200,
                 'data'=> $order,
                 'code_agent'=>$codeLiveur,
                 'info_agent'=>$code,
                 'image_url' => $colis && $colis->image ? url('upload/' . $colis->image) : null,
                 // agents.photo est déjà stocké avec son préfixe 'upload/'
                 // (voir agents.blade.php, écriture à l'upload) — préfixer
                 // une deuxième fois produisait une URL invalide en
                 // upload/upload/.
                 'agent_photo_url' => $photoAgent
                     ? (str_starts_with($photoAgent, 'http') || str_starts_with($photoAgent, 'upload/')
                         ? url($photoAgent)
                         : url('upload/' . $photoAgent))
                     : null,
             ]);
             else return response()->json(['response' => 404]);
        
         }
    
      public function declinOrderCommand(Request $request)
        {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

         $order = DB::table('declin_command')->insert([
             'id_user' => $utilisateur->id,
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
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        return Idempotence::executer($request->input('idempotency_key'), 'takeOrderCommand', function () use ($request, $utilisateur) {

          $order = order_detail::where('ref',$request->ref)->first();

          $solde =(new Fonction())->solde($utilisateur->id);
         
          if($solde['solde'] < $order->price)
        {
                  return response()->json(['response' => 404,'message' => "Solde insuffisant !! veuillez recharger votre compte", 'retour' => 0, 'solde'=> 'true']); 
        }
      
            
            
            
          $agent = Agent::where('id_user',$utilisateur->id)->first();

        if(!isset($agent))
        {
                  return response()->json(['response' => 404,'message' => "Vous n'êtes pas un agent", 'retour' => 0]);
        }

          $freeStatusAgent = Agent::where('id_user',$utilisateur->id)->update([

            'freeStatus' => 0

            ]);
        
                    /*
          | Une commande annulée entre-temps ne se prend plus.
          |
          | La fenêtre de l'agent s'ouvre au moment où la commande sonne, et rien
          | ne la refermait ensuite : si le comptoir annulait dans l'intervalle,
          | l'agent appuyait sur « Prendre » sans rien savoir et partait livrer
          | une commande qui n'existait plus. Le contrôle porte sur le statut, et
          | non sur l'agent : « déjà prise » et « annulée » ne se disent pas
          | pareil à celui qui attend.
          */
          if(!isset($order))
          {
              return response()->json(['response' => 404, 'message' => "Cette commande est introuvable.", 'retour' => 1, 'indisponible' => true]);
          }

          if($order->status === \App\Support\AnnulationDeCommande::STATUT)
          {
              $motif = $order->cancel_reason ?? null;

              return response()->json([
                  'response' => 404,
                  'message' => $motif ? "Commande annulée : " . $motif : "Cette commande a été annulée.",
                  'retour' => 1,
                  'indisponible' => true,
              ]);
          }

          if($order->status !== 'want' && $order->status !== 'pending')
          {
              return response()->json(['response' => 404, 'message' => "Cette commande n'est plus à prendre.", 'retour' => 1, 'indisponible' => true]);
          }

          if($order->id_agent==null)
          {
             $insert =  $order->update([
                  'id_agent'=> $utilisateur->id,
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


        });
    }

    
    
    
    
    
    
    
    
    
    
    
    /**
     * Commandes en attente proposées aux agents, limitées à la journée en cours.
     *
     * Sans borne de date, l'écran de l'application accumulait toutes les commandes
     * jamais prises depuis l'ouverture du service : une liste sans fin, où les
     * commandes du jour se noyaient parmi des mois d'anciennes.
     */
    public function getAllOrder(Request $request)
    {
        [$debut, $fin] = $this->borneesDuJourCameroun();

        /*
         | « waiting » est inclus au même titre que « pending » : c'est le statut
         | posé par le bouton « Colis prêt » du mur des commandes pour signaler
         | qu'un colis attend d'être enlevé. Le filtrer sur « pending » seul aurait
         | fait disparaître la commande de l'écran des agents au moment précis où
         | elle devient prête.
         */
        // Colonnes explicitement listées sur 'user' : cette route v1.0 n'a
        // aucune authentification (CLAUDE.md règle 8), et un with('user')
        // sans restriction renvoyait en clair confirmation_code (le code de
        // réinitialisation de mot de passe, voir UserController::changePasswordByOtp)
        // ainsi que l'email, la date de naissance, le sexe et la ville du
        // client — même correctif que getOrder() plus haut dans ce fichier.
        $order = order_detail::whereIn('status', ['pending', 'waiting'])
            ->whereBetween('created_at', [$debut, $fin])
            ->with('carts')
            ->with(['user' => fn ($q) => $q->select('id', 'name', 'last_name', 'phone', 'whatsapp')])
            ->orderBy('id', 'desc')
            ->get();

        if ($order) return response()->json(['response' => 200, 'data' => $order]);
        else return response()->json(['response' => 404]);
    }

    /**
     * Début et fin de la journée camerounaise, exprimés en UTC.
     *
     * Les dates sont stockées en UTC alors que le Cameroun est à UTC+1 : borner
     * sur la journée du serveur ferait basculer la liste à une heure du matin
     * heure locale, et masquerait les commandes passées entre minuit et une heure.
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function borneesDuJourCameroun(): array
    {
        $debut = now()->setTimezone('Africa/Douala')->startOfDay();

        return [$debut->copy()->utc(), $debut->copy()->endOfDay()->utc()];
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
    
    
    
    /**
     * Historique des commandes.
     *
     * Un agent ne doit y voir que les commandes qu'il a prises : la méthode
     * renvoyait jusqu'ici l'intégralité des commandes de la plateforme, à tout le
     * monde.
     *
     * Le filtre ne s'applique que si l'application transmet id_user, pour ne pas
     * vider l'écran des clients existants qui appellent ce point d'entrée sans ce
     * paramètre. Côté application, envoyer id_user suffit à activer le
     * cloisonnement.
     */
    public function getAllOrderWithoutCondition(Request $request)
    {
        // Colonnes explicitement listées sur 'user' (CLAUDE.md règle 8/9 —
        // endpoint consommé sans authentification par empolyeeafc en continu) :
        // voir le correctif identique sur getAllOrder() ci-dessus.
        $order = order_detail::where('status', '!=', 'failed')
            ->when($request->id_user, fn ($q) => $q->where('id_agent', $request->id_user))
            ->with('carts')
            ->with(['user' => fn ($q) => $q->select('id', 'name', 'last_name', 'phone', 'whatsapp')])
            ->orderBy('id', 'desc')
            ->get();

        if ($order) return response()->json(['response' => 200, 'data' => $order]);
        else return response()->json(['response' => 404]);
    }
    
    
    
    
    
    
    
    
    
    /**
     * Commandes à prendre, limitées à la journée en cours.
     *
     * Deux défauts se cumulaient dans la requête précédente, et l'accueil de
     * l'agent en portait la conséquence : une liste de dizaines de commandes où
     * les vraies passaient inaperçues.
     *
     * D'abord aucune borne de date : toutes les commandes sans agent depuis
     * l'ouverture du service remontaient, certaines vieilles de huit mois.
     *
     * Ensuite le filtre de statut. « != pending » écartait les commandes non
     * encore préparées, mais laissait passer Success et failed : une commande
     * déjà livrée, dont l'agent n'avait pas été enregistré, s'affichait comme du
     * travail à prendre. On énumère donc les statuts qui signifient réellement
     * « disponible » au lieu d'exclure le seul qui gênait.
     *
     * La journée est celle du Cameroun, convertie en UTC pour interroger la
     * base : sinon la liste basculerait à minuit UTC, soit une heure du matin à
     * Garoua, et les commandes du soir disparaîtraient des écrans encore ouverts.
     */
    public function getAllWithoutSellerOrder(Request $request)
    {
            $debut = now()->setTimezone('Africa/Douala')->startOfDay();

            // Colonnes explicitement listées sur 'user' : même correctif que
            // getAllOrder() ci-dessus (route v1.0 sans authentification).
            $order = order_detail::where('id_agent','=',null)
                ->whereIn('status', ['waiting', 'want', 'take', 'process'])
                ->whereBetween('created_at', [$debut->copy()->utc(), $debut->copy()->endOfDay()->utc()])
                ->with('carts')
                ->with(['user' => fn ($q) => $q->select('id', 'name', 'last_name', 'phone', 'whatsapp')])
                ->orderByDesc('id')
                ->get();

             if($order) return response()->json(['response' => 200, 'data'=> $order]);
             else return response()->json(['response' => 404]);

    }
    
         public function updatePositionAgentOrder(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $order = order_detail::where('ref', $request->ref)->first();

        if (! $order) {
            return response()->json(['response' => 404]);
        }

        if ((int) $order->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette commande."]);
        }

        $update = $order->update([
            'latAgent' => $request->latAgent,
            'lonAgent' => $request->lonAgent,
        ]);

        if ($update) {
            return response()->json(['response' => 200, 'data' => $order]);
        }

        return response()->json(['response' => 404]);
    }
    
    
     public function mapAftertakeOrder(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $commande = order_detail::where('ref', $request->ref)->first();

        if (! $commande) {
            return response()->json(['response' => 400]);
        }

        if ((int) $commande->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette commande."]);
        }

         $order = $commande->update([

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


             // La course la plus récente doit apparaître en tête côté app agent
             // (écran Historique) : sans tri explicite, MySQL ne garantit aucun
             // ordre particulier.
             //
             // Colonnes explicitement listées sur 'user' : même correctif que
             // getAllOrder() ci-dessus (route v1.0 sans authentification).
             $order = order_detail::where('id_agent', $request->id_agent)->with('carts')->with(['user' => fn ($q) => $q->select('id', 'name', 'last_name', 'phone', 'whatsapp')])->orderByDesc('id')->get();


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
            $title = "Poulet AFC - nouvelle commande a prendre";
            $object = 'Poulet AFC - nouvelle commande a prendre';
            Mail::to($agent->email)
                ->send(new NotificationMail($object, $content, $title));
                
                
            $user = User::Where('id',$order->id_user)->first();
            $content = "Un agent vient de prendre la commande  ".$order->ref.".Rendez-vous dans votre historique pour voir les détails" ;
            $title = "Poulet AFC - votre commande";
            $object = 'Poulet AFC - votre commande';
            Mail::to($user->email)
                ->send(new NotificationMail($object, $content, $title));
                
                
                  return response()->json(['response' => 200, 'message'=> "requête effectuée avec success"]);
                 
             }
             
             
            
             else return response()->json(['response' => 404]);
        
    }
    
    
       public function getUserOrder(Request $request)
    {
        
        
            
             /*
              | Colonnes explicitement listées sur "agent" : cette route v1.0 n'a
              | aucune authentification (voir CLAUDE.md règle 8), n'importe qui
              | connaissant un id_user peut lister les commandes de n'importe
              | quel client. Un ->with('agent') sans restriction renvoyait en
              | clair le numéro de carte d'identité, le solde et les chemins
              | des pièces d'identité du livreur assigné — l'app cliente ne lit
              | que agent_name/phone (voir history_screen.dart).
              */
             $order = order_detail::where('id_user', $request->id_user)
                 ->with('carts.cartItems.product')
                 ->with(['agent' => fn ($q) => $q->select('id', 'id_user', 'agent_name', 'phone')])
                 ->orderBy('id','desc')
                 ->get();

             /*
              | Le prix montré au client est la somme de son panier, et non la
              | valeur figée en base.
              |
              | Les deux divergeaient quand le panier avait été composé en
              | plusieurs fois : l'écran d'historique annonçait 2 500 F au-dessus
              | d'un détail de panier en valant 30 000. Le client voyait donc
              | deux prix pour la même commande, sur le même écran.
              */
             $order->each(function ($commande) {
                 $panier = \App\Support\MontantDeCommande::panier($commande);

                 if ($panier === null) {
                     return;
                 }

                 $commande->panier_price = $panier;
                 $commande->price = $panier + (int) $commande->delivery_fees;
                 $commande->qty = \App\Support\MontantDeCommande::quantite($commande);
             });

            
             if($order) return response()->json(['response' => 200, 'data'=> $order]);
        else return response()->json(['response' => 404]);
        
    }
    
    public function insertPosition(Request $request)
    {
        // Endpoint v1.0 sans authentification (comme le reste de cette API) :
        // n'importe qui connaissant un id_user peut appeler cette route. La
        // validation ci-dessous ne vérifie pas la propriété du compte (hors
        // de portée sans revoir l'auth de toute l'API), elle empêche
        // seulement l'écriture de coordonnées absurdes/malformées qui
        // casseraient le calcul d'itinéraire côté agent.
        if (! is_numeric($request->id_user)) {
            return response()->json(['response' => 400, 'message' => 'id_user invalide']);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;
        if (! is_numeric($latitude) || ! is_numeric($longitude)
            || $latitude < -90 || $latitude > 90
            || $longitude < -180 || $longitude > 180) {
            return response()->json(['response' => 400, 'message' => 'Coordonnées invalides']);
        }

        $order = User::where('id', $request->id_user)->update([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        if ($order) return response()->json(['response' => 200]);
        else return response()->json(['response' => 404]);
        
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
          public function declinCommandAfterTakeOrder(Request $request)
        {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        /*
         | L'agent rend la commande : c'est une annulation, et le motif est ce
         | qui la rend exploitable. « declin » sans un mot ne dit pas si le
         | client était absent, l'adresse fausse, ou l'agent en panne — trois
         | situations qui n'appellent pas la même suite.
         |
         | Le statut reste « declin » : c'est celui que lisent l'historique et
         | les écrans existants, et le changer casserait leur lecture.
         */
        $ligne = order_detail::where('ref', $request->ref)->first();

        if (! $ligne) {
            return response()->json(['response' => 400, 'message' => 'Commande introuvable.']);
        }

        if ((int) $ligne->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
            return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette commande."]);
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

        $freeStatusAgent = Agent::where('id_user', $utilisateur->id)->update([
            'freeStatus' => 1,
        ]);

        if ($order) {
            return response()->json(['response' => 200]);
        }

        return response()->json(['response' => 400]);
    }

    /**
     * L'agent signale qu'il est arrivé chez le client — avant même de
     * saisir le code de livraison. Même dispositif que
     * ClandoController::arriveeAgent : une colonne à part
     * (order_details.agent_arrived_at), lue par l'application cliente pour
     * ouvrir d'elle-même l'écran de paiement Orange Money. Couvre à la fois
     * les livraisons boutique et le service coursier, qui partagent cette
     * même table et ce même contrôleur.
     *
     * Idempotent : un second appel (double-tap, retry réseau) ne réécrit
     * pas l'horodatage déjà posé.
     */
    public function arriveeAgentOrder(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        $order = order_detail::where('ref', $request->ref)
            ->where('id_agent', $utilisateur->id)
            ->first();

        if (! $order) {
            return response()->json(['response' => 400, 'message' => 'Commande introuvable']);
        }

        if (! $order->agent_arrived_at) {
            $order->update(['agent_arrived_at' => now()]);
        }

        return response()->json(['response' => 200]);
    }

    /*
     | Signal séparé de l'arrivée : appelé quand l'agent confirme le mode de
     | règlement dans TerminerPaymentSheet, avant même le code de livraison.
     | Sans lui, le client ne savait que le paiement final (LIVRAISON/OM)
     | qu'au moment de terminatedCourseOrder — trop tard pour lui afficher le
     | popup Orange Money au bon moment, et il le voyait dès l'arrivée même
     | quand l'agent choisissait finalement les espèces.
     */
    public function setPaymentMethodOrder(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

        if (! in_array($request->payment_method, ['LIVRAISON', 'OM'], true)) {
            return response()->json(['response' => 400, 'message' => 'Mode de paiement invalide']);
        }

        $order = order_detail::where('ref', $request->ref)
            ->where('id_agent', $utilisateur->id)
            ->first();

        if (! $order) {
            return response()->json(['response' => 400, 'message' => 'Commande introuvable']);
        }

        $order->update(['payment_method' => $request->payment_method]);

        return response()->json(['response' => 200]);
    }

       public function terminatedCourseOrder(Request $request)
    {
        $utilisateur = app(\App\Support\ApiAuthentification::class)->utilisateurOuErreur($request);
        if ($utilisateur instanceof \Illuminate\Http\JsonResponse) {
            return $utilisateur;
        }

         $order = order_detail::where('ref',$request->ref)->first();

         if (! $order) {
             return response()->json(['response' => 400, 'message' => 'Commande introuvable']);
         }

         if ((int) $order->id_agent !== $utilisateur->id && ! app(\App\Support\ApiAuthentification::class)->estStaff($utilisateur)) {
             return response()->json(['response' => 403, 'message' => "Vous n'êtes pas assigné à cette commande."]);
         }

         /*
          | Code de livraison transmis par l'application.
          |
          | Elle envoie « code » ; on ne lisait que « delivery_code », qui valait
          | donc toujours null. La comparaison échouait à chaque tentative et
          | aucun agent ne pouvait terminer une livraison : l'écran répondait
          | « code incorrect !!! 3 essai restants » quel que soit le code saisi.
          |
          | Les deux noms sont acceptés : les téléphones déjà installés envoient
          | « code », et rien ne garantit qu'ils seront tous mis à jour.
          */
         $codeSaisi = $request->input('code', $request->input('delivery_code'));

         /*
          | Comparaison sur des chaînes nettoyées. delivery_code est un varchar
          | rempli tantôt par rand(0,10000), tantôt par l'application : « 0420 »
          | et « 420 » désignent le même code, et une comparaison lâche les
          | aurait aussi confondus avec n'importe quelle chaîne non numérique.
          */
         if ($codeSaisi !== null && trim((string) $order->delivery_code) === trim((string) $codeSaisi))
         {
             /*
              | Le client a pu annuler pendant que l'agent était déjà en route :
              | cette écriture passait outre, ressuscitant silencieusement une
              | livraison que le client croyait annulée (voir le même correctif
              | sur ClandoController::terminatedCourse). L'agent est quand même
              | libéré : il n'a plus rien à livrer sur cette commande.
              */
             if (\App\Support\AnnulationDeCommande::estAnnulee($order)) {
                 Agent::where('id_user', (int) $order->id_agent)->update(['freeStatus' => 1]);

                 return response()->json([
                     'response' => 409,
                     'message' => 'Cette commande a été annulée par le client entre-temps.',
                 ]);
             }

             /*
              | Même dispositif que ClandoController::terminatedCourse :
              | 'LIVRAISON'/'MOMO'/'OM' (déjà le vocabulaire de cette table,
              | voir CoursierController::moyenDePaiement) déclenche le crédit
              | de agents.deposit_recu, une seule fois, sous verrou + transaction
              | pour éviter qu'un double appel simultané ne le double.
              | Absent (ancienne version de l'app agent, ou paramètre non
              | fourni pour cette livraison) : comportement strictement
              | inchangé, aucun crédit.
              */
             $paymentMethod = $request->input('payment_method');
             $paiementReconnu = in_array($paymentMethod, ['LIVRAISON', 'MOMO', 'OM'], true);

             $update = DB::transaction(function () use ($order, $request, $paymentMethod, $paiementReconnu) {
                 $orderVerrouille = order_detail::where('id', $order->id)->lockForUpdate()->first();
                 $dejaTerminee = $orderVerrouille->status === 'Success';

                 $misesAJour = ['status' => 'Success'];
                 if ($paiementReconnu) {
                     $misesAJour['payment_method'] = $paymentMethod;
                     if ($paymentMethod === 'LIVRAISON') {
                         $misesAJour['status_paiement'] = 'Success';
                     }
                 }

                 $misAJour = $orderVerrouille->update($misesAJour);

                 // L'agent crédité est celui déjà vérifié assigné à cette
                 // commande (id_agent, contrôlé plus haut) — jamais un
                 // id_user fourni par le client, qui permettrait à
                 // l'agent assigné de rediriger le crédit/la dette vers
                 // n'importe quel autre compte. > 0 exclut délibérément le
                 // cas staff sur une commande jamais assignée (id_agent
                 // null) : personne à créditer.
                 $idAgent = (int) $orderVerrouille->id_agent;
                 if (! $dejaTerminee && $paiementReconnu && $idAgent > 0) {
                     Agent::where('id_user', $idAgent)->increment('deposit_recu', $orderVerrouille->price);

                     /*
                      | Double écriture Phase 1 (App\Support\LivreDeComptes).
                      | Une demande de coursier suit la règle des courses (sa
                      | part se calcule sur le prix) ; une livraison boutique,
                      | celle des livraisons (sur les frais de livraison) —
                      | règles validées le 2026-09-01.
                      */
                     $livre = app(\App\Support\LivreDeComptes::class);
                     $commission = (float) ($orderVerrouille->commission_agent ?? 0);
                     $ref = (string) $orderVerrouille->ref;

                     if ($orderVerrouille->delivery_type === 'coursier') {
                         $paymentMethod === 'cash'
                             ? $livre->courseCash($idAgent, $commission, 'order', $orderVerrouille->id, $ref)
                             : $livre->courseOm($idAgent, (float) $orderVerrouille->price, $commission, 'order', $orderVerrouille->id, $ref);
                     } else {
                         $frais = (float) ($orderVerrouille->delivery_fees ?? 0);
                         $paymentMethod === 'cash'
                             ? $livre->livraisonCash($idAgent, $commission, $orderVerrouille->id, $ref)
                             : $livre->livraisonOm($idAgent, $frais, $commission, $orderVerrouille->id, $ref);
                     }
                 }

                 return $misAJour;
             });



                   $freeStatusAgent = Agent::where('id_user', (int) $order->id_agent)->update([

            'freeStatus' => 1

            ]);

          if($update)
         {
             return response()->json(['response' => 200]);
         }
         
         return response()->json(['response' => 400 , 'message'=> 'Une erreur est survenue' ]);
         
         
         }
         
         
          /*
           | L'ancien message annonçait « 3 essai restants » alors qu'aucun
           | décompte n'existe côté serveur : rien n'est compté, rien n'est
           | bloqué. Annoncer un quota imaginaire fait croire à l'agent qu'il
           | risque d'être verrouillé.
           */
          return response()->json(['response' => 400 , 'message'=> 'Code de livraison incorrect']);
    }
    
    
    public function verifiedDeliveryCode(Request $request)
    {
         
        
    }
    
    
      public function getCommandAgent(Request $request)
        {
            // N'excluait que 'Success' : une commande annulée ('failed') ou
            // refusée ('declin') restait "active" pour toujours du point de
            // vue de ce endpoint — c'est lui qu'interroge le bandeau "Course
            // en cours" de l'app agent (active_command_banner.dart), qui
            // continuait donc à s'afficher indéfiniment après une annulation.
            $order = order_detail::where('id_agent',$request->id_user)->whereNotIn('status', ['Success', 'failed', 'declin'])->get();

        if($order) return response()->json(['response' => 200, 'data'=>  $order ]);
        else return response()->json(['response' => 404]);
        }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}
