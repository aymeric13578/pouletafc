<?php
use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\order_detail;

name('dashboard.commands');

new class extends Component {
    public $search = '';
    public $showModal = false;
    public $showDetailsModal = false;
    public $orderId = null;
    public $status = '';
    public $order_details = null;

    public function getOrdersProperty()
    {
        return order_detail::when($this->search, function ($q) {
                $q->where('ref', 'like', '%' . $this->search . '%')
                  ->orWhere('address', 'like', '%' . $this->search . '%');
            })
            ->orderBy('id', 'desc')
            ->with('user')
            ->paginate(10);
    }

    public function getTotalOrdersProperty()
    {
        return order_detail::count();
    }

    public function getProcessingOrdersProperty()
    {
        return order_detail::whereIn('status', ['process', 'want', 'take'])->count();
    }

    public function getTotalAmountProperty()
    {
        return order_detail::sum('price');
    }

    public function openModal($id)
    {
        $this->orderId = $id;
        $order = order_detail::findOrFail($id);
        $this->status = $order->status;
        $this->showModal = true;
    }

    public function openDetailsModal($id)
    {
        $this->order_details = order_detail::with('user')->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->reset(['orderId', 'status']);
        $this->resetValidation();
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->order_details = null;
    }

    public function updateOrderStatus()
    {
        $this->validate([
            'status' => 'required|in:pending,Success,failed,waiting,process,want,take',
        ]);

        $order = order_detail::findOrFail($this->orderId);
        $order->status = $this->status;
        $order->save();

        $this->dispatch('notify', ['message' => 'Statut de la commande modifié avec succès !', 'type' => 'success']);
        $this->closeModal();
    }

    /**
     * Bascule le statut de paiement d'une commande.
     *
     * La colonne status_paiement est un enum('pending','Success','failed') : on
     * s'y tient. Le tunnel du site y écrivait 'paid'/'unpaid', valeurs que MySQL
     * refuse — aucune commande du web n'avait donc de paiement exploitable.
     */
    public function togglePaiement($id)
    {
        $order = order_detail::findOrFail($id);
        $order->status_paiement = $order->status_paiement === 'Success' ? 'pending' : 'Success';
        $order->save();

        $this->dispatch('notify', [
            'message' => $order->status_paiement === 'Success' ? 'Commande marquée payée !' : 'Paiement annulé.',
            'type' => 'success',
        ]);
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
                    <input type="text" id="search" wire:model.live="search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Rechercher une commande (référence ou adresse)" />
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
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Commandes</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->totalOrders }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Commandes En Cours</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->processingOrders }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Montant Total</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ number_format($this->totalAmount, 2, ',', ' ') }} FCFA</p>
                </div>
            </div>

            <!-- Tableau -->
            <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b">
                            <th class="py-3 px-4 text-gray-800">ID</th>
                            <th class="py-3 px-4 text-gray-800">Client</th>
                            <th class="py-3 px-4 text-gray-800">Montant</th>
                            <th class="py-3 px-4 text-gray-800">Adresse</th>
                            <th class="py-3 px-4 text-gray-800">Référence</th>
                            <th class="py-3 px-4 text-gray-800">Méthode de Paiement</th>
                            <th class="py-3 px-4 text-gray-800">Statut</th>
                            <th class="py-3 px-4 text-gray-800">Paiement</th>
                            <th class="py-3 px-4 text-gray-800">Date</th>
                            <th class="py-3 px-4 text-gray-800">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->orders as $order)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4">{{ $order->id }}</td>
                                <td class="py-3 px-4">{{ $order->user ? $order->user->name : 'N/A' }}</td>
                                <td class="py-3 px-4">{{ number_format($order->price, 2, ',', ' ') }} FCFA</td>
                                <td class="py-3 px-4">{{ $order->address ?? 'N/A' }}</td>
                                <td class="py-3 px-4">{{ $order->ref }}</td>
                                <td class="py-3 px-4">{{ $order->payment_method }}</td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $order->status === 'Success' ? 'bg-green-100 text-green-800' :
                                           ($order->status === 'failed' ? 'bg-red-100 text-red-800' :
                                           ($order->status === 'waiting' ? 'bg-yellow-100 text-yellow-800' :
                                           ($order->status === 'process' ? 'bg-blue-100 text-blue-800' :
                                           ($order->status === 'want' ? 'bg-orange-100 text-orange-800' :
                                           ($order->status === 'take' ? 'bg-purple-100 text-purple-800' :
                                           'bg-gray-100 text-gray-800'))))) }}">
                                        {{ $order->status === 'pending' ? 'En attente' :
                                           ($order->status === 'Success' ? 'Terminé' :
                                           ($order->status === 'failed' ? 'Supprimé' :
                                           ($order->status === 'waiting' ? 'En attente d\'un agent' :
                                           ($order->status === 'process' ? 'En cours de livraison' :
                                           ($order->status === 'want' ? 'Colis souhaité' :
                                           'Colis pris'))))) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $order->status_paiement === 'Success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $order->status_paiement === 'Success' ? 'Payée' : 'Non payée' }}
                                    </span>
                                    <button wire:click="togglePaiement({{ $order->id }})"
                                            class="mt-1.5 block rounded-lg px-2.5 py-1 text-xs font-bold text-white transition-colors
                                                {{ $order->status_paiement === 'Success' ? 'bg-gray-400 hover:bg-gray-500' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                                        {{ $order->status_paiement === 'Success' ? 'Annuler' : 'Marquer payée' }}
                                    </button>
                                </td>
                                <td class="py-3 px-4">{{ optional($order->created_at)->format('Y-m-d') }}</td>
                                <td class="py-3 px-4">
                                    <button wire:click="openDetailsModal({{ $order->id }})" class="text-blue-600 hover:text-blue-800 mr-2" title="Voir les détails">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <button wire:click="openModal({{ $order->id }})" class="text-gray-600 hover:text-gray-800" title="Modifier le statut">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <!-- Pagination -->
                <div class="mt-4">
                    {{ $this->orders->links() }}
                </div>
            </div>

            <!-- Modal pour modifier le statut -->
            <div wire:model="showModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Modifier le Statut de la Commande</h2>
                    <form wire:submit.prevent="updateOrderStatus" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm mb-1" for="status">Statut <span class="text-red-500">*</span></label>
                            <select id="status" wire:model="status" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                <option value="">Sélectionner</option>
                                <option value="pending">En attente</option>
                                <option value="Success">Terminé</option>
                                <option value="failed">Supprimé</option>
                                <option value="waiting">En attente d'un agent</option>
                                <option value="process">En cours de livraison</option>
                                <option value="want">Colis souhaité</option>
                                <option value="take">Colis pris</option>
                            </select>
                            @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeModal" class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                            <button type="submit" class="bg-indigo-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-indigo-700">Modifier</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Modal pour visualiser les détails -->
            <div wire:model="showDetailsModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showDetailsModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-4xl h-auto max-h-[75vh] overflow-y-auto">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Détails de la Commande</h2>
                    @if ($order_details)
                        <div class="space-y-4">
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID</label>
                                    <p class="text-sm">{{ $order_details->id }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Client</label>
                                    <p class="text-sm">{{ $order_details->user ? $order_details->user->name : 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Montant</label>
                                    <p class="text-sm">{{ number_format($order_details->price, 2, ',', ' ') }} FCFA</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Quantité</label>
                                    <p class="text-sm">{{ $order_details->qty ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Statut</label>
                                    <p class="text-sm">
                                        {{ $order_details->status === 'pending' ? 'En attente' :
                                           ($order_details->status === 'Success' ? 'Terminé' :
                                           ($order_details->status === 'failed' ? 'Supprimé' :
                                           ($order_details->status === 'waiting' ? 'En attente d\'un agent' :
                                           ($order_details->status === 'process' ? 'En cours de livraison' :
                                           ($order_details->status === 'want' ? 'Colis souhaité' :
                                           'Colis pris'))))) }}
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Date de création</label>
                                    <p class="text-sm">{{ optional($order_details->created_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Date de mise à jour</label>
                                    <p class="text-sm">{{ optional($order_details->updated_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID Panier</label>
                                    <p class="text-sm">{{ $order_details->id_cart ?? 'N/A' }}</p>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-gray-700 text-sm mb-1">Adresse</label>
                                    <p class="text-sm">{{ $order_details->address ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Référence</label>
                                    <p class="text-sm">{{ $order_details->ref ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID Agent</label>
                                    <p class="text-sm">{{ $order_details->id_agent ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID Utilisateur</label>
                                    <p class="text-sm">{{ $order_details->id_user ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Méthode de Paiement</label>
                                    <p class="text-sm">{{ $order_details->payment_method ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Commission Vendeur</label>
                                    <p class="text-sm">{{ $order_details->commission_seller ? number_format($order_details->commission_seller, 2, ',', ' ') . ' FCFA' : 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Longitude Client</label>
                                    <p class="text-sm">{{ $order_details->longitude ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Latitude Client</label>
                                    <p class="text-sm">{{ $order_details->latitude ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Longitude Agent</label>
                                    <p class="text-sm">{{ $order_details->lonAgent ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Latitude Agent</label>
                                    <p class="text-sm">{{ $order_details->latAgent ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Matricule Véhicule</label>
                                    <p class="text-sm">{{ $order_details->matricule_vehicule ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Latitude Boutique</label>
                                    <p class="text-sm">{{ $order_details->latShop ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Longitude Boutique</label>
                                    <p class="text-sm">{{ $order_details->lonShop ?? 'N/A' }}</p>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-gray-700 text-sm mb-1">Nom Boutique</label>
                                    <p class="text-sm">{{ $order_details->shop_name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Code de Livraison</label>
                                    <p class="text-sm">{{ $order_details->delivery_code ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Commission Agent</label>
                                    <p class="text-sm">{{ $order_details->commission_agent ? number_format($order_details->commission_agent, 2, ',', ' ') . ' FCFA' : 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeDetailsModal" class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600">Fermer</button>
                        </div>
                    @endif
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
