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
            $carts = Cart::where('user_id', $user->id)->where('status','pending')
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
        $carts = Cart::where('user_id', $user->id)->latest()->first();
        // ->where('status', 1);
         // Si l'utilisateur n'a pas de panier, en créer un nouveau

       
               if ( !isset($carts) || $carts->status == "Success"|| $carts->status == "failed") {
                $carts= Cart::create([
                    'user_id' => $user->id,
                    'status'=>"pending"
                ]);
               } 
               else {
                $carts->first();
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

        // Afficher le panier
        $cartItems = CartItem::where('cart_id', $carts->id)->get();
        
       $updateCart = Cart::where('id', $carts->id)->update(['total_amount'=> $carts->total_amount + $product->price*$request->quantity]);
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
