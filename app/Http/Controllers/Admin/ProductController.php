<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Fonction\Fonction;
use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ProductController extends Controller
{

    public function listProduct()
    {
        $listProducts = Product::with('shop')->orderBy('id', 'desc')->get();

       

        // dd($listProducts);
        return view('admin.products.product_list', ['listProducts' => $listProducts]);
    }

    public function addProduct()
    {
        $categories = Category::latest()->get();
        $shops = Shop::latest()->get();
        return view('admin.products.add_product', compact('categories', 'shops'));
    }
    public function cathegoryProduct()
    {
        return view('admin.products.product_category');
    }

    public function storeProduct(Request $request)
    {

        $request->validate([
            'name' => 'required|unique:products',
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
        $category_name = Category::where('id', $request->{'category_id'})->value('name');
        $ref = sprintf('%s%s%s%s', 'POULET-', now()->format('Y'), now()->format('m'), now()->format('d'));
        $product = Product::create([
            'name' => $request->{'name'},
            'description' => $request->{'description'},
            'locality' => $request->{'locality'},
             'stock_init' => $request->{'quantity'},
            
            'price' => $request->{'unit_price'},
            'category' => $category_name,
            'id_category' => $request->{'category_id'},
            'id_shop' => $request->{'shop_id'},
            'bar_code' => $request->{'bar_code'},
            'commission' => $request->{'commission'},
            'product_image1' => "https://pouletafc.2gether-network.com/".$img_url1,
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
            'ref' => $ref,
            'status' => "pending",
        ]);


        $product->update([
            'ref' => $ref,
        ]);

        Category::where('id', $request->{'category_id'})->increment('product_count', 1);
        Shop::where('id', $request->{'shop_id'})->increment('product_count', 1);
        session()->flash("good","Produit ajouté avec success");

        return back();

        // return redirect()->route('listProduct')->with('message', 'Produit ajouté avec succès!');
    }

    public function editProductImg($id)
    {
        $productInfo = Product::findOrFail($id);
        return view('admin.products.edit-product-img', compact('productInfo'));
    }

    public function updateProductImg(Request $request)
    {
        $request->validate([
            'product_img' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $id = $request->{'id'};
        $image = $request->file('product_img');
        $img_name = hexdec(uniqid()) . '.' . $image->getClientOriginalExtension();
        $request->{'product_img'}->move(public_path('upload'), $img_name);
        $img_url = 'upload/' . $img_name;

        $category_name = Category::where('id', $request->{'category_id'})->value('category_name');

        Product::findOrFail($id)->update([
            'product_img' => $img_url,
        ]);

        return redirect()->route('listProduct')->with('message', 'Image modifiée avec succès!');
    }

    public function editProduct($id)
    {
        $productInfo = Product::findOrFail($id);
        $categories = Category::latest()->get();
        return view('admin.edit_product', compact('productInfo', 'categories'));
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
}
