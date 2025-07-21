
<?php
use function Laravel\Folio\{name};
use Livewire\Volt\Component;

name('dashboard.users');

new class extends Component {
    public $search = '';
    public $showModal = false;
    public $nom = '';
    public $email = '';
    public $password = '';
    public $role = '';

    public function openModal()
    {
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['nom', 'email', 'password', 'role']);
    }

    public function addUtilisateur()
    {
        // Placeholder: Reset form without saving
        $this->closeModal();
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
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 21">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.15 5.6h.01m3.337 1.913h.01m-6.979 0h.01M5.541 11h.01M15 15h2.706a1.957 1.957 0 0 0 1.883-1.325A9 9 0 1 0 2.043 11.89 9.1 9.1 0 0 0 7.2 19.1a8.62 8.62 0 0 0 3.769.9A2.013 2.013 0 0 0 13 18v-.857A2.034 2.034 0 0 1 15 15Z" />
                        </svg>
                    </div>
                    <input type="text" id="search" wire:model.live="search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Rechercher un utilisateur" />
                </div>
                <button type="submit" class="inline-flex items-center py-2.5 px-3 ms-2 text-sm font-medium text-white bg-blue-700 rounded-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    <svg class="w-4 h-4 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                    </svg>Search
                </button>
            </form>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Utilisateurs</h3>
                    <p class="text-3xl font-bold text-indigo-600">200</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Utilisateurs Actifs</h3>
                    <p class="text-3xl font-bold text-indigo-600">180</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Rôles Admin</h3>
                    <p class="text-3xl font-bold text-indigo-600">20</p>
                </div>
            </div>

            <!-- Add Button -->
            <div class="flex justify-end mb-4">
                <button wire:click="openModal" class="bg-indigo-600 text-white py-2 px-6 rounded-lg hover:bg-indigo-700 transition duration-300">Ajouter Utilisateur</button>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b">
                            <th class="py-3 px-4 text-gray-800">ID</th>
                            <th class="py-3 px-4 text-gray-800">Nom</th>
                            <th class="py-3 px-4 text-gray-800">Email</th>
                            <th class="py-3 px-4 text-gray-800">Rôle</th>
                            <th class="py-3 px-4 text-gray-800">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">1</td>
                            <td class="py-3 px-4">Jean Dupont</td>
                            <td class="py-3 px-4">jean@example.com</td>
                            <td class="py-3 px-4">Admin</td>
                            <td class="py-3 px-4">
                                <button class="text-indigo-600 hover:underline">Modifier</button>
                                <button class="text-red-600 hover:underline ml-2">Supprimer</button>
                            </td>
                        </tr>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4">2</td>
                            <td class="py-3 px-4">Marie Curie</td>
                            <td class="py-3 px-4">marie@example.com</td>
                            <td class="py-3 px-4">Utilisateur</td>
                            <td class="py-3 px-4">
                                <button class="text-indigo-600 hover:underline">Modifier</button>
                                <button class="text-red-600 hover:underline ml-2">Supprimer</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Modal -->
            <div wire:model="showModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Ajouter un Utilisateur</h2>
                    <form wire:submit.prevent="addUtilisateur">
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="nom">Nom</label>
                            <input type="text" id="nom" wire:model="nom" class="w-full p-2 border rounded-lg" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="email">Email</label>
                            <input type="email" id="email" wire:model="email" class="w-full p-2 border rounded-lg" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="password">Mot de passe</label>
                            <input type="password" id="password" wire:model="password" class="w-full p-2 border rounded-lg" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="role">Rôle</label>
                            <select id="role" wire:model="role" class="w-full p-2 border rounded-lg" required>
                                <option value="">Sélectionner</option>
                                <option value="admin">Admin</option>
                                <option value="utilisateur">Utilisateur</option>
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" wire:click="closeModal" class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                            <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700">Ajouter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endvolt
</x-layouts.app>

