<?php

namespace App\Http\Controllers\Merchand;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Support\Facades\DB;
use Auth;


class SubCategoryController extends Controller
{

    public function subCategoryList()
    {
        $subCategories = SubCategory::latest()->orderBy('id', 'desc')->get();

        return view('merchand.sub-categories.sub-category-list', ['subCategories' => $subCategories]);
    }

    public function addSubCategory()
    {
        $categories = DB::table('categories')
            ->orderBy('id', 'desc')
            ->get();
        return view('merchand.sub-categories.add-sub-category', ['categories' => $categories]);
    }

    public function storeSubCategory(Request $request)
    {
        // $request->validate([
        //     "name" => 'required|unique:sub_categories',
        //     "category_id" => 'required'
        // ]);

        $image = $request->file('image');
        $image_name = hexdec(uniqid()) . '.' . $image->extension();
        $request->{'image'}->move(public_path('upload'), $image_name);
        $image_url = 'upload/' . $image_name;

        $category_name = Category::where('id', $request->{'category_id'})->value('name');
        SubCategory::insert([
            'name' => $request->{'subcategory_name'},
            'id_user'=> Auth::user()->id, 
            'ref' => $request->{'subcategory_code'},
            'image' => $image_url,
            'id_category' => $request->{'category_id'},
            'category_name' => $category_name,
            'slug' => strtolower(str_replace(' ', '-', $request->{'subcategory_name'})),
        ]);
        session()->flash("good","Sous-catégorie ajoutée avec success");

        Category::where('id', $request->{'category_id'})->increment('subcategory_count', 1);


        return redirect()->route('merchandsubcategorylist')->with('message', 'Sous-catégorie ajoutée avec succès!');
    }

    public function editSubCat(Request $request)
    {
        $request->validate([
            "id" => 'required'
        ]);

        try {
            $id = $request->{"id"};

            $subCategory = SubCategory::findOrFail($id);

            if (filled($request->{'category_id'})) {
                Category::where('id', $request->{'category_id'})->decrement('subcategory_count', 1);
            }
            $category_name = Category::where('id', $request->{'category_id'})->value('category_name');
            $subCat = $subCategory->updateService([
                'subcategory_name' => $request->{'subcategory_name'},
                'subcategory_code' => $request->{'subcategory_code'},
                'category_id' => $request->{'category_id'},
                'category_name' => $category_name,
            ]);

            Category::where('id', $subCat->{'category_id'})->increment('subcategory_count', 1);

            return api_response(100);
        } catch (\Exception $ex) {
            return api_response(103, $ex->getMessage());
        }
    }

    public function deleteSubCat(Request $request)
    {
        $request->validate([
            "id" => 'required'
        ]);

        try {
            $id = $request->{"id"};
            $subCategory = SubCategory::findOrFail($id);

            $subCategory->delete();

            Category::where('id', $request->{'category_id'})->decrement('subcategory_count', 1);

            return api_response(100);
        } catch (\Exception $ex) {
            return api_response(103, $ex->getMessage());
        }
    }
}
