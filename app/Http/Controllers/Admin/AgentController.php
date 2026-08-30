<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\CreditAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class AgentController extends Controller
{
    /**
     * Afficher la liste des agents
     */
    public function agentList()
    {
        $agents = Agent::whereNull('deleted_at')->latest()->paginate(10);
        return view('admin.agents.agent_list', compact('agents'));
    }

    /**
     * Afficher la page de gestion des cautions (peut être supprimée si inutilisée)
     */
    public function cautionList()
    {
        return view('admin.caution.caution_list');
    }

    /**
     * Afficher le formulaire d'ajout d'un agent
     */
    public function addAgent()
    {
        return view('admin.agents.add_agent');
    }

    /**
     * Enregistrer un nouvel agent
     */
    public function storeagent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "agent_name" => 'required',
            "phone" => 'required',
            "identity_card_file" => 'required',
            "location_plan_file" => 'required',
            "photo" => 'required|image',
            "vehicule" => 'nullable|in:moto,voiture',
        ]);

        if ($validator->fails()) {
            Session::flash("danger", "Le nom, le numéro de téléphone, la carte nationale d'identité, le plan de localisation et la photo sont obligatoires !");
            return back()->with('error', "Erreur de validation des données !");
        }

        $agent_photo = $request->file('photo');
        $agent_photo_name = hexdec(uniqid()) . '.' . $agent_photo->extension();
        $request->file('photo')->move(public_path('upload'), $agent_photo_name);
        $agent_photo_url = 'upload/' . $agent_photo_name;

        $identity_card_file = $request->file('identity_card_file');
        $identity_card_file_name = hexdec(uniqid()) . '.' . $identity_card_file->extension();
        $request->file('identity_card_file')->move(public_path('upload'), $identity_card_file_name);
        $identity_card_file_url = 'upload/' . $identity_card_file_name;

        $location_plan_file = $request->file('location_plan_file');
        $location_plan_file_name = hexdec(uniqid()) . '.' . $location_plan_file->extension();
        $request->file('location_plan_file')->move(public_path('upload'), $location_plan_file_name);
        $location_plan_file_url = 'upload/' . $location_plan_file_name;

        $agent = Agent::create([
            'id_user' => uniqid(),
            'agent_name' => $request->agent_name,
            'phone' => $request->phone,
            'national_identity_card_number' => $request->national_identity_card_number,
            'photo' => $agent_photo_url,
            'identity_card_file' => $identity_card_file_url,
            'location_plan_file' => $location_plan_file_url,
            'registration_number' => "POULET AFC",
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'type' => $request->type,
            'vehicule' => $request->vehicule,
            'matricule_vehicule' => $request->matricule_vehicule,
        ]);

        $ref = sprintf('%s%s%s%s%s', 'POULET-AGENT', $agent->id, now()->format('Y'), now()->format('m'), now()->format('d'));

        $agent->update([
            'registration_number' => $ref,
        ]);

        return redirect()->route('agentlist')->with('message', 'Agent ajouté avec succès!');
    }

    /**
     * Modifier un agent
     */
    public function editAgent(Request $request)
    {
      
        try {
            $id = $request->id;
            $agent = Agent::findOrFail($id);

            $updateData = [
                'agent_name' => $request->agent_name,
                'phone' => $request->phone,
                'national_identity_card_number' => $request->national_identity_card_number,
                'registration_number' => $request->registration_number,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'type' => $request->type,
                'vehicule' => $request->vehicule,
                'matricule_vehicule' => $request->matricule_vehicule,
            ];

            // Gestion des fichiers
            if ($request->hasFile('photo')) {
                $agent_photo = $request->file('photo');
                $agent_photo_name = hexdec(uniqid()) . '.' . $agent_photo->extension();
                $request->file('photo')->move(public_path('upload'), $agent_photo_name);
                $updateData['photo'] = 'upload/' . $agent_photo_name;
            }

            if ($request->hasFile('identity_card_file')) {
                $identity_card_file = $request->file('identity_card_file');
                $identity_card_file_name = hexdec(uniqid()) . '.' . $identity_card_file->extension();
                $request->file('identity_card_file')->move(public_path('upload'), $identity_card_file_name);
                $updateData['identity_card_file'] = 'upload/' . $identity_card_file_name;
            }

            if ($request->hasFile('location_plan_file')) {
                $location_plan_file = $request->file('location_plan_file');
                $location_plan_file_name = hexdec(uniqid()) . '.' . $location_plan_file->extension();
                $request->file('location_plan_file')->move(public_path('upload'), $location_plan_file_name);
                $updateData['location_plan_file'] = 'upload/' . $location_plan_file_name;
            }

            $agent->update($updateData);

            return back()->with('message', 'Agent modifié avec succès');
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la modification de l\'agent : ' . $e->getMessage());
        }
    }

    /**
     * Supprimer un agent
     */
    public function deleteAgent(Request $request)
    {
        $request->validate([
            "id" => 'required',
        ]);

        try {
            $id = $request->id;
            $agent = Agent::findOrFail($id);
            $agent->delete();

            return back()->with('message', 'Agent supprimé avec succès');
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la suppression de l\'agent : ' . $e->getMessage());
        }
    }

    /**
     * Créditer un agent
     */
    public function creditAgent(Request $request)
    {
        $request->validate([
            'id_user' => 'required',
            'amount' => 'required|numeric|min:0',
            'password' => 'required',
        ]);

        try {
            // Vérifier si l'utilisateur est admin
            if (Auth::user()->role != "admin") {
                return back()->with('error', 'Seul un administrateur peut créditer un agent.');
            }

            // Vérifier le mot de passe
            if (!Auth::attempt(['email' => Auth::user()->email, 'password' => $request->password])) {
                return back()->with('error', 'Mot de passe incorrect.');
            }

            $agent = Agent::where('id_user', $request->id_user)->firstOrFail();

            // Le solde et le total crédité sont des accesseurs calculés
            // (Agent::getBalanceAttribute() / getTotalCreditedAttribute()),
            // pas des colonnes qu'on incrémente : les deux lignes qui
            // faisaient "$agent->balance += ..." lisaient la valeur déjà
            // calculée puis l'écrivaient dans la colonne brute sans passer
            // par aucun mutateur, corrompant un peu plus cette colonne à
            // chaque crédit sans que rien ne la relise jamais correctement.
            // Insérer la ligne credit_agents ci-dessous suffit : c'est déjà
            // ce que Fonction::solde() additionne pour recalculer le solde.

            // Enregistrer l'historique du crédit
            CreditAgent::create([
                'id_user' => Auth::user()->id,
                'id_agent' => $request->id_user,
                'amount' => $request->amount,
                'created_at' => now(),
            ]);

            return back()->with('message', 'Compte de l\'agent crédité avec succès');
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors du crédit de l\'agent : ' . $e->getMessage());
        }
    }

    /**
     * Afficher l'historique des crédits d'un agent
     */
    public function creditHistory($id_user)
    {
        $agent = Agent::where('id_user', $id_user)->firstOrFail();
        $credits = CreditAgent::where('id_agent', $id_user)->latest()->paginate(10);

        return view('admin.agents.credit_history', compact('agent', 'credits'));
    }

    /**
     * Changer le statut d'un agent
     */
    public function changeStatus($id, $base, $status)
    {
        $agent = Agent::findOrFail($id);
        $agent->status = $status;
        $agent->save();

        $message = $status === 'Success' ? 'Agent activé avec succès' : 'Agent désactivé avec succès';
        return redirect()->route('agentlist')->with('message', $message);
    }
}