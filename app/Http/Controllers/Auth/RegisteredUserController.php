<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use App\Fonction\SendMail;
use App\Fonction\Fonction;
use App\Models\Country;
use DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificationMail;
class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'birth' => ['required'],
            'whatsapp' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:50'],
         
        ]);

        do {
            $ref = 'REF_' . (new Fonction())->genUniqueID('10');
            $find_ref = \DB::select('select * from users where ref="' . $ref . '"');
        } while (!empty($find_ref));

        do {
            $confirmation_code = rand(10000, 99999);
            $find_confirmation_code = \DB::select('select * from users where confirmation_code="' . $confirmation_code . '"');
        } while (!empty($find_confirmation_code));



        $country = Country::find(37);

        $content = "Votre code de confirmation Poulet AFC est " . $confirmation_code;
        $title = "Poulet AFC - votre code de confirmation";
        $object = 'Poulet AFC - votre code de confirmation';
        Mail::to($request->email)
            ->send(new NotificationMail($object, $content, $title));

        // dd($country->name);


    

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'ref' => $ref,
            'birth' => $request->birth,
            'phone' => $request->phone,
            'country' => $country->name,
            'id_country' => $country->id,
            'country_code' => $country->phoneCode,
            'city' => $request->city,
            'whatsapp' => $request->whatsapp,
            'confirmation_code' => $confirmation_code,
            'status' => 'pending'
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }
}
