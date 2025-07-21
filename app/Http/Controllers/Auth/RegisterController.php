<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Fonction\SendMail;
use App\Fonction\Fonction;
use App\Models\Country;
use Auth;

use DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificationMail;



class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/login';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'birth' => ['required'],
            'whatsapp' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    public function registerform()
    {
        $country = DB::table('countries')->get();
            
        return view('register',['countries'=>$country]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        do {
            $ref = 'REF_' . (new Fonction())->genUniqueID('10');
            $find_ref = \DB::select('select * from users where ref="' . $ref . '"');
        } while (!empty($find_ref));

        do {
            $confirmation_code = rand(10000, 99999);
            $find_confirmation_code = \DB::select('select * from users where confirmation_code="' . $confirmation_code . '"');
        } while (!empty($find_confirmation_code));



        $country = Country::find(37);

         $function  = new Fonction();
         $function->sendSms("Votre code de confirmation POULET AFC est " . $confirmation_code, $data['phone']);
        
        $content = "Votre code de confirmation POULET AFC est " . $confirmation_code;
        $title = "NOUVEAU COMPTE POULET AFC";
        $object = 'NOUVEAU COMPTE POULET AFC';
        Mail::to($data['email'])
            ->send(new NotificationMail($object, $content, $title));

        // dd($country->name);


        return User::create([

            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'ref' => $ref,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'birth' => $data['birth'],
            'phone' => $data['phone'],
            'country' => $country->name,
            'id_country' => $country->id,
            'country_code' => $country->phoneCode,
            'city' => $data['city'],
            'confirmation_code' => $confirmation_code,
            'status' => 'pending'

        ]);
    }
}
