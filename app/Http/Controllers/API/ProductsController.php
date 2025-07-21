<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Fonction\Fonction;
use response;

class ProductsController extends Controller
{
    public function getAllProducts()
    {
        $product = Product::where('status','Success')->get();
        return response()->json([
            "response"=>200,
            "data"=>$product,

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
        return response()->json([
            "response"=>200,
            "data"=>$product,

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
