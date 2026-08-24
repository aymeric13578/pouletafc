<?php
use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\Agent;
use App\Models\User;
use App\Models\Clando;
use App\Models\order_detail;
use App\Models\CreditAgent;
use App\Models\Deposit;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

name('dashboard.agents');

new class extends Component {
    use WithFileUploads;

    public $search = '';

    /** Affiche ou masque le bloc de partage de l'application agent. */
    public $partageOuvert = false;

    /**
     * Ce qu'il faut pour partager l'application agent.
     *
     * L'application n'est sur aucun magasin et ne figure sur aucune page
     * publique : elle ne s'adresse pas aux clients. Sans lien à envoyer,
     * recruter un livreur supposait de lui transmettre un fichier de cent
     * mégaoctets par WhatsApp.
     */
    public function getApplicationProperty(): array
    {
        return app(\App\Services\MobileAppService::class)->agent();
    }

    public function basculerPartage(): void
    {
        $this->partageOuvert = ! $this->partageOuvert;
    }
    public $showModal = false;
    public $showFileModal = false;
    public $showCreditModal = false;
    public $editMode = false;
    public $agentId = null;
    public $creditAgentId = null;
    public $fileUrl = '';
    public $fileType = '';
    public $id_user = '';
    public $agent_name = '';
    public $phone = '';
    public $ref = '';
    public $national_identity_card_number = '';
    public $location_plan_file = null;
    public $identity_card_file = null;
    public $photo = null;
    public $contrat = null;
    public $city = '';
    public $type = '';
    public $vehicule = '';
    public $matricule_vehicule = '';
    public $creditAmount = '';
    public $password = '';

    public function getAgentsProperty()
    {
        return Agent::when($this->search, function ($q) {
            $q->where('agent_name', 'like', '%' . $this->search . '%')
              ->orWhere('phone', 'like', '%' . $this->search . '%')
              ->orWhere('ref', 'like', '%' . $this->search . '%');
        })
            ->orderBy('id', 'desc')
            ->with('user')
            ->get();
    }

    public function getTotalAgentsProperty()
    {
        return Agent::count();
    }

    public function getActiveAgentsProperty()
    {
        return Agent::where('status', 'Success')->count();
    }

    public function getWaitingAgentsProperty()
    {
        return Agent::where('status', 'pending')->count();
    }

    public function getTotalBalanceProperty()
    {
        return Agent::sum('balance');
    }

    public function getAgentUsersProperty()
    {
        if ($this->editMode && $this->agentId) {
            $currentAgent = Agent::find($this->agentId);
            $excludeIds = Agent::where('id', '!=', $this->agentId)->pluck('id_user')->toArray();
            return User::whereNotIn('id', $excludeIds)
                ->orWhere('id', $currentAgent ? $currentAgent->id_user : null)
                ->where('role', '!=', 'agent')
                ->get();
        }
        return User::whereNotIn('id', Agent::pluck('id_user'))
            ->where('role', '!=', 'agent')
            ->get();
    }

    public function getAgentBalance($id)
    {
        $totalEarnClando = Clando::where('id_agent', $id)->where('status', 'Success')->sum('price') ?? 0;
        $totalEarnCommand = order_detail::where('id_agent', $id)->where('status', 'Success')->sum('price') ?? 0;
        $totalCredit = CreditAgent::where('id_agent', $id)->sum('amount') ?? 0;
        $totalDeposit = Deposit::where('id_agent', $id)->where('status', 'Success')->sum('amount') ?? 0;

        return $totalDeposit + $totalCredit - $totalEarnClando - $totalEarnCommand;
    }

    public function updatedIdUser($value)
    {
        if ($value) {
            $user = User::find($value);
            if ($user) {
                $this->agent_name = $user->name;
                $this->phone = $user->phone;
            } else {
                $this->agent_name = '';
                $this->phone = '';
            }
        } else {
            $this->agent_name = '';
            $this->phone = '';
        }
    }

    /**
     * Ferme toutes les fenêtres avant d'en ouvrir une.
     *
     * Les trois calques sont empilés au même endroit et ne se fermaient qu'à
     * leur propre bouton. Il suffisait qu'un reste ouvert pour qu'en ouvrir un
     * second les superpose : c'est celui qui vient le plus bas dans la page qui
     * s'affiche alors, et non celui qu'on vient de demander.
     */
    private function fermerLesFenetres(): void
    {
        $this->showModal = false;
        $this->showFileModal = false;
        $this->showCreditModal = false;
    }

    public function openModal($mode = 'add', $id = null)
    {
        $this->fermerLesFenetres();

        $this->resetForm();
        $this->editMode = ($mode === 'edit');
        if ($this->editMode && $id) {
            $agent = Agent::findOrFail($id);
            $this->agentId = $agent->id;
            $this->id_user = $agent->id_user;
            $this->agent_name = $agent->agent_name;
            $this->phone = $agent->phone;
            $this->ref = $agent->ref;
            $this->national_identity_card_number = $agent->national_identity_card_number;
            $this->city = $agent->city;
            $this->type = $agent->type;
            $this->vehicule = $agent->vehicule;
            $this->matricule_vehicule = $agent->matricule_vehicule;
        } else {
            $this->city = 'yaounde';
            $this->type = 'classic';
            $this->vehicule = 'moto';
        }
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function openFileModal($fileUrl, $fileType)
    {
        $this->fermerLesFenetres();
        $this->fileUrl = $fileUrl;
        $this->fileType = $fileType;
        $this->showFileModal = true;
    }

    public function closeFileModal()
    {
        $this->showFileModal = false;
        $this->fileUrl = '';
        $this->fileType = '';
    }

    public function openCreditModal($id)
    {
        $this->fermerLesFenetres();
        $this->creditAgentId = $id;
        $this->creditAmount = '';
        $this->password = '';
        $this->showCreditModal = true;
        $this->resetValidation(['creditAmount', 'password']);
    }

    public function closeCreditModal()
    {
        $this->showCreditModal = false;
        $this->creditAgentId = null;
        $this->creditAmount = '';
        $this->password = '';
        $this->resetValidation(['creditAmount', 'password']);
    }

    public function resetForm()
    {
        $this->editMode = false;
        $this->agentId = null;
        $this->id_user = '';
        $this->agent_name = '';
        $this->phone = '';
        $this->ref = '';
        $this->national_identity_card_number = '';
        $this->location_plan_file = null;
        $this->identity_card_file = null;
        $this->photo = null;
        $this->contrat = null;
        $this->city = '';
        $this->type = '';
        $this->vehicule = '';
        $this->matricule_vehicule = '';
        $this->resetValidation();
    }

    public function toggleAgentStatus($id)
    {
        $agent = Agent::findOrFail($id);
        $agent->status = $agent->status === 'Success' ? 'failed' : 'Success';
        $agent->save();
        $this->dispatch('notify', ['message' => 'Statut de l\'agent modifié avec succès !', 'type' => 'success']);
    }

    public function creditAgent()
    {
        $this->validate([
            'creditAmount' => 'required|numeric|min:0',
            'password' => 'required',
        ]);

        if (Auth::user()->role !== 'admin') {
            $this->dispatch('notify', ['message' => 'Seul un administrateur peut créditer un agent.', 'type' => 'error']);
            return;
        }

        if (!Hash::check($this->password, Auth::user()->password)) {
            $this->dispatch('notify', ['message' => 'Mot de passe incorrect.', 'type' => 'error']);
            return;
        }

        try {
            $agent = Agent::where('id_user', $this->creditAgentId)->firstOrFail();
            $agent->balance += $this->creditAmount;
            $agent->total_credited += $this->creditAmount;
            $agent->save();

            CreditAgent::create([
                'id_user' => Auth::user()->id,
                'id_agent' => $this->creditAgentId,
                'amount' => $this->creditAmount,
                'created_at' => now(),
            ]);

            $this->dispatch('notify', ['message' => 'Compte de l\'agent crédité avec succès !', 'type' => 'success']);
            $this->closeCreditModal();
        } catch (\Exception $e) {
            $this->dispatch('notify', ['message' => 'Erreur lors du crédit de l\'agent : ' . $e->getMessage(), 'type' => 'error']);
        }
    }

    public function saveAgent()
    {
        $this->validate([
            'id_user' => 'required|exists:users,id|unique:agents,id_user' . ($this->editMode ? ',' . $this->agentId : ''),
            'agent_name' => 'required|string|max:191',
            'phone' => 'required|string|max:191',
            'ref' => 'required|string|max:255|unique:agents,ref' . ($this->editMode ? ',' . $this->agentId : ''),
            'national_identity_card_number' => 'nullable|string|max:191',
            'location_plan_file' => 'nullable|file|mimes:pdf,jpg,png|max:2048',
            'identity_card_file' => 'nullable|file|mimes:pdf,jpg,png|max:2048',
            'photo' => 'nullable|file|mimes:jpg,png|max:2048',
            'contrat' => 'nullable|file|mimes:pdf|max:2048',
            'city' => 'required|string|max:255',
            'type' => 'required|in:classic,vip',
            'vehicule' => 'required|in:moto,voiture',
            'matricule_vehicule' => 'nullable|string|max:255',
        ]);

        $data = [
            'id_user' => $this->id_user,
            'agent_name' => $this->agent_name,
            'phone' => $this->phone,
            'ref' => $this->editMode ? $this->ref : 'REF_' . Str::random(10),
            'national_identity_card_number' => $this->national_identity_card_number,
            'city' => $this->city,
            'type' => $this->type,
            'vehicule' => $this->vehicule,
            'matricule_vehicule' => $this->matricule_vehicule,
        ];

        // Statut "pending" uniquement à la création : sur une modification, ce
        // champ écrasait silencieusement le statut existant, désactivant un
        // agent "Actif" au moindre changement d'un autre champ (téléphone,
        // véhicule...) sans que personne ne le décide ni ne le voie.
        if (! $this->editMode) {
            $data['status'] = 'pending';
        }

        /*
         | Les 4 fichiers écrivaient via le disque "public" de
         | config/filesystems.php, dont la racine (storage_path('../../pouletafc'))
         | résout à la racine du projet — ni servie par le serveur web, ni
         | forcément inscriptible par l'utilisateur PHP sur cet hébergement.
         | L'écriture échouait et provoquait l'erreur 500 au moindre fichier
         | joint. public_path('upload') est le seul chemin dont ce projet
         | garantit qu'il est à la fois servi et préservé à chaque déploiement
         | (voir .github/deploy-release.sh) — c'est déjà la convention utilisée
         | par CoursierController et l'ancien AgentController.
         |
         | ->move() (hérité de Symfony\UploadedFile) appelle en interne
         | move_uploaded_file(), qui exige que le fichier vienne d'un vrai
         | upload HTTP synchrone de LA requête courante. Un champ
         | wire:model="photo" upload le fichier de façon asynchrone lors
         | d'une requête précédente (/livewire/upload-file) : au moment de
         | saveAgent(), ce n'est déjà plus "cette requête-ci", donc ->move()
         | échoue systématiquement (pas seulement en local) avec "Could not
         | move the file". rename() sur le vrai chemin temporaire
         | (getRealPath()) n'a pas cette contrainte et fonctionne pour un
         | fichier Livewire comme pour un upload classique.
         |
         | ->extension() (pas ->getClientOriginalExtension()) : la règle
         | "mimes:..." ne valide que le CONTENU du fichier, pas le nom que le
         | client a choisi de lui donner. getClientOriginalExtension() reste
         | donc entièrement contrôlé par l'attaquant — un PNG valide nommé
         | "x.php" passait la validation puis était enregistré sous
         | "xxxx.php" dans public/upload, exécutable par le serveur web
         | (aucun .htaccess n'y désactive PHP). ->extension() déduit
         | l'extension du contenu réellement détecté (déjà ce que "mimes"
         | vérifie), donc le nom de fichier ne peut plus sortir de
         | jpg/png/pdf selon le champ.
         */
        if ($this->location_plan_file) {
            $nom = hexdec(uniqid()) . '.' . $this->location_plan_file->extension();
            rename($this->location_plan_file->getRealPath(), public_path('upload') . DIRECTORY_SEPARATOR . $nom);
            $data['location_plan_file'] = 'upload/' . $nom;
        }
        if ($this->identity_card_file) {
            $nom = hexdec(uniqid()) . '.' . $this->identity_card_file->extension();
            rename($this->identity_card_file->getRealPath(), public_path('upload') . DIRECTORY_SEPARATOR . $nom);
            $data['identity_card_file'] = 'upload/' . $nom;
        }
        if ($this->photo) {
            $nom = hexdec(uniqid()) . '.' . $this->photo->extension();
            rename($this->photo->getRealPath(), public_path('upload') . DIRECTORY_SEPARATOR . $nom);
            $data['photo'] = 'upload/' . $nom;
        }
        if ($this->contrat) {
            $nom = hexdec(uniqid()) . '.' . $this->contrat->extension();
            rename($this->contrat->getRealPath(), public_path('upload') . DIRECTORY_SEPARATOR . $nom);
            $data['contrat'] = 'upload/' . $nom;
        }

        if ($this->editMode) {
            $agent = Agent::findOrFail($this->agentId);
            $agent->update($data);
            $this->dispatch('notify', ['message' => 'Agent modifié avec succès !', 'type' => 'success']);
        } else {
            Agent::create($data);
            $this->dispatch('notify', ['message' => 'Agent ajouté avec succès !', 'type' => 'success']);
        }

        $this->closeModal();
    }

    public function deleteAgent($id)
    {
        $agent = Agent::findOrFail($id);
        $agent->delete();
        $this->dispatch('notify', ['message' => 'Agent supprimé avec succès !', 'type' => 'success']);
    }
};
?>

<x-layouts.app>
    @volt
        <div class="container mx-auto px-2 mt-6">

            <!-- Barre de recherche -->
            <form class="flex items-center max-w-lg mx-auto mb-6">
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative w-full">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 21">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.15 5.6h.01m3.337 1.913h.01m-6.979 0h.01M5.541 11h.01M15 15h2.706a1.957 1.957 0 0 0 1.883-1.325A9 9 0 1 0 2.043 11.89 9.1 9.1 0 0 0 7.2 19.1a8.62 8.62 0 0 0 3.769.9A2.013 2.013 0 0 0 13 18v-.857A2.034 2.034 0 0 1 15 15Z" />
                        </svg>
                    </div>
                    <input type="text" id="search" wire:model.live="search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Rechercher un agent" />
                </div>
                <button type="submit" class="inline-flex items-center py-2.5 px-3 ms-2 text-sm font-medium text-white bg-blue-700 rounded-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    <svg class="w-4 h-4 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                    </svg>Rechercher
                </button>
            </form>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Agents</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->totalAgents }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Agents Actifs</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->activeAgents }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Agents en Attente</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->waitingAgents }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Solde Total</h3>
                    <p class="text-3xl font-bold {{ $this->totalBalance >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($this->totalBalance, 2) }} FCFA</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-wrap justify-end gap-3 mb-4">
                <button type="button" wire:click="basculerPartage"
                        class="border border-indigo-600 text-indigo-700 bg-white py-2 px-6 rounded-lg hover:bg-indigo-50 transition duration-300">
                    {{ $partageOuvert ? "Masquer le partage" : "Partager l'application" }}
                </button>

                <button wire:click="openModal" class="bg-indigo-600 text-white py-2 px-6 rounded-lg hover:bg-indigo-700 transition duration-300">Ajouter Agent</button>
            </div>

            {{--
              | Partage de l'application agent.
              |
              | Déplié sous les boutons plutôt que dans une fenêtre : l'écran en
              | compte déjà trois, empilées au même endroit, et en ajouter une
              | quatrième pour afficher un lien à recopier n'apporterait qu'un
              | risque de confusion supplémentaire.
            --}}
            @if ($partageOuvert)
                <div wire:key="partage-application" class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-gray-900">Application agent</h2>
                            <p class="mt-1 text-xs text-gray-500">
                                @if ($this->application['apk_available'])
                                    Version en ligne{{ $this->application['apk_size'] ? ' · ' . $this->application['apk_size'] : '' }}{{ $this->application['apk_updated_at'] ? ' · mise en ligne le ' . $this->application['apk_updated_at'] : '' }}
                                @else
                                    Aucun fichier n'est encore déposé sur le serveur.
                                @endif
                            </p>
                        </div>
                    </div>

                    @php $lien = $this->application['apk_url']; @endphp

                    <div class="mt-4 border-t border-gray-100 pt-4">
                        @if ($this->application['apk_available'])
                            <p class="text-xs font-semibold text-gray-700">Lien à envoyer à l'agent</p>

                            {{--
                              | Champ en lecture seule plutôt qu'un simple texte : sur
                              | téléphone, un appui long ne sélectionne pas proprement un
                              | lien affiché en clair, et l'agent le recopie de travers.
                            --}}
                            <div class="mt-2 flex flex-wrap items-center gap-2" x-data="{ copie: false }">
                                <input type="text" readonly value="{{ $lien }}" onclick="this.select()"
                                       class="min-w-0 flex-1 rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-800" />

                                <button type="button"
                                        x-on:click="navigator.clipboard.writeText('{{ $lien }}'); copie = true; setTimeout(() => copie = false, 2000)"
                                        class="rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-700">
                                    <span x-show="!copie">Copier</span>
                                    <span x-show="copie" x-cloak>Copié !</span>
                                </button>

                                <a href="https://wa.me/?text={{ urlencode("Bonjour, voici l'application agent Poulet AFC à installer : " . $lien) }}"
                                   target="_blank" rel="noopener"
                                   class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                                    Envoyer par WhatsApp
                                </a>

                                <a href="{{ $lien }}"
                                   class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    Télécharger
                                </a>
                            </div>

                            <p class="mt-3 text-xs text-gray-500">
                                L'agent devra autoriser l'installation depuis une source inconnue :
                                Android bloque par défaut les applications qui ne viennent pas du Play Store.
                            </p>
                        @else
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                                <p class="text-xs font-bold text-amber-900">Fichier absent</p>
                                <p class="mt-1 text-xs text-amber-800">
                                    Déposez l'APK sur le serveur à cet emplacement exact, puis rechargez cette page :
                                </p>
                                <p class="mt-2 break-all rounded bg-white px-3 py-2 font-mono text-xs text-gray-800">
                                    {{ $this->application['apk_path'] }}
                                </p>
                                <p class="mt-2 text-xs text-amber-800">
                                    Ce dossier est conservé d'une mise en production à l'autre : le fichier
                                    n'a donc à être déposé qu'une fois, puis remplacé à chaque nouvelle version.
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Tableau -->
            <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b">
                            <th class="py-3 px-4 text-gray-800">ID</th>
                            <th class="py-3 px-4 text-gray-800">Référence</th>
                            <th class="py-3 px-4 text-gray-800">Nom</th>
                            <th class="py-3 px-4 text-gray-800">Téléphone</th>
                            <th class="py-3 px-4 text-gray-800">Ville</th>
                            <th class="py-3 px-4 text-gray-800">Type</th>
                            <th class="py-3 px-4 text-gray-800">Véhicule</th>

                            <th class="py-3 px-4 text-gray-800">Solde</th>
                            <th class="py-3 px-4 text-gray-800">Statut</th>
                            <th class="py-3 px-4 text-gray-800">Documents</th>
                            <th class="py-3 px-4 text-gray-800">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->agents as $agent)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4">{{ $agent->id }}</td>
                                <td class="py-3 px-4">{{ $agent->ref }}</td>
                                <td class="py-3 px-4">{{ $agent->agent_name }}</td>
                                <td class="py-3 px-4">{{ $agent->phone }}</td>
                                <td class="py-3 px-4">{{ $agent->city }}</td>
                                <td class="py-3 px-4">{{ $agent->type === 'classic' ? 'Classique' : 'VIP' }}</td>
                                <td class="py-3 px-4">{{ $agent->vehicule === 'moto' ? 'Moto' : 'Voiture' }}</td>
                            
                                <td class="py-3 px-4">
                                    <span class="{{ $this->getAgentBalance($agent->id_user) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ number_format($this->getAgentBalance($agent->id_user), 2) }} FCFA
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $agent->status === 'Success' ? 'bg-green-100 text-green-800' : ($agent->status === 'failed' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                                        {{ $agent->status === 'Success' ? 'Actif' : ($agent->status === 'failed' ? 'Désactivé' : 'En attente') }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    @if ($agent->location_plan_file)
                                        <button wire:click="openFileModal('{{ url($agent->location_plan_file) }}', 'document')" class="text-blue-600 hover:text-blue-800 text-xs mr-2">Voir Plan</button>
                                    @endif
                                    @if ($agent->identity_card_file)
                                        <button wire:click="openFileModal('{{ url($agent->identity_card_file) }}', 'document')" class="text-blue-600 hover:text-blue-800 text-xs mr-2">Voir CNI</button>
                                    @endif
                                    @if ($agent->photo)
                                        <button wire:click="openFileModal('{{ url($agent->photo) }}', 'image')" class="text-blue-600 hover:text-blue-800 text-xs mr-2">Voir Photo</button>
                                    @endif
                                    @if ($agent->contrat)
                                        <button wire:click="openFileModal('{{ url($agent->contrat) }}', 'document')" class="text-blue-600 hover:text-blue-800 text-xs">Voir Contrat</button>
                                    @endif
                                </td>
                                <td class="py-3 px-4">
                                    <button wire:click="openModal('edit', {{ $agent->id }})" class="text-indigo-600 hover:text-indigo-800 mr-2" title="Modifier">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                    <button wire:click="deleteAgent({{ $agent->id }})" onclick="return confirm('Supprimer cet agent ?')" class="text-red-600 hover:text-red-800 mr-2" title="Supprimer">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                    <button wire:click="toggleAgentStatus({{ $agent->id }})" class="text-gray-600 hover:text-gray-800 mr-2" title="{{ $agent->status === 'Success' ? 'Désactiver' : 'Activer' }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            @if ($agent->status === 'Success')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            @endif
                                        </svg>
                                    </button>
                                    <button wire:click="openCreditModal({{ $agent->id_user }})" class="text-green-600 hover:text-green-800" title="Créditer l'agent">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2m-2 4V9m2 0v6m2-6v6" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Modal d'ajout/modification -->
            <div wire:key="fenetre-agent" class="fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-4xl h-auto max-h-[75vh] overflow-y-auto">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">{{ $editMode ? 'Modifier un Agent' : 'Ajouter un Agent' }}</h2>
                    <form id="agentForm" wire:submit.prevent="saveAgent" class="space-y-4">
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="id_user">Utilisateur <span class="text-red-500">*</span></label>
                                <select id="id_user" wire:model="id_user" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                    <option value="">Sélectionner</option>
                                    @foreach ($this->agentUsers as $user)
                                        <option value="{{ $user->id }}" @selected($id_user == $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                                    @endforeach
                                </select>
                                @error('id_user') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="national_identity_card_number">Numéro de carte d'identité</label>
                                <input type="text" id="national_identity_card_number" wire:model="national_identity_card_number" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                @error('national_identity_card_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="location_plan_file">Plan de localisation</label>
                                <input type="file" id="location_plan_file" wire:model="location_plan_file" class="w-full p-1 text-sm border border-gray-300 rounded-lg">
                                @error('location_plan_file') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="identity_card_file">Carte d'identité</label>
                                <input type="file" id="identity_card_file" wire:model="identity_card_file" class="w-full p-1 text-sm border border-gray-300 rounded-lg">
                                @error('identity_card_file') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="photo">Photo</label>
                                <input type="file" id="photo" wire:model="photo" class="w-full p-1 text-sm border border-gray-300 rounded-lg">
                                @error('photo') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="contrat">Contrat</label>
                                <input type="file" id="contrat" wire:model="contrat" class="w-full p-1 text-sm border border-gray-300 rounded-lg">
                                @error('contrat') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="city">Ville <span class="text-red-500">*</span></label>
                                <input type="text" id="city" wire:model="city" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                @error('city') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="type">Type <span class="text-red-500">*</span></label>
                                <select id="type" wire:model="type" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                    <option value="">Sélectionner</option>
                                    <option value="classic">Classique</option>
                                    <option value="vip">VIP</option>
                                </select>
                                @error('type') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="vehicule">Véhicule <span class="text-red-500">*</span></label>
                                <select id="vehicule" wire:model="vehicule" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                    <option value="">Sélectionner</option>
                                    <option value="moto">Moto</option>
                                    <option value="voiture">Voiture</option>
                                </select>
                                @error('vehicule') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="matricule_vehicule">Matricule véhicule</label>
                                <input type="text" id="matricule_vehicule" wire:model="matricule_vehicule" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                @error('matricule_vehicule') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeModal" class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                            <button type="submit" form="agentForm" class="bg-indigo-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-indigo-700">{{ $editMode ? 'Modifier' : 'Ajouter' }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal pour afficher les fichiers -->
            <div wire:key="fenetre-fichier" class="fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showFileModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-4xl h-auto max-h-[75vh] overflow-y-auto">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Aperçu {{ $fileType === 'image' ? 'de l\'image' : 'du document' }}</h2>
                    <div class="flex justify-center">
                        @if ($fileType === 'image')
                            <img src="{{ $fileUrl }}" alt="Image" class="max-w-full max-h-[60vh] object-contain">
                        @else
                            <embed src="{{ $fileUrl }}" type="application/pdf" class="w-full h-[60vh]">
                        @endif
                    </div>
                    <div class="flex justify-end mt-4">
                        <button wire:click="closeFileModal" class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600">Fermer</button>
                    </div>
                </div>
            </div>

            <!-- Modal pour créditer un agent -->
            <div wire:key="fenetre-credit" class="fixed inset-0 z-50 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showCreditModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Créditer un Agent</h2>
                    <form id="creditForm" wire:submit.prevent="creditAgent" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm mb-1" for="creditAmount">Montant <span class="text-red-500">*</span></label>
                            <input type="number" id="creditAmount" wire:model="creditAmount" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                            @error('creditAmount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm mb-1" for="password">Mot de passe <span class="text-red-500">*</span></label>
                            <input type="password" id="password" wire:model="password" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                            @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeCreditModal" class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                            <button type="submit" form="creditForm" class="bg-indigo-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-indigo-700">Créditer</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Toastr -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
            <script>
                toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 3000 };
            </script>
        </div>
    @endvolt
</x-layouts.app>
