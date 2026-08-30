<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Promotion;
use App\Fonction\Fonction;
use response;

class ProductsController extends Controller
{
    public function getAllProducts()
    {
        $product = Product::where('status','Success')->get();

        // Promotions actives (validées par l'équipe et dans leur fenêtre de
        // dates) : voir MaBoutiqueController::saveMyShopPromotion. Sans cet
        // enrichissement, une promotion pouvait être créée puis activée sans
        // jamais devenir visible côté client — aucun champ ne la reliait à
        // son produit dans la réponse consommée par l'app mobile.
        $promotions = Promotion::where('status', 'Success')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->get()
            ->keyBy('id_product');

        /*
         | Commission de boutique : le prix envoyé au client est majoré du taux
         | convenu avec le marchand, et l'écart revient à l'entreprise (voir
         | App\Support\MajorationBoutique). Appliquée ici, et non côté
         | application, pour que le prix affiché soit exactement celui qui sera
         | facturé — et parce que le marchand, lui, doit continuer de voir son
         | prix de base dans « Ma boutique ».
         |
         | La promotion se calcule sur le prix majoré : une remise de 10 % doit
         | porter sur ce que le client voit, sinon l'affichage annoncerait une
         | réduction que le total ne refléterait pas.
         */
        $majoration = app(\App\Support\MajorationBoutique::class);

        $data = $product->map(function ($item) use ($promotions, $majoration) {
            $array = $item->toArray();

            $prixAffiche = $majoration->prixAffiche((float) $item->price, $item->id_shop, $item->id);
            $array['price'] = $prixAffiche;

            $promotion = $promotions->get($item->id);
            if ($promotion) {
                $array['promotion'] = [
                    'title' => $promotion->title,
                    'discount_type' => $promotion->discount_type,
                    'discount_value' => $promotion->discount_value,
                    'price_after' => round($promotion->prixApres((float) $prixAffiche)),
                ];
            }
            return $array;
        });

        return response()->json([
            "response"=>200,
            "data"=>$data,

        ]);
    }

    public function storeProduct(Request $request)
    {

        $request->validate([
            'designation_tech' => 'required|unique:products',
            'category_id' => 'required',
            'shop_id' => 'required',
            'unit_price' => 'required',
            'quantity' => 'required',
            'description' => 'required',
            'product_image1' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        
     

        $image1 = $request->file('product_image1');
        $img_name1 = hexdec(uniqid()) . '.' . $image1->getClientOriginalExtension();

        $request->{'product_image1'}->move(public_path('upload'), $img_name1);
        $img_url1 = 'upload/' . $img_name1;

        $image2 = $request->file('product_image2');
        $img_name2 = hexdec(uniqid()) . '.' . $image2->getClientOriginalExtension();
        $request->{'product_image2'}->move(public_path('upload'), $img_name2);
        $img_url2 = 'upload/' . $img_name2;

        $image3 = $request->file('product_image3');
        $img_name3 = hexdec(uniqid()) . '.' . $image3->getClientOriginalExtension();
        $request->{'product_image3'}->move(public_path('upload'), $img_name3);
        $img_url3 = 'upload/' . $img_name3;

        $video1 = $request->file('product_video1');
        $video_name1 = hexdec(uniqid()) . '.' . $video1->getClientOriginalExtension();
        $request->{'product_video1'}->move(public_path('upload'), $video_name1);
        $video_url1 = 'upload/' . $video_name1;

        $video2 = $request->file('product_video2');
        $video_name2 = hexdec(uniqid()) . '.' . $video2->getClientOriginalExtension();
        $request->{'product_video2'}->move(public_path('upload'), $video_name2);
        $video_url2 = 'upload/' . $video_name2;

        $shop_logo = $request->file('shop_logo');
        $logo_name = hexdec(uniqid()) . '.' . $shop_logo->getClientOriginalExtension();
        $request->{'shop_logo'}->move(public_path('upload'), $logo_name);
        $shop_logo_url = 'upload/' . $logo_name;

        $category_name = Category::where('id', $request->{'category_id'})->value('category_name');

        $product = Product::create([
            'designation_tech' => $request->{'designation_tech'},
            'description' => $request->{'description'},
            'locality' => $request->{'locality'},
            'price' => $request->{'unit_price'},
            'category' => $category_name,
            'category_id' => $request->{'category_id'},
            'shop_id' => $request->{'shop_id'},
            'bar_code' => $request->{'bar_code'},
            'commission' => $request->{'commission'},
            'shop_logo' => $shop_logo_url,
            'product_image1' => $img_url1,
            'product_image2' => $img_url2,
            'product_image3' => $img_url3,
            'product_video1' => $video_url1,
            'product_video2' => $video_url2,
            'product_length' => $request->{'product_length'},
            'product_width' => $request->{'product_width'},
            'product_epaisseur' => $request->{'product_epaisseur'},
            'product_volume' => $request->{'product_volume'},
            'product_color' => $request->{'product_color'},
            'product_weigth' => $request->{'product_weigth'},
            'parameter1' => $request->{'parameter1'},
            'parameter2' => $request->{'parameter2'},
            'quantity' => $request->{'quantity'},
            'slug' => strtolower(str_replace(' ', '-', $request->{'designation_tech'})),
            'reference' => 'POULET',
            'status' => 2,
        ]);

        $ref = sprintf('%s%s%s%s%s', 'POULET-', $product->{'id'}, now()->format('Y'), now()->format('m'), now()->format('d'));

        $product->update([
            'reference' => $ref,
        ]);

        Category::where('id', $request->{'category_id'})->increment('product_count', 1);
        Shop::where('id', $request->{'shop_id'})->increment('product_count', 1);

        return redirect()->route('listProduct')->with('message', 'Produit ajouté avec succès!');
    }

    public function updateProduct(Request $request)
    {
        $productId = $request->{'id'};

        $request->validate([
            'designation_tech' => 'required|unique:products',
            'category_id' => 'required',
            'shop_id' => 'required',
            'unit_price' => 'required',
            'quantity' => 'required',
            'description' => 'required',
        ]);

        $category_name = Category::where('id', $request->{'category_id'})->value('category_name');

        Product::findOrFail($productId)->update([
            'designation_tech' => $request->{'designation_tech'},
            'description' => $request->{'description'},
            'locality' => $request->{'locality'},
            'unit_price' => $request->{'unit_price'},
            'category' => $category_name,
            'category_id' => $request->{'category_id'},
            'shop_id' => $request->{'shop_id'},
            'bar_code' => $request->{'bar_code'},
            'commission' => $request->{'commission'},
            'product_length' => $request->{'product_length'},
            'product_width' => $request->{'product_width'},
            'product_epaisseur' => $request->{'product_epaisseur'},
            'product_volume' => $request->{'product_volume'},
            'product_color' => $request->{'product_color'},
            'product_weigth' => $request->{'product_weigth'},
            'parameter1' => $request->{'parameter1'},
            'parameter2' => $request->{'parameter2'},
            'quantity' => $request->{'quantity'},
            'slug' => strtolower(str_replace(' ', '-', $request->{'designation_tech'})),
            'status' => 2,
        ]);

        return redirect()->route('listProduct')->with('message', 'Produit modifié avec succès!');
    }

    public function deleteProduct($id)
    {
        $catId = Product::where('id', $id)->value('category_id');
        $shopId = Product::where('id', $id)->value('shop_id');

        Product::findOrFail($id)->delete();

        Category::where('id', $catId)->decrement('product_count', 1);
        Shop::where('id', $shopId)->decrement('product_count', 1);

        return redirect()->route('listProduct')->with('message', 'Produit supprimé avec succès!');
    }

    public function productEdit(Request $request)
    {
        $request->validate([
            "id" => 'required'
        ]);

        try {
            $id = $request->{"id"};

            $product = Product::findOrFail($id);

            if (filled($request->{'category_id'})) {
                Category::where('id', $request->{'category_id'})->decrement('product_count', 1);
            }

            if (filled($request->{'shop_id'})) {
                Shop::where('id', $request->{'shop_id'})->decrement('product_count', 1);
            }
            $prod = $product->updateService([
                'designation_tech' => $request->{'product_name'},
                'description' => $request->{'description'},
                'locality' => $request->{'locality'},
                'unit_price' => $request->{'unit_price'},
                'category' => $request->{'category'},
                'category_id' => $request->{'category_id'},
                'shop_id' => $request->{'shop_id'},
                'bar_code' => $request->{'bar_code'},
                'commission' => $request->{'commission'},
                'product_length' => $request->{'product_length'},
                'product_width' => $request->{'product_width'},
                'product_epaisseur' => $request->{'product_epaisseur'},
                'product_volume' => $request->{'product_volume'},
                'product_color' => $request->{'product_color'},
                'product_weigth' => $request->{'product_weigth'},
                'parameter1' => $request->{'parameter1'},
                'parameter2' => $request->{'parameter2'},
                'quantity' => $request->{'quantity'},
                'slug' => strtolower(str_replace(' ', '-', $request->{'designation_tech'})),
                'statut' => 2,
            ]);

            Category::where('id', $prod->{'category_id'})->increment('product_count', 1);
            Shop::where('id', $prod->{'shop_id'})->increment('product_count', 1);

            return api_response(100);
        } catch (\Exception $ex) {
            return api_response(103, $ex->getMessage());
        }
    }

    public function productdelete(Request $request)
    {
        $request->validate([
            "id" => 'required'
        ]);

        try {
            $id = $request->{"id"};
            $product = Product::findOrFail($id);

            $product->delete();

            Category::where('id', $request->{'category_id'})->decrement('product_count', 1);
            Shop::where('id', $request->{'shop_id'})->decrement('product_count', 1);

            return api_response(100);
        } catch (\Exception $ex) {
            return api_response(103, $ex->getMessage());
        }
    }
    public function getProductsByCategory(Request $request)
    {
        $product = Product::where('status','Success')->where('id_category',$request->id)->get();

        // Même majoration que getAllProducts : un produit ne peut pas
        // s'afficher à deux prix selon qu'on l'atteint par le catalogue ou par
        // sa catégorie.
        $majoration = app(\App\Support\MajorationBoutique::class);

        $data = $product->map(function ($item) use ($majoration) {
            $array = $item->toArray();
            $array['price'] = $majoration->prixAffiche((float) $item->price, $item->id_shop, $item->id);

            return $array;
        });

        return response()->json([
            "response"=>200,
            "data"=>$data,

        ]);


    }

    public function getVariantPrice(Request $request)
    {
        $product = Product::all();
        return response()->json([
            "response"=>200,
            "data"=>$product->where('id',$request->id)
                            ->where('quantity',$request->quantity),

        ]);
    }
}
