<?php
use function Laravel\Folio\{name};
use Livewire\Volt\Component;

name('dashboard.customers');

new class extends Component {
    public $search = '';
    public $showModal = false;
    public $nom = '';
    public $email = '';
    public $telephone = '';

    public function openModal()
    {
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['nom', 'email', 'telephone']);
    }

    public function addClient()
    {
        $this->dispatch('notify', ['message' => 'Client ajouté avec succès !', 'type' => 'success']);
        $this->closeModal();
    }
};
?>

<x-layouts.app>
    @volt
        <div class="container mx-auto px-2 mt-6">
            <!-- Notifications -->
            <div x-data x-on:notify.window="toastr[event.detail.type](event.detail.message)"></div>

            <!-- Barre de recherche -->
            <form class="flex items-center max-w-lg mx-auto mb-6">
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative w-full">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 21">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.15 5.6h.01m3.337 1.913h.01m-6.979 0h.01M5.541 11h.01M15 15h2.706a1.957 1.957 0 0 0 1.883-1.325A9 9 0 1 0 2.043 11.89 9.1 9.1 0 0 0 7.2 19.1a8.62 8.62 0 0 0 3.769.9A2.013 2.013 0 0 0 13 18v-.857A2.034 2.034 0 0 1 15 15Z" />
                        </svg>
                    </div>
                    <input type="text" id="search" wire:model.live="search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Rechercher un client" />
                </div>
                <button type="submit" class="inline-flex items-center py-2.5 px-3 ms-2 text-sm font-medium text-white bg-blue-700 rounded-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800">
                    <svg class="w-4 h-4 me-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 20">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                    </svg>Rechercher
                </button>
            </form>

            <!-- Cartes de statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Clients</h3>
                    <p class="text-3xl font-bold text-indigo-600">1200</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Clients Actifs</h3>
                    <p class="text-3xl font-bold text-indigo-600">980</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Nouveaux (30j)</h3>
                    <p class="text-3xl font-bold text-indigo-600">120</p>
                </div>
            </div>

            <!-- Bouton Ajouter -->
            <div class="flex justify-end mb-4">
                <button wire:click="openModal" class="bg-indigo-600 text-white py-2 px-6 rounded-lg hover:bg-indigo-700 transition duration-300">Ajouter Client</button>
            </div>

            <!-- Tableau -->
            <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b">
                            <th class="py-3 px-4 text-gray-800">ID</th>
                            <th class="py-3 px-4 text-gray-800">Nom</th>
                            <th class="py-3 px-4 text-gray-800">Email</th>
                            <th class="py-3 px-4 text-gray-800">Téléphone</th>
                            <th class="py-3 px-4 text-gray-800">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ([[ 'id' => 1, 'nom' => 'Jean Dupont', 'email' => 'jean@example.com', 'telephone' => '+22501020304' ], [ 'id' => 2, 'nom' => 'Marie Curie', 'email' => 'marie@example.com', 'telephone' => '+22505060708' ]] as $client)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4">{{ $client['id'] }}</td>
                                <td class="py-3 px-4">{{ $client['nom'] }}</td>
                                <td class="py-3 px-4">{{ $client['email'] }}</td>
                                <td class="py-3 px-4">{{ $client['telephone'] }}</td>
                                <td class="py-3 px-4">
                                    <button class="text-indigo-600 hover:underline">Modifier</button>
                                    <button class="text-red-600 hover:underline ml-2">Supprimer</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Modale -->
            <div wire:model="showModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Ajouter un Client</h2>
                    <form wire:submit.prevent="addClient">
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="nom">Nom</label>
                            <input type="text" id="nom" wire:model="nom" class="w-full p-2 border rounded-lg" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="email">Email</label>
                            <input type="email" id="email" wire:model="email" class="w-full p-2 border rounded-lg" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-700 mb-2" for="telephone">Téléphone</label>
                            <input type="text" id="telephone" wire:model="telephone" class="w-full p-2 border rounded-lg" required>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" wire:click="closeModal" class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                            <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700">Ajouter</button>
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
