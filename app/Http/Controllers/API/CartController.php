<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\CartItem;
use App\Models\User;
use App\Models\Cart;
use App\Models\order_detail;
use DB;

class CartController extends Controller
{

    // Afficher les produits du panier
    public function viewCart(Request $request)
    {
        // Vérifier si l'utilisateur a un panier
        //dd( $request->user());
        $user = User::where('ref', $request->ref)->first();
            if (!$user) {
                return response()->json(['message' => 'User Not Found'], 404);
            }
            /*
            | Le plus récent des paniers en attente : le même que celui dans
            | lequel « addToCartAndView » écrit. Sans ce tri, les deux écrans
            | pouvaient regarder deux paniers différents du même client.
            */
            $carts = Cart::where('user_id', $user->id)
                         ->where('status', 'pending')
                         // Même règle qu'à l'ajout : un panier déjà commandé
                         // n'est plus le panier en cours du client.
                         ->whereNotExists(function ($requete) {
                             $requete->select(\DB::raw(1))
                                 ->from('order_details')
                                 ->whereColumn('order_details.id_cart', 'carts.id');
                         })
                         ->orderByDesc('id')
                         ->first();
                          //->where('status', 1)
                if (!$carts) {
                    return response()->json([ 'response'=> 404,'message' => 'Cart Empty','totalCarts' => 0]);
                }
    
        // Récupérer les produits du panier de l'utilisateur
        $cartItems = CartItem::where('cart_id', $carts->id)
        ->with('product')
        ->where('status','Success')
        ->get();
    
    $totalamount = 0 ;
    
    foreach($cartItems as $data)
    {
        $totalamount = $data->amount*$data->quantity +  $totalamount;
    }
        return response()->json(['response'=> 200 ,'Carts' => $cartItems,'totalCartsItems'=> count($cartItems),'totalAmount'=>$totalamount]);
    }

    // Add product in Cart
    public function addToCartAndView(Request $request)
    {
        // Vérifier si l'utilisateur a un panier en cours

        $user = User::where('ref', $request->ref)->first();
        if (!$user) {
            return response()->json(['message' => 'User Not Found'], 404);
        }
        /*
        | Le panier en cours, et un seul.
        |
        | On prenait le dernier panier créé, quel que soit son statut, pour en
        | ouvrir un neuf s'il était clos. Deux ajouts rapprochés — un double
        | appui, une reprise après coupure réseau — n'en trouvaient donc aucun
        | d'ouvert et en créaient chacun un : le client se retrouvait avec deux
        | paniers, et ses articles répartis entre les deux.
        |
        | Pire, « viewCart » lisait le PREMIER panier en attente pendant que
        | celui-ci écrivait dans le DERNIER : l'article ajouté n'apparaissait pas,
        | le client rajoutait, et l'écart se creusait.
        |
        | On cherche donc explicitement un panier en attente, le plus récent, et
        | les deux écrans regardent désormais le même.
        */
        $carts = Cart::where('user_id', $user->id)
            ->where('status', 'pending')
            /*
            | Jamais un panier qui a déjà servi à une commande.
            |
            | C'est ce qui produisait les montants faux. Un client compose un
            | panier, commande, revient plus tard et ajoute d'autres produits :
            | ceux-ci rejoignaient le panier déjà commandé, qui n'avait pas été
            | refermé. La commande gardait le montant calculé au moment où elle
            | avait été passée, tandis que son panier continuait de grossir — le
            | comptoir voyait alors treize articles à préparer pour 2 500 F.
            |
            | Le statut du panier suffisait en théorie ; en pratique il pouvait
            | rester ouvert, et ce contrôle-ci ne dépend pas de lui.
            */
            ->whereNotExists(function ($requete) {
                $requete->select(\DB::raw(1))
                    ->from('order_details')
                    ->whereColumn('order_details.id_cart', 'carts.id');
            })
            ->orderByDesc('id')
            ->first();

        if (! $carts) {
            $carts = Cart::create([
                'user_id' => $user->id,
                'status' => 'pending',
            ]);
        }
        // Ajouter le produit au panier
        $product = Product::where('id', $request->product_id)->first();
        if (!$product) {
            return response()->json(['message' => 'Produit non trouvé', 'response'=>404]);
        }

        $existingCartItem = CartItem::where('product_id', $product->id)->where("cart_id",$carts->id)->where("status","Success")->first();
        if ($existingCartItem) {
            // Si le produit existe déjà dans le panier, augmenter la quantité

            $existingCartItem->update(['quantity'=>$request->quantity]);
           // $existingCartItem->increment('quantity');
        } else {
            // Sinon, créer un nouvel élément du panier pour ce produit

            $existingCartItem  = CartItem::create([
                'user_id'      => $user->id,
                'product_id'   => $product->id,
                'cart_id'      => $carts->id,
                'quantity'     => $request->quantity, 
                'amount'       => $product->price,
                'status'       =>'Success'
            ]);
        }

        /*
        | Seuls les articles vivants sont renvoyés.
        |
        | Un article retiré du panier passe en « failed », il n'est pas effacé.
        | Sans ce filtre, il revenait dans la réponse : le client voyait
        | réapparaître ce qu'il venait de retirer, et le même produit deux fois
        | s'il le rajoutait ensuite.
        */
        $cartItems = CartItem::where('cart_id', $carts->id)
            ->where('status', 'Success')
            ->get();

        /*
        | Le total est recalculé à partir du panier, au lieu d'être additionné à
        | chaque ajout. L'ancien calcul ajoutait le prix même quand la quantité
        | était seulement corrigée — modifier « 3 » en « 1 » gonflait le total.
        */
        $total = $cartItems->sum(fn ($ligne) => $ligne->amount * $ligne->quantity);

        Cart::where('id', $carts->id)->update(['total_amount' => $total]);
        return response()->json(['response'=> 200,'cartItems' => $cartItems, 'message'=>  "Produit ajouté avec success" ]);
    }


    public function deleteCart(Request $request)
    { 

        $carts = Cart::where('id', $request->id)->update([
            'status'=>'failed'
        ]);

        if($carts) return response()->json(['response' => 200]);
        else return response()->json(['response' => 404]);

    }
    public function deleteProductCart(Request $request)
    {
        $cartItems = CartItem::where('id', $request->id)->update([
            'status'=>'failed'
        ]);


        if($cartItems) return response()->json(['response' => 200]);
        else return response()->json(['response' => 404]);
    }
    
    
    
    public function CreateOrder(Request $request)
    {
        
         $order  = order_detail::create([
                'id_user'      => $request->user_id,
                'id_cart'      => $request->cart_id,
                'longitude'     => $request->longitude, 
                'latitude'       => $request->latitude,
                'status'       =>'pending'
            ]);
            
             if($order) return response()->json(['response' => 200]);
        else return response()->json(['response' => 404]);
        
    }
     public function getOrder(Request $request)
    {
        
        
            
             $order = order_detail::where('id', $request->id_order)->with('carts')->get();

            
             if($order) return response()->json(['response' => 200, 'data'=> $order]);
        else return response()->json(['response' => 404]);
        
    }
    
    
       public function getUserOrder(Request $request)
    {
        
        
            
             $order = order_detail::where('id_user', $request->id_user)->with('carts')->get();

            
             if($order) return response()->json(['response' => 200, 'data'=> $order]);
        else return response()->json(['response' => 404]);
        
    }
    
    public function getPaymentMethod()
    {
        
        $paymentmethod = DB::table('payment_methods')
        ->where('status','Success')
        ->get();
         return response()->json(['response' => 200, 'data'=>$paymentmethod ]);
    }
    
    
    public function updateItem(Request $request)
    {
        
        $cartItems = CartItem::where('id', $request->id)->update([
            'quantity'=> $request->quantity
        ]);
         return response()->json(['response' => 200, ]);
    }
    
    
    
}
