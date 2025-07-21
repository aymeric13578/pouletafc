<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Fonction\SendMail;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificationMail;
class ValidateCompte extends Controller
{
    
   
   public function index(Request $request)
   {
  $user = User::where('ref',$request->ref)->get();
  if($user->isEmpty())
  {
      session()->flash('alert','erreur');
     return redirect()->route('login');

  }

    return view('auth.validateCompte',['ref'=>$request->ref]);
   }
   
    public function create(Request $request)
    {
        $request->validate(
            [
                "code"=>['required', 'int'],
            ]
            );
            $user = User::where('confirmation_code',$request->code)->first();

            if(isset($user))
            {
                $user->update([
                    'status'=>'Success',
                    'confirmation_code'=>''
                ]);

            

                
        $content = "Compte validé avec success ";
        $title = "Validation de compte POULET AFC";
        $object = 'Validation de compte POULET AFC';
         Mail::to($user->email)
        ->send(new NotificationMail($object,$content,$title)) ; 
               return redirect()->route('login');

            }

            session()->flash("alert",'Erreur de code. Veuillez réessayer');
            return back();

            



    }
}
