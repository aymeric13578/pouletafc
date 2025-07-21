<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BeginAgentDay;
use App\Fonction\SendMail;
use App\Fonction\Fonction;
use App\Models\Country;
use Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    
    public function takeDay(Request $request)
    {
           $data = User::Where('ref',$request->ref)->first();
           
                 if(!$data)
        {
             return response()->json([
                "response"=>400,
                "message"=>"utilisateur inexistant",
    
            ]);
        }
           
        
            User::Where('ref',$request->ref)->update(
                [
                    'in_activity'=>1,
                    
                    'actual_lat_position_agent'=>$request->lat,
                
                     'actual_lon_position_agent'=>$request->lon,
                    
                    ]);
        
        
           
       
           $insert = BeginAgentDay::create([

            'id_user' =>$data->id,
            'lat' => $request->lat,
            'lon' => $request->lon,
             'type' => "beginDay",
            

        ]);
         return response()->json([
                "response"=>200,
                "message"=>"requete effectuée avec success",
                "data"=>$data
    
            ]); 
        
    }
    
    public function updateDeliveryPosition(Request $request)
    {
            $data = User::Where('id',$request->id_user)->update(
                [
                    'longitude'=>$request->lon,
                    'latitude'=>$request->lat,
                

                    
                    ]);
                    
                    
                    if($data)
                    {
                         return response()->json([
                "response"=>200,
                "message"=>"requete effectuée avec success",
    
                        ]);
                    }
                    
                      return response()->json([
                "response"=>400,
                
    
            ]);
    }
    
      public function takeDayDesactive(Request $request)
    {
        
            User::Where('ref',$request->ref)->update(
                [
                    'in_activity'=>0,
                    'actual_lat_position_agent'=>$request->lat,
                
                     'actual_lon_position_agent'=>$request->lon,
                    
                    ]);
        
           $data = User::Where('ref',$request->ref)->first();
           
             if(!$data)
        {
             return response()->json([
                "response"=>400,
                "message"=>"utilisateur inexistant",
    
            ]);
        }
           
           $insert = BeginAgentDay::create([

            'id_user' =>$data->id,
            'type' => "endDay",
            

        ]);
         return response()->json([
                "response"=>200,
                "message"=>"requete effectuée avec success",
    
            ]); 
        
    }
    public function getInfoUser(Request $request)
    {

        $data = User::Where('ref',$request->ref)->get();

        if($data)
        {
            return response()->json([
                "response"=>200,
                "data"=>$data,
    
            ]);
        }
        else{
            return response()->json([
                "response"=>400,
                "data"=>$data,
    
            ]);
        }

       
    }

    public function register(Request $request)
    {



        $data = $request->all();
        
        
        if($request->password != $request->confirmpassword)
        {
            return response()->json([
                "response"=>400,
                "message"=>"Les deux mots de passe sont différents"
              
    
            ]);

        }



        $seachUser = User::where('email',$data['email'])->first();

        if($seachUser)
        {
            return response()->json([
                "response"=>$seachUser,
                "message"=>"Utilisateur existe déjà !!! impossible de créer un compte"
              
    
            ]);

        }

        do {
            $ref = 'REF_' . (new Fonction())->genUniqueID('10');
            $find_ref = \DB::select('select * from users where ref="' . $ref . '"');
        } while (!empty($find_ref));

        do {
            $confirmation_code = rand(10000, 99999);
            $find_confirmation_code = \DB::select('select * from users where confirmation_code="' . $confirmation_code . '"');
        } while (!empty($find_confirmation_code));



        $country = Country::find(37);

       
        // dd($country->name);


        $create = User::create([

            'name' => $data['name'],
            'last_name' => $data['lastname'],
            'ref' => $ref,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'whatsapp' => $data['whatsapp'],
            'phone' => $data['phone'],
            'country' => $country->name,
            'id_country' => $country->id,
            'country_code' => $country->phoneCode,
            'city' => $data['city'],
            'confirmation_code' => $confirmation_code,
            'status' => 'pending'

        ]);

        if($create)
        {

            $content = "Votre code de confirmation POULET AFC est " . $confirmation_code;
            $title = "NOUVEAU COMPTE POULET AFC";
            $object = 'NOUVEAU COMPTE POULET AFC';
            Mail::to($data['email'])
                ->send(new NotificationMail($object, $content, $title));
    
           return response()->json([
                "response"=>200,
                "message"=>"Votre code de confirmation POULET AFC est " . $confirmation_code,
    
            ]); 
        }

        return response()->json([
            "response"=>400,
          

        ]);
    }


    public function updateUser(Request $request)
    {
        
         $data = $request->all();
        
        $seachUser = User::where('ref',$data['ref'])->first();

          if(!$seachUser)
        {
            return response()->json([
                "response"=>$seachUser,
                "message"=>"Utilisateur inexitant"
              
    
            ]);

        }
        
        
       
             $update = User::where('ref', $data['ref'])->update([

            'name' => $data['name'],
            'last_name' => $data['lastname'],
            'whatsapp' => $data['whatsapp'],
            'phone' => $data['phone'],
            'city' => $data['city'],
           

        ]);
        
           if($update)
        {

          
    
           return response()->json([
                "response"=>200,
                "message"=>"Compte modifié avec success",
    
            ]); 
        }
        
              return response()->json([
            "response"=>400,
          

        ]);
        
        
        
        
        
        
    }
    
    
    
    
  public function changePassword(Request $request)
{
    $data = $request->all();

    // Vérifier si le mot de passe actuel correspond à celui de la base de données
    $seachUser = User::where('ref', $data['ref'])->first();

    if (!$seachUser) {
        return response()->json([
            "response" => 400,
            "message" => "Utilisateur inexistant"
        ]);
    }

    if (!Hash::check($data['password'], $seachUser->password)) {
        return response()->json([
            "response" => 400,
            "message" => "Le mot de passe actuel est incorrect"
        ]);
    }

    // Vérifier si le nouveau mot de passe et la confirmation correspondent
    if ($request->newpassword != $request->confirmpassword) {
        return response()->json([
            "response" => 400,
            "message" => "Les deux mots de passe sont différents"
        ]);
    }

    // Mettre à jour le mot de passe
    $update = User::where('ref', $data['ref'])->update([
        'password' => Hash::make($data['newpassword']),
    ]);

    if ($update) {
        return response()->json([
            "response" => 200,
            "message" => "Mot de passe modifié avec succès"
        ]);
    }

    return response()->json([
        "response" => 400,
        "message" => "Échec de la modification du mot de passe"
    ]);
}
    
    
    
    

    public function login(Request $request)
    {
        $data = $request->all();


        if (Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'status' => "Success"])) {
           
        $seachUser = User::where('email',$data['email'])->first();

        return response()->json([
            "response"=>200,
            "message"=>" Connexion établie avec success",
            "data"=>$seachUser

        ]);
        }

        return response()->json([
            "response"=>400,
            "message"=>"Utilisateur inexistant ou compte invalide",
            

        ]);
    }
    public function loginDelivery(Request $request)
    {
        $data = $request->all();


        if (Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
           
        $seachUser = User::where('email',$data['email'])->first();
        
         if($seachUser->role != "agent")
        {
              return response()->json([
            "response"=>404,
            "message"=>"Désolé vous n'êtes pas un agent",
            

        ]);
        }
        
        if($seachUser->status != "Success")
        {
              return response()->json([
            "response"=>404,
            "message"=>"Votre compte est en cours de configuration",
            

        ]);
        }

        return response()->json([
            "response"=>200,
            "message"=>" Connexion établie avec success",
            "data"=>$seachUser

        ]);
        }

        return response()->json([
            "response"=>404,
            "message"=>"Utilisateur inexistant ou compte invalide",
            

        ]);
    }

    public function validateCompte(Request $request)
    {   
        $data = $request->all();

        $user = User::where('confirmation_code',$data['code'])->first();

        if($user)
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
          

    return response()->json([
        "response"=>200,
        "message"=>"Activation du compte effectué avec success",
        

    ]);
        }

        return response()->json([
            "response"=>400,
            "message"=>"code incorrect",
            
    
        ]);

    }
    
    
    public function loginEmployee(Request $request)
    {
        $data = $request->all();


        if (Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
           
        $seachUser = User::where('email',$data['email'])->first();
        
         if($seachUser->role != "employee_afc")
        {
              return response()->json([
            "response"=>404,
            "message"=>"Désolé vous n'êtes pas un gestionnaire AFC ",
            

        ]);
        }
        
        if($seachUser->status != "Success")
        {
              return response()->json([
            "response"=>404,
            "message"=>"Votre compte est en cours de configuration",
            

        ]);
        }

        return response()->json([
            "response"=>200,
            "message"=>" Connexion établie avec success",
            "data"=>$seachUser

        ]);
        }

        return response()->json([
            "response"=>404,
            "message"=>"Utilisateur inexistant ou compte invalide",
            

        ]);
    }
    
    
    public function sendOtpCode(Request $request)
    
    {
         $data = $request->all();
         
         
        if($data['method'] == 'email')
        {
              $seachUser = User::where('email',$data['value'])->first();
              
              
        }
        
         if($data['method'] == 'sms')
        {
              $seachUser = User::where('phone',$data['value'])->first();
        }
        
        
        if(isset($seachUser))
        {
                do {
            $confirmation_code =  rand(10000, 99999);
            $find_ref = \DB::select('select * from users where confirmation_code="' . $confirmation_code . '"');
              } while (!empty($find_ref));
              
              $seachUser->update([
                  'confirmation_code'=> $confirmation_code
                  
                  ]);
        
         if($data['method'] == 'email')
        {
             
            $content = "Votre code de confirmation POULET AFC est " . $confirmation_code;
            $title = " RESTAURER COMPTE AFC";
            $object = 'RESTAURER COMPTE AFC';
            Mail::to($seachUser->email)
                ->send(new NotificationMail($object, $content, $title));
              
        }
        
        
        
           if($data['method'] == 'sms')
        {
             
        $function  = new Fonction();
         $function->sendSms("Votre code de confirmation POULET AFC est " . $confirmation_code,$seachUser->phone);
             
        }
        
        
        
         return response()->json([
            "response"=>200,
            "message"=>"Code de confirmation envoyé avec success",
            

        ]);
        
        
        
        
        }
        
         return response()->json([
            "response"=>404,
            "message"=>"Une erreur est survenue",
            

        ]);
        
        
        
        
        
    }
    
    
    public function verifyOtpChangePassword(Request $request)
    {
        $data = $request->all();
         if($data['method'] == 'email')
        {
              $seachUser = User::where('email',$data['value'])->where('confirmation_code',$data['otp'])->first();
              
              
        }
        
         if($data['method'] == 'sms')
        {
              $seachUser = User::where('phone',$data['value'])->where('confirmation_code',$data['otp'])->first();
              
              
        }
        
            if(isset($seachUser))
        {
            
              return response()->json([
            "response"=>200,
            "message"=>"Code de confirmation correct",
            

        ]); 
            
            
        }
        
        
        
         return response()->json([
            "response"=>404,
            "message"=>"Code incorrect",
            

        ]);
        
        
    }
    
    
    
      public function changePasswordByOtp(Request $request)
    {
        $data = $request->all();
         if($data['method'] == 'email')
        {
              $seachUser = User::where('email',$data['value'])->where('confirmation_code',$data['otp'])->first();
              
              
        }
        
         if($data['method'] == 'sms')
        {
              $seachUser = User::where('phone',$data['value'])->where('confirmation_code',$data['otp'])->first();
              
              
        }
        
            if(isset($seachUser))
        {
            
               $seachUser->update([
                   'password' => Hash::make($data['password']),
                  'confirmation_code'=> ""
                   
                  
                  ]);
            
              return response()->json([
            "response"=>200,
            "message"=>"Mot de passe modifié avec success",
            

                ]); 
            
            
        }
        
        
        
         return response()->json([
            "response"=>404,
            "message"=>"Impossible de modifier le mot de passe",
            

        ]);
        
        
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}
