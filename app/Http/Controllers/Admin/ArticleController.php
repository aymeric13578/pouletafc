<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Article;
use Auth;
use DB;

class ArticleController extends Controller
{
    public function addArticle()
    {
        return view('admin.articles.add_article');
    }

    public function articleList()
    {
        $articles = DB::table('articles')
            ->orderBy('id', 'desc')
            ->get();
        return view('admin.articles.article_list', ['articles' => $articles]);
    }
    public function storeArticle(Request $request)
    {
        // $request->validate([
        //     "tit" => 'required|unique:shops'
        // ]);




        $banner = $request->file('image');
        $banner_name = hexdec(uniqid()) . '.' . $banner->getClientOriginalExtension();
        $request->{'image'}->move(public_path('upload'), $banner_name);
        $banner_url = 'upload/' . $banner_name;

        Article::insert([
            'title' => $request->{'title'},
            'id_user'=> Auth::user()->id,
            'image' =>"https://pouletafc.2gether-network.com/".$banner_url,
            'description' => $request->{'description'},

        ]);

        session()->flash("good","Article  ajoutée avec success");


        return redirect()->route('listArticle')->with('message', 'Article ajoutée avec succès!');
    }
}
