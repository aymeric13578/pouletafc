<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Clando;
use App\Models\order_detail;
use App\Models\CreditAgent;
use Illuminate\Support\Facades\DB;
use App\Fonction\Fonction;
class FinanceController extends Controller
{
    public function getfinanceAgent(Request $request)
    {
        
        
         $solde =(new Fonction())->solde($request->id_user);
         
         
         
        $totalearnClando = $solde['totalearnclando'];
        $totalearnCommand =  $solde['totalearncommand'];
        $totalcredit =  $solde['totalcredit'];
        $totaldeposit =  $solde['totaldeposit'];
        $soldeAgent =  $solde['solde'];
        
        
        $historiqueClando = DB::table('clando')->where('id_agent',$request->id_user)->where('status','Success')->get();
        $historiqueCommand = DB::table('order_details')->where('id_agent',$request->id_user)->where('status','Success')->get();
        $historiqueCredit = DB::table('credit_agents')->where('id_agent',$request->id_user)->get();
        $historiquedeposit = DB::table('deposits')->where('id_agent',$request->id_user)->where('status','Success')->get();
        
       
        
        return response()->json(['response' => 200,
        'totalearn'=> $totalearnClando +  $totalearnCommand,
        'totalcredit'=> $totalcredit, 
        'totaldeposit'=> $totaldeposit,
        "solde" =>  $solde,
        "historiqueClando"=> $historiqueClando,
        "historiqueCommand"=> $historiqueCommand,
         "historiquecredit"=> $historiqueCredit,
         "historiquedeposit"=> $historiquedeposit,
        
        ]);
    }
}
