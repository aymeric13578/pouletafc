<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\BeginAgentDay;
use App\Fonction\Fonction;
use App\Models\Country;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificationMail;

class UserController extends Controller
{
    public function takeDay(Request $request)
    {
        $ref = $request->input('ref');
        if (!$ref) {
            return response()->json([
                "response" => 400,
                "message" => "Référence utilisateur manquante",
            ]);
        }

        $data = User::where('ref', $ref)->first();
        if (!$data) {
            return response()->json([
                "response" => 400,
                "message" => "Utilisateur inexistant",
            ]);
        }

        User::where('ref', $ref)->update([
            'in_activity' => 1,
            'actual_lat_position_agent' => $request->input('lat'),
            'actual_lon_position_agent' => $request->input('lon'),
        ]);

        BeginAgentDay::create([
            'id_user' => $data->id,
            'lat' => $request->input('lat'),
            'lon' => $request->input('lon'),
            'type' => "beginDay",
        ]);

        return response()->json([
            "response" => 200,
            "message" => "Requête effectuée avec succès",
            "data" => $data->fresh()
        ]);
    }

    public function updateDeliveryPosition(Request $request)
    {
        $userId = $request->input('id_user');
        if (!$userId) {
            return response()->json([
                "response" => 400,
                "message" => "Identifiant utilisateur manquant",
            ]);
        }

        $updated = User::where('id', $userId)->update([
            'longitude' => $request->input('lon'),
            'latitude' => $request->input('lat'),
        ]);

        if ($updated) {
            return response()->json([
                "response" => 200,
                "message" => "Requête effectuée avec succès",
            ]);
        }

        return response()->json([
            "response" => 400,
            "message" => "Échec de la mise à jour",
        ]);
    }

    public function takeDayDesactive(Request $request)
    {
        $ref = $request->input('ref');
        if (!$ref) {
            return response()->json([
                "response" => 400,
                "message" => "Référence utilisateur manquante",
            ]);
        }

        $data = User::where('ref', $ref)->first();
        if (!$data) {
            return response()->json([
                "response" => 400,
                "message" => "Utilisateur inexistant",
            ]);
        }

        User::where('ref', $ref)->update([
            'in_activity' => 0,
            'actual_lat_position_agent' => $request->input('lat'),
            'actual_lon_position_agent' => $request->input('lon'),
        ]);

        BeginAgentDay::create([
            'id_user' => $data->id,
            'lat' => $request->input('lat'),
            'lon' => $request->input('lon'),
            'type' => "endDay",
        ]);

        return response()->json([
            "response" => 200,
            "message" => "Requête effectuée avec succès",
        ]);
    }

    public function getInfoUser(Request $request)
    {
        $ref = $request->input('ref');
        /*
         | Le dossier "agent" (photo, type de véhicule, matricule) vit dans la
         | table `agents` du tableau de bord — jamais alimenté depuis l'app
         | mobile, uniquement saisi par un admin. Chargé ici en relation
         | (User::agent(), via id_user) plutôt que dupliqué sur `users` :
         | c'est le tableau de bord qui gère cette paperasse, l'app ne fait que
         | l'afficher.
         |
         | Colonnes explicitement listées : cette route v1.0 n'a aucune
         | authentification (voir CLAUDE.md règle 8), n'importe qui connaissant
         | un `ref` peut l'appeler pour n'importe quel utilisateur. Un
         | ->with('agent') sans restriction aurait renvoyé en clair le numéro
         | de carte d'identité, le solde de l'agent et les chemins vers ses
         | photos de pièces d'identité — aucun de ces champs n'est affiché par
         | l'app (qui lit photo/vehicule/matricule_vehicule/type), ils n'ont
         | donc rien à faire dans cette réponse.
         */
        $data = User::where('ref', $ref)
            ->with(['agent' => fn ($q) => $q->select('id', 'id_user', 'photo', 'vehicule', 'matricule_vehicule', 'type')])
            ->get();

        /*
         | agents.photo est stocké tantôt en chemin relatif ("upload/x.jpg",
         | convention actuelle), tantôt en URL absolue historique (anciennes
         | lignes créées avant ce format) — le dashboard gère déjà les deux
         | avec url(), qui laisse une URL absolue inchangée et complète un
         | chemin relatif. L'app mobile ne le faisait pas : elle passait le
         | chemin relatif tel quel à NetworkImage(), qui ne peut pas le
         | résoudre — l'avatar restait vide (ni photo ni repli sur les
         | initiales) dès qu'un agent avait une vraie photo.
         */
        foreach ($data as $u) {
            if ($u->agent && $u->agent->photo) {
                $u->agent->photo = url($u->agent->photo);
            }

            /*
             | users.photo — même défaut que agents.photo ci-dessus, mais pour
             | le compte lui-même : un gestionnaire (role=employee_afc) n'a
             | pas de fiche dans `agents` (réservée aux livreurs), donc pas de
             | agent.photo du tout. C'est users.photo, jusqu'ici jamais
             | normalisé par cette route, qui est la seule photo disponible
             | pour ces comptes-là.
             */
            if ($u->photo && ! str_starts_with($u->photo, 'http')) {
                $u->photo = url('upload/' . $u->photo);
            }
        }

        if ($data->isNotEmpty()) {
            return response()->json([
                "response" => 200,
                "data" => $data,
            ]);
        }

        return response()->json([
            "response" => 400,
            "data" => [],
            "message" => "Aucun utilisateur trouvé"
        ]);
    }

    public function register(Request $request)
    {
        $password = $request->input('password');
        $confirmpassword = $request->input('confirmpassword');

        if ($password !== $confirmpassword) {
            return response()->json([
                "response" => 400,
                "message" => "Les deux mots de passe sont différents"
            ]);
        }

        $email = $request->input('email');
        if ($email) {
            $seachUser = User::where('email', $email)->first();
            if ($seachUser) {
                return response()->json([
                    "response" => 400,
                    "message" => "Utilisateur existe déjà !!! impossible de créer un compte"
                ]);
            }
        }

        do {
            $ref = 'REF_' . (new Fonction())->genUniqueID('10');
            $find_ref = DB::table('users')->where('ref', $ref)->exists();
        } while ($find_ref);

        do {
            $confirmation_code = (string) rand(10000, 99999);
            $find_confirmation_code = DB::table('users')->where('confirmation_code', $confirmation_code)->exists();
        } while ($find_confirmation_code);

        $country = Country::find(37);

        $lastname = $request->input('lastname', '');
        $name = $request->input('name', $lastname ?: 'Client');

        /*
         | Numéro de téléphone.
         |
         | L'application n'a qu'un seul champ, intitulé WhatsApp, et n'envoie donc
         | jamais « phone ». La colonne restait vide sur tous les comptes créés
         | depuis le mobile : impossible d'appeler un client, et le SMS de
         | confirmation, conditionné à ce champ, ne partait pas.
         |
         | Les deux colonnes reçoivent la même valeur quand une seule est
         | transmise : c'est bien le même numéro, l'application ne demande le
         | second qu'aux comptes saisis depuis le tableau de bord.
         */
        $phone = trim((string) $request->input('phone', ''));
        $whatsapp = trim((string) $request->input('whatsapp', ''));

        $phone = $phone !== '' ? $phone : $whatsapp;
        $whatsapp = $whatsapp !== '' ? $whatsapp : $phone;

        if ($phone === '') {
            return response()->json([
                "response" => 400,
                "message" => "Le numéro de téléphone est obligatoire"
            ]);
        }

        if (!$email) {
            return response()->json([
                "response" => 400,
                "message" => "L'adresse e-mail est obligatoire"
            ]);
        }

        /*
         | Un numéro déjà pris identifie un compte existant aussi sûrement qu'un
         | e-mail : sans ce contrôle, deux comptes portaient le même numéro et le
         | code de confirmation partait vers celui qu'on ne voulait pas.
         */
        if (User::where('phone', $phone)->orWhere('whatsapp', $phone)->exists()) {
            return response()->json([
                "response" => 400,
                "message" => "Ce numéro est déjà associé à un compte"
            ]);
        }

        try {
            $create = User::create([
                'name' => $name,
                'last_name' => $lastname,
                'ref' => $ref,
                'email' => $email,
                'password' => Hash::make($password),
                'whatsapp' => $whatsapp,
                'phone' => $phone,
                'country' => $country ? $country->name : 'Cameroon',
                'id_country' => $country ? $country->id : 37,
                'country_code' => $country ? $country->phoneCode : '237',
                'city' => $request->input('city'),
                'confirmation_code' => $confirmation_code,
                'status' => 'pending'
            ]);
        } catch (\Throwable $e) {
            Log::error("Erreur lors de la création d'utilisateur: " . $e->getMessage());
            return response()->json([
                "response" => 400,
                "message" => "Impossible de créer le compte. Vérifiez vos informations."
            ]);
        }

        if ($create) {
            $content = "Votre code de confirmation Poulet AFC est " . $confirmation_code;
            $title = "Poulet AFC - votre code de confirmation";
            $object = 'Poulet AFC - votre code de confirmation';

            // Courriel et SMS, chacun indépendant de l'autre : l'échec de l'un
            // ne doit ni empêcher le second, ni faire échouer l'inscription.
            app(\App\Support\NotificationClient::class)->prevenirDirectement(
                $email,
                $create->phone ?: $create->whatsapp,
                $object,
                $content,
                $title
            );

            return response()->json([
                "response" => 200,
                "message" => "Votre code de confirmation Poulet AFC est " . $confirmation_code,
            ]);
        }

        return response()->json([
            "response" => 400,
            "message" => "Échec de l'enregistrement",
        ]);
    }

    /**
     * Renvoie le code de confirmation d'une inscription encore en attente.
     *
     * Sans authentification comme le reste de v1.0 (voir CLAUDE.md règle 8) :
     * n'importe qui connaissant un numéro peut demander un renvoi tant que le
     * compte associé est encore "pending". Pas de limite de fréquence côté
     * serveur — seul le compte à rebours de 120 s côté app dissuade les appels
     * répétés — donc un appel direct à cette route peut déclencher des SMS
     * facturés (voir NotificationClient) sans y être invité par l'app.
     */
    public function resendConfirmationCode(Request $request)
    {
        $phone = trim((string) $request->input('phone', ''));
        if ($phone === '') {
            return response()->json([
                "response" => 400,
                "message" => "Numéro de téléphone manquant",
            ]);
        }

        $user = User::where(function ($q) use ($phone) {
                $q->where('phone', $phone)->orWhere('whatsapp', $phone);
            })
            ->where('status', 'pending')
            ->first();

        if (!$user) {
            return response()->json([
                "response" => 400,
                "message" => "Aucune inscription en attente pour ce numéro",
            ]);
        }

        do {
            $confirmation_code = (string) rand(10000, 99999);
            $find_confirmation_code = DB::table('users')->where('confirmation_code', $confirmation_code)->exists();
        } while ($find_confirmation_code);

        $user->update(['confirmation_code' => $confirmation_code]);

        $content = "Votre code de confirmation Poulet AFC est " . $confirmation_code;
        $title = "Poulet AFC - votre code de confirmation";
        $object = 'Poulet AFC - votre code de confirmation';

        app(\App\Support\NotificationClient::class)->prevenirDirectement(
            $user->email,
            $user->phone ?: $user->whatsapp,
            $object,
            $content,
            $title
        );

        return response()->json([
            "response" => 200,
            "message" => "Un nouveau code vous a été envoyé",
        ]);
    }

    public function updateUser(Request $request)
    {
        $ref = $request->input('ref');
        $seachUser = User::where('ref', $ref)->first();

        if (!$seachUser) {
            return response()->json([
                "response" => 400,
                "message" => "Utilisateur inexistant"
            ]);
        }

        $update = User::where('ref', $ref)->update([
            'name' => $request->input('name', $seachUser->name),
            'last_name' => $request->input('lastname', $seachUser->last_name),
            'whatsapp' => $request->input('whatsapp', $seachUser->whatsapp),
            'phone' => $request->input('phone', $seachUser->phone),
            'city' => $request->input('city', $seachUser->city),
        ]);

        if ($update) {
            return response()->json([
                "response" => 200,
                "message" => "Compte modifié avec succès",
            ]);
        }

        return response()->json([
            "response" => 400,
            "message" => "Aucune modification effectuée",
        ]);
    }

    public function deleteUser(Request $request)
    {
        $ref = $request->input('ref');
        $password = $request->input('password');

        if (!$ref) {
            return response()->json([
                "response" => 400,
                "message" => "Référence utilisateur manquante",
            ]);
        }

        $seachUser = User::where('ref', $ref)->first();

        if (!$seachUser) {
            return response()->json([
                "response" => 400,
                "message" => "Utilisateur inexistant"
            ]);
        }

        // Confirmation par mot de passe avant suppression définitive
        if (!$password || !Hash::check($password, $seachUser->password)) {
            return response()->json([
                "response" => 400,
                "message" => "Mot de passe incorrect"
            ]);
        }

        try {
            $seachUser->purgeAccount();
        } catch (\Throwable $e) {
            Log::error("Échec suppression compte (ref {$ref}) : " . $e->getMessage());
            return response()->json([
                "response" => 400,
                "message" => "Impossible de supprimer le compte. Veuillez réessayer."
            ]);
        }

        return response()->json([
            "response" => 200,
            "message" => "Votre compte et vos données personnelles ont été supprimés définitivement"
        ]);
    }

    public function changePassword(Request $request)
    {
        $ref = $request->input('ref');
        $password = $request->input('password');
        $newpassword = $request->input('newpassword');
        $confirmpassword = $request->input('confirmpassword');

        $seachUser = User::where('ref', $ref)->first();

        if (!$seachUser) {
            return response()->json([
                "response" => 400,
                "message" => "Utilisateur inexistant"
            ]);
        }

        if (!Hash::check($password, $seachUser->password)) {
            return response()->json([
                "response" => 400,
                "message" => "Le mot de passe actuel est incorrect"
            ]);
        }

        if ($newpassword !== $confirmpassword) {
            return response()->json([
                "response" => 400,
                "message" => "Les deux mots de passe sont différents"
            ]);
        }

        $update = User::where('ref', $ref)->update([
            'password' => Hash::make($newpassword),
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
        $field = $request->filled('whatsapp') ? 'whatsapp' : ($request->filled('email') ? 'email' : ($request->filled('phone') ? 'phone' : 'email'));
        $identifier = $request->input($field);
        $password = $request->input('password');

        if (!$identifier || !$password) {
            return response()->json([
                "response" => 400,
                "message" => "Identifiant ou mot de passe manquant",
            ]);
        }

        if (Auth::attempt([$field => $identifier, 'password' => $password, 'status' => "Success"])) {
            $seachUser = User::where($field, $identifier)->first();

            /*
             | Jeton Sanctum (le modèle User utilise déjà HasApiTokens) — sert
             | à prouver, sur les endpoints « Ma boutique », que l'appelant est
             | bien ce compte plutôt qu'un id_user arbitraire fourni en
             | paramètre (voir App\Http\Controllers\API\MaBoutiqueController).
             | N'affecte aucun autre endpoint v1.0, qui continuent de
             | fonctionner sans authentification comme avant (CLAUDE.md règle
             | 8).
             */
            $token = $seachUser->createToken('client-mobile')->plainTextToken;

            return response()->json([
                "response" => 200,
                "message" => "Connexion établie avec succès",
                "data" => $seachUser,
                "token" => $token,
            ]);
        }

        return response()->json([
            "response" => 400,
            "message" => "Utilisateur inexistant ou compte invalide",
        ]);
    }

    public function loginDelivery(Request $request)
    {
        $field = $request->filled('email') ? 'email' : ($request->filled('phone') ? 'phone' : 'whatsapp');
        $identifier = $request->input($field);
        $password = $request->input('password');

        if (!$identifier || !$password) {
            return response()->json([
                "response" => 404,
                "message" => "Identifiant ou mot de passe manquant",
            ]);
        }

        if (Auth::attempt([$field => $identifier, 'password' => $password])) {
            $seachUser = User::where($field, $identifier)->first();

            if (! in_array($seachUser->role, ["agent", "admin"], true)) {
                return response()->json([
                    "response" => 404,
                    "message" => "Désolé vous n'êtes pas un agent",
                ]);
            }

            if ($seachUser->status !== "Success") {
                return response()->json([
                    "response" => 404,
                    "message" => "Votre compte est en cours de configuration",
                ]);
            }

            /*
             | Jeton Sanctum, même mécanisme que UserController::login() —
             | voir App\Support\ApiAuthentification, qui le vérifiera sur
             | chaque endpoint protégé (spec 2026-09-01).
             */
            $token = $seachUser->createToken('agent-mobile')->plainTextToken;

            return response()->json([
                "response" => 200,
                "message" => "Connexion établie avec succès",
                "data" => $seachUser,
                "token" => $token,
            ]);
        }

        return response()->json([
            "response" => 404,
            "message" => "Utilisateur inexistant ou compte invalide",
        ]);
    }

    public function validateCompte(Request $request)
    {
        $code = $request->input('code');
        if (!$code) {
            return response()->json([
                "response" => 400,
                "message" => "Code de confirmation manquant",
            ]);
        }

        $user = User::where('confirmation_code', $code)->first();

        if ($user) {
            $user->update([
                'status' => 'Success',
                'confirmation_code' => ''
            ]);

            $content = "Compte validé avec succès";
            $title = "Poulet AFC - validation de votre compte";
            $object = 'Poulet AFC - validation de votre compte';

            if ($user->email) {
                try {
                    Mail::to($user->email)->send(new NotificationMail($object, $content, $title));
                } catch (\Throwable $e) {
                    Log::error("Échec envoi mail validation : " . $e->getMessage());
                }
            }

            return response()->json([
                "response" => 200,
                "message" => "Activation du compte effectuée avec succès",
            ]);
        }

        return response()->json([
            "response" => 400,
            "message" => "Code incorrect",
        ]);
    }

    public function loginEmployee(Request $request)
    {
        $field = $request->filled('email') ? 'email' : ($request->filled('phone') ? 'phone' : 'whatsapp');
        $identifier = $request->input($field);
        $password = $request->input('password');

        if (!$identifier || !$password) {
            return response()->json([
                "response" => 404,
                "message" => "Identifiant ou mot de passe manquant",
            ]);
        }

        if (Auth::attempt([$field => $identifier, 'password' => $password])) {
            $seachUser = User::where($field, $identifier)->first();

            if (! in_array($seachUser->role, ["employee_afc", "admin"], true)) {
                return response()->json([
                    "response" => 404,
                    "message" => "Désolé vous n'êtes pas un gestionnaire AFC",
                ]);
            }

            if ($seachUser->status !== "Success") {
                return response()->json([
                    "response" => 404,
                    "message" => "Votre compte est en cours de configuration",
                ]);
            }

            return response()->json([
                "response" => 200,
                "message" => "Connexion établie avec succès",
                "data" => $seachUser
            ]);
        }

        return response()->json([
            "response" => 404,
            "message" => "Utilisateur inexistant ou compte invalide",
        ]);
    }

    public function sendOtpCode(Request $request)
    {
        $method = $request->input('method');
        $value = $request->input('value');

        if (!$method || !$value) {
            return response()->json([
                "response" => 404,
                "message" => "Méthode ou valeur manquante",
            ]);
        }

        $seachUser = null;
        if ($method === 'email') {
            $seachUser = User::where('email', $value)->first();
        } elseif ($method === 'sms') {
            $seachUser = User::where('phone', $value)->orWhere('whatsapp', $value)->first();
        }

        if ($seachUser) {
            do {
                $confirmation_code = (string) rand(10000, 99999);
                $find_ref = DB::table('users')->where('confirmation_code', $confirmation_code)->exists();
            } while ($find_ref);

            $seachUser->update([
                'confirmation_code' => $confirmation_code
            ]);

            /*
             | Les deux canaux, quel que soit celui demandé.
             |
             | C'était auparavant l'un ou l'autre : demander « sms » n'envoyait
             | aucun courriel. Or Orange accepte les SMS, les facture et n'en
             | remet aucun — le client restait donc devant un écran lui affirmant
             | qu'un code venait de partir, sans rien recevoir nulle part.
             */
            $envois = app(\App\Support\NotificationClient::class)->prevenir(
                $seachUser,
                'RESTAURER COMPTE AFC',
                "Votre code de confirmation Poulet AFC est " . $confirmation_code
            );

            /*
             | On annonce le canal réellement emprunté plutôt qu'un succès vague :
             | « envoyé avec succès » devant une boîte vide use la confiance plus
             | vite qu'un échec annoncé.
             */
            return response()->json([
                "response" => 200,
                "message" => $envois['mail']
                    ? "Code de confirmation envoyé par e-mail."
                    : ($envois['sms']
                        ? "Code de confirmation envoyé par SMS."
                        : "Aucun moyen de contact valide sur ce compte."),
                "canaux" => $envois,
            ]);
        }

        return response()->json([
            "response" => 404,
            "message" => "Une erreur est survenue ou utilisateur introuvable",
        ]);
    }

    public function verifyOtpChangePassword(Request $request)
    {
        $method = $request->input('method');
        $value = $request->input('value');
        $otp = $request->input('otp');

        $seachUser = null;
        if ($method === 'email') {
            $seachUser = User::where('email', $value)->where('confirmation_code', $otp)->first();
        } elseif ($method === 'sms') {
            $seachUser = User::where('phone', $value)->where('confirmation_code', $otp)->first();
        }

        if ($seachUser) {
            return response()->json([
                "response" => 200,
                "message" => "Code de confirmation correct",
            ]);
        }

        return response()->json([
            "response" => 404,
            "message" => "Code incorrect",
        ]);
    }

    public function changePasswordByOtp(Request $request)
    {
        $method = $request->input('method');
        $value = $request->input('value');
        $otp = $request->input('otp');
        $password = $request->input('password');

        $seachUser = null;
        if ($method === 'email') {
            $seachUser = User::where('email', $value)->where('confirmation_code', $otp)->first();
        } elseif ($method === 'sms') {
            $seachUser = User::where('phone', $value)->where('confirmation_code', $otp)->first();
        }

        if ($seachUser && $password) {
            $seachUser->update([
                'password' => Hash::make($password),
                'confirmation_code' => ""
            ]);

            return response()->json([
                "response" => 200,
                "message" => "Mot de passe modifié avec succès",
            ]);
        }

        return response()->json([
            "response" => 404,
            "message" => "Impossible de modifier le mot de passe",
        ]);
    }
}