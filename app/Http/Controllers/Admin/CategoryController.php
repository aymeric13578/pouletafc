<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use App\Models\Shop;

use Illuminate\Http\Request;

class CategoryController extends Controller
{

    public function categoryList()
    {
        $categories = DB::table('categories')
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.categories.category-list', ['categories' => $categories]);
    }

    public function addCategory()
    {
        $shops = Shop::latest()->get();

        return view('admin.categories.add-category',['shops' => $shops]);
    }

    public function storeCategory(Request $request)
    {
        // $request->validate([
        //     "name" => 'required|unique:categories'
        // ]);

        $image_baniere = $request->file('category_image');
        $img_name = hexdec(uniqid()) . '.' . $image_baniere->extension();

        $request->{'category_image'}->move(public_path('upload'), $img_name);
        $img_url = 'upload/' . $img_name;

        Category::insert([
            'name'  => $request->{'category_name'},
            'ref'  => $request->{'category_code'},
            'image' => url($img_url),
            'slug' => strtolower(str_replace(' ', '-', $request->{'category_name'})),
            'id_shop'  => $request->{'shop_id'}
        ]);
        session()->flash("good","Catégorie ajoutée avec success");

        return redirect()->route('categorylist')->with('message', 'Catégorie ajoutée avec succès!');
    }

    public function editCategory($id)
    {
        $category_info = Category::findOrfail($id);
        return view('admin.categories.edit_category', compact('category_info'));
    }

    public function updateCategory(Request $request)
    {
        $id = $request->{'id'};
        $request->validate([
            "category_name" => 'required|unique:categories'
        ]);

        Category::findOrFail($id)->update([
            'category_name' => $request->{'category_name'},
            'category_code' => $request->{'category_code'},
            'slug' => strtolower(str_replace(' ', '-', $request->{'category_name'})),
        ]);

        return redirect()->route('categorylist')->with('message', 'Catégorie mise à jour avec succès!');
    }

    public function deleteCategory($id)
    {
        Category::findOrFail($id)->delete();
        return redirect()->route('categorylist')->with('message', 'Catégorie supprimée avec succès');
    }

    public function editCat(Request $request)
    {
        $request->validate([
            "id" => 'required'
        ]);

        try {
            $id = $request->{"id"};

            $category = Category::findOrFail($id);

            $category->update([
                "category_name" => $request->{"designation"},
                "category_code" => $request->{"code"},
            ]);

            return api_response(100);
        } catch (\Exception $ex) {
            return api_response(103, $ex->getMessage());
        }
    }

    public function deleteCat(Request $request)
    {
        $request->validate([
            "id" => 'required'
        ]);

        try {
            $id = $request->{"id"};
            $category = Category::findOrFail($id);

            $category->delete();

            return api_response(100);
        } catch (\Exception $ex) {
            return api_response(103, $ex->getMessage());
        }
    }
}