<?php
use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Agent;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

name('dashboard.users');

new class extends Component {
    public $search = '';
    public $showModal = false;
    public $showRoleModal = false;
    public $editMode = false;
    public $userId = null;
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $role = '';
    public $city = '';
    public $phone = '';
    public $country_code = '';
    public $sexe = '';
    public $whatsapp = '';
    public $selectedRole = '';
    public $showImagePopup = false;
    public $currentImage = '';

    public function getUsersProperty()
    {
        return User::when($this->search, function ($q) {
            $q->where('name', 'like', '%' . $this->search . '%')
              ->orWhere('email', 'like', '%' . $this->search . '%');
        })
            ->orderBy('id', 'desc')
            ->get();
    }

    public function getTotalUsersProperty()
    {
        return User::count();
    }

    public function getActiveUsersProperty()
    {
        return User::where('status', 'Success')->count();
    }

    public function getAdminUsersProperty()
    {
        return User::where('role', 'admin')->count();
    }

    public function openModal($mode = 'add', $id = null)
    {
        $this->resetForm();
        $this->editMode = $mode === 'edit';
        if ($this->editMode && $id) {
            $user = User::findOrFail($id);
            $this->userId = $user->id;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = $user->role;
            $this->city = $user->city;
            $this->phone = $user->phone;
            $this->country_code = $user->country_code;
            $this->sexe = $user->sexe;
            $this->whatsapp = $user->whatsapp;
        }
        $this->showModal = true;
    }

    public function openRoleModal($id)
    {
        $this->userId = $id;
        $user = User::findOrFail($id);
        $this->selectedRole = $user->role;
        $this->showRoleModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function closeRoleModal()
    {
        $this->showRoleModal = false;
        $this->userId = null;
        $this->selectedRole = '';
    }

    public function resetForm()
    {
        $this->editMode = false;
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->role = '';
        $this->city = '';
        $this->phone = '';
        $this->country_code = '';
        $this->sexe = '';
        $this->whatsapp = '';
        $this->resetValidation();
    }

    public function saveUser()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($this->editMode ? ',' . $this->userId : ''),
            // Minimum ramené à 4 caractères sur demande : les comptes d'agents et
            // d'opérateurs sont saisis sur le terrain, au clavier d'un téléphone.
            'password' => $this->editMode ? 'nullable|string|min:4|confirmed' : 'required|string|min:4|confirmed',
            'password_confirmation' => $this->editMode ? 'nullable|string|min:4' : 'required|string|min:4',
            'role' => 'nullable|in:user,agent,admin,merchand,employee_afc',
            'city' => 'nullable|string|max:255',
            'phone' => 'nullable|numeric|digits_between:1,15',
            'country_code' => 'nullable|string|size:3',
            'sexe' => 'nullable|in:H,F',
            'whatsapp' => 'nullable|numeric|digits_between:1,15',
        ]);

        // Même garde-fou que changeRole() : ce formulaire ne doit pas
        // permettre à un employee_afc de créer ou de promouvoir un compte
        // administrateur.
        abort_unless(
            $this->role !== 'admin' || Auth::user()?->role === 'admin',
            403,
            "Seul un administrateur peut accorder le rôle administrateur."
        );

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role ?: 'user',
            'city' => $this->city,
            'phone' => $this->phone,
            'country_code' => $this->country_code,
            'sexe' => $this->sexe,
            'whatsapp' => $this->whatsapp,
        ];
        if (!empty($this->password)) {
            $data['password'] = bcrypt($this->password);
        }

        if ($this->editMode) {
            $user = User::findOrFail($this->userId);
            $user->update($data);
            if ($data['role'] === 'agent' && !Agent::where('id_user', $this->userId)->exists()) {
                Agent::create([
                    'id_user' => $this->userId,
                    'ref' => $user->ref,
                    'agent_name' => $this->name,
                    'phone' => $this->phone,
                    'status' => 'pending',
                    'city' => $this->city ?: 'yaounde',
                    'type' => 'classic',
                    'vehicule' => 'moto',
                    'in_activity' => 0,
                    'freeStatus' => 0,
                ]);
            }
            $this->dispatch('notify', ['message' => 'Utilisateur modifié avec succès !', 'type' => 'success']);
        } else {
            $user = User::create($data);
            if ($data['role'] === 'agent') {
                Agent::create([
                    'id_user' => $user->id,
                    'ref' => $user->ref,
                    'agent_name' => $this->name,
                    'phone' => $this->phone,
                    'status' => 'pending',
                    'city' => $this->city ?: 'yaounde',
                    'type' => 'classic',
                    'vehicule' => 'moto',
                    'in_activity' => 0,
                    'freeStatus' => 0,
                ]);
            }
            $this->dispatch('notify', ['message' => 'Utilisateur ajouté avec succès !', 'type' => 'success']);
        }

        $this->closeModal();
    }

    public function changeRole()
    {
        $this->validate([
            'selectedRole' => 'required|in:user,agent,admin,merchand,employee_afc',
        ]);

        /*
         | Cette page (dashboard.users) est accordable menu par menu à un
         | employee_afc via App\Support\MenuTableauDeBord — pour de la gestion
         | de compte courante, pas pour distribuer le rôle admin. Sans ce
         | garde-fou, quiconque reçoit ce seul menu pouvait se nommer, ou
         | nommer n'importe qui, administrateur : le rôle admin outrepasse
         | entièrement le système de droits (MenuTableauDeBord::autorise()).
         */
        abort_unless(
            $this->selectedRole !== 'admin' || Auth::user()?->role === 'admin',
            403,
            "Seul un administrateur peut accorder le rôle administrateur."
        );

        $user = User::findOrFail($this->userId);
        $user->update(['role' => $this->selectedRole]);

        if ($this->selectedRole === 'agent' && !Agent::where('id_user', $this->userId)->exists()) {
            Agent::create([
                'id_user' => $this->userId,
                'ref' => $user->ref,
                'agent_name' => $user->name,
                'phone' => $user->phone,
                'status' => 'pending',
                'city' => $user->city ?: 'yaounde',
                'type' => 'classic',
                'vehicule' => 'moto',
                'in_activity' => 0,
                'freeStatus' => 0,
            ]);
        }

        $this->dispatch('notify', ['message' => 'Rôle de l\'utilisateur modifié avec succès !', 'type' => 'success']);
        $this->closeRoleModal();
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        $this->dispatch('notify', ['message' => 'Utilisateur supprimé avec succès !', 'type' => 'success']);
    }

    public function toggleUserStatus($id)
    {
        $user = User::findOrFail($id);
        $user->status = $user->status === 'Success' ? 'Pending' : 'Success';
        $user->save();
        $this->dispatch('notify', ['message' => 'Statut de l\'utilisateur modifié avec succès !', 'type' => 'success']);
    }
};
?>

<x-layouts.app>
    @volt
        <div class="container mx-auto px-2 mt-6">
            <!-- Search Bar -->
            <form class="flex items-center max-w-lg mx-auto mb-6">
                <label for="search" class="sr-only">Search</label>
                <div class="relative w-full">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 21">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11.15 5.6h.01m3.337 1.913h.01m-6.979 0h.01M5.541 11h.01M15 15h2.706a1.957 1.957 0 0 0 1.883-1.325A9 9 0 1 0 2.043 11.89 9.1 9.1 0 0 0 7.2 19.1a8.62 8.62 0 0 0 3.769.9A2.013 2.013 0 0 0 13 18v-.857A2.034 2.034 0 0 1 15 15Z" />
                        </svg>
                    </div>
                    <input type="text" id="search" wire:model.live="search"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                        placeholder="Rechercher un utilisateur" />
                </div>
                <button type="submit"
                    class="inline-flex items-center py-2.5 px-3 ms-2 text-sm font-medium text-white bg-blue-700 rounded-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    <svg class="w-4 h-4 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                    </svg>Search
                </button>
            </form>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div
                    class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Utilisateurs</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->totalUsers }}</p>
                </div>
                <div
                    class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Utilisateurs Actifs</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->activeUsers }}</p>
                </div>
                <div
                    class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Rôles Admin</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->adminUsers }}</p>
                </div>
            </div>

            <!-- Add Button -->
            <div class="flex justify-end mb-4">
                <button wire:click="openModal('add')"
                    class="bg-indigo-600 text-white py-2 px-6 rounded-lg hover:bg-indigo-700 transition duration-300">Ajouter
                    Utilisateur</button>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b">

                            <th class="py-3 px-4 text-gray-800">Nom</th>
                            <th class="py-3 px-4 text-gray-800">Email</th>
                            <th class="py-3 px-4 text-gray-800">Rôle</th>

                            <th class="py-3 px-4 text-gray-800">Téléphone</th>

                            <th class="py-3 px-4 text-gray-800">WhatsApp</th>
                            <th class="py-3 px-4 text-gray-800">Statut</th>
                            <th class="py-3 px-4 text-gray-800">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->users as $user)
                            <tr class="border-b hover:bg-gray-50">
                            
                                <td class="py-3 px-4">{{ $user->name }}</td>
                                <td class="py-3 px-4">{{ $user->email }}</td>
                                <td class="py-3 px-4">{{ $user->role }}</td>

                                <td class="py-3 px-4">{{ $user->phone }}</td>

                                <td class="py-3 px-4">{{ $user->whatsapp }}</td>
                                <td class="py-3 px-4">
                                    <span class="{{ $user->status === 'Success' ? 'text-green-600' : 'text-yellow-600' }}">
                                        {{ $user->status === 'Success' ? 'Valide' : 'En attente de validation' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <button wire:click="openModal('edit', {{ $user->id }})"
                                        class="text-indigo-600 hover:text-indigo-800 mr-2" title="Modifier">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                            </path>
                                        </svg>
                                    </button>
                                    <button wire:click="openRoleModal({{ $user->id }})"
                                        class="text-blue-600 hover:text-blue-800 mr-2" title="Changer le rôle">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M17 20h5v-2a2 2 0 00-2-2h-3m-2 4H4a2 2 0 01-2-2V6a2 2 0 012-2h5m4 0h3a2 2 0 012 2v2m-6 2h6m-6 4h6" />
                                        </svg>
                                    </button>
                                    <button wire:click="toggleUserStatus({{ $user->id }})"
                                        class="text-{{ $user->status === 'Success' ? 'green' : 'yellow' }}-600 hover:text-{{ $user->status === 'Success' ? 'green' : 'yellow' }}-800 mr-2"
                                        title="{{ $user->status === 'Success' ? 'Désactiver' : 'Activer' }}">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="{{ $user->status === 'Success' ? 'M5 13l4 4L19 7' : 'M6 18L18 6M6 6l12 12' }}" />
                                        </svg>
                                    </button>
                                    <button wire:click="deleteUser({{ $user->id }})"
                                        onclick="return confirm('Supprimer cet utilisateur ?')"
                                        class="text-red-600 hover:text-red-800" title="Supprimer">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                            </path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Modal for Adding/Editing User -->
            <div wire:model="showModal"
                class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showModal ? '' : 'hidden' }}"
                x-data="{ showPassword: false, showConfirmPassword: false }">
                <div class="bg-white rounded-lg p-6 w-full max-w-4xl h-auto max-h-[75vh] overflow-y-auto">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">
                        {{ $editMode ? 'Modifier un Utilisateur' : 'Ajouter un Utilisateur' }}
                    </h2>
                    <form id="userForm" wire:submit.prevent="saveUser" class="space-y-4">
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="name">Nom <span class="text-red-500">*</span></label>
                                <input type="text" id="name" wire:model="name"
                                    class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                @error('name')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="email">Email <span class="text-red-500">*</span></label>
                                <input type="email" id="email" wire:model="email"
                                    class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                @error('email')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="password">Mot de passe
                                    {!! $editMode ? '(optionnel)' : '<span class="text-red-500">*</span>' !!}</label>
                                <div class="relative">
                                    <input :type="showPassword ? 'text' : 'password'" id="password" wire:model="password"
                                        class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" {{ $editMode ? '' : 'required' }}>
                                    <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-3"
                                        x-on:click="showPassword = !showPassword">
                                        <svg x-show="!showPassword" class="w-5 h-5 text-gray-500" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <svg x-show="showPassword" class="w-5 h-5 text-gray-500" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.02 10.02 0 01-4.132 5.411m0 0L21 21" />
                                        </svg>
                                    </button>
                                </div>
                                @error('password')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="password_confirmation">Confirmer le mot de passe
                                    {!! $editMode ? '(optionnel)' : '<span class="text-red-500">*</span>' !!}</label>
                                <div class="relative">
                                    <input :type="showConfirmPassword ? 'text' : 'password'" id="password_confirmation"
                                        wire:model="password_confirmation"
                                        class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" {{ $editMode ? '' : 'required' }}>
                                    <button type="button" class="absolute inset-y-0 end-0 flex items-center pe-3"
                                        x-on:click="showConfirmPassword = !showConfirmPassword">
                                        <svg x-show="!showConfirmPassword" class="w-5 h-5 text-gray-500" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <svg x-show="showConfirmPassword" class="w-5 h-5 text-gray-500" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.02 10.02 0 01-4.132 5.411m0 0L21 21" />
                                        </svg>
                                    </button>
                                </div>
                                @error('password_confirmation')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="city">Ville</label>
                                <input type="text" id="city" wire:model="city"
                                    class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                @error('city')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="phone">Téléphone</label>
                                <input type="text" id="phone" wire:model="phone"
                                    class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                @error('phone')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="country_code">Code Pays</label>
                                <input type="text" id="country_code" wire:model="country_code"
                                    class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                @error('country_code')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="sexe">Sexe</label>
                                <select id="sexe" wire:model="sexe"
                                    class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Sélectionner</option>
                                    <option value="H">Homme</option>
                                    <option value="F">Femme</option>
                                </select>
                                @error('sexe')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="whatsapp">WhatsApp</label>
                                <input type="text" id="whatsapp" wire:model="whatsapp"
                                    class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                @error('whatsapp')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="role">Rôle</label>
                                <select id="role" wire:model="role"
                                    class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                                    <option value="">Sélectionner</option>
                                    <option value="user">Utilisateur</option>
                                    <option value="agent">Agent</option>
                                    <option value="admin">Admin</option>
                                    <option value="merchand">Marchand</option>
                                    <option value="employee_afc">Employé AFC</option>
                                </select>
                                @error('role')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeModal"
                                class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                            <button type="submit" form="userForm"
                                class="bg-indigo-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-indigo-700">{{ $editMode ? 'Modifier' : 'Ajouter' }}</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal for Changing Role -->
            <div wire:model="showRoleModal"
                class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showRoleModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Changer le Rôle</h2>
                    <form id="roleForm" wire:submit.prevent="changeRole">
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm mb-1" for="selectedRole">Nouveau Rôle <span class="text-red-500">*</span></label>
                            <select id="selectedRole" wire:model="selectedRole"
                                class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                <option value="">Sélectionner</option>
                                <option value="user">Utilisateur</option>
                                <option value="agent">Agent</option>
                                <option value="admin">Admin</option>
                                <option value="merchand">Marchand</option>
                                <option value="employee_afc">Employé AFC</option>
                            </select>
                            @error('selectedRole')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="button" wire:click="closeRoleModal"
                                class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                            <button type="submit" form="roleForm"
                                class="bg-indigo-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-indigo-700">Changer</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Toastr -->
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
            <script>
                toastr.options = {
                    closeButton: true,
                    progressBar: true,
                    positionClass: 'toast-top-right',
                    timeOut: 3000
                };
            </script>
        </div>
    @endvolt
</x-layouts.app>
