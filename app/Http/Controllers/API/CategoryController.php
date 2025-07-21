<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use response;

class CategoryController extends Controller
{
    public function getAllCategory()
    {
        $category = Category::where('status','Success')->get();
        return response()->json([
            "response"=>200,
            "data"=>$category,

        ]);

        
    }



    public function storeCategory(Request $request)
    {
        $request->validate([
            "category_name" => 'required|unique:categories'
        ]);

        $image_baniere = $request->file('category_image');
        $img_name = hexdec(uniqid()) . '.' . $image_baniere->getClientOriginalExtension();

        $request->{'category_image'}->move(public_path('upload'), $img_name);
        $img_url = 'upload/' . $img_name;

        Category::insert([
            'category_name'  => $request->{'category_name'},
            'category_code'  => $request->{'category_code'},
            'category_image' => $img_url,
            'slug' => strtolower(str_replace(' ', '-', $request->{'category_name'})),
        ]);
        return response()->json(['message', 'Catégorie ajoutée avec succès!'], 201);
    }
}
