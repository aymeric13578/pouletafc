<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Commandes</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flowbite@2.5.2/dist/flowbite.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    @livewireStyles
</head>

<body class="min-h-screen bg-gray-50">
    @livewireScripts
    <?php
    use function Laravel\Folio\{name};
    use Livewire\Volt\Component;
    use App\Models\order_detail;

    // Renommé : /commandes est désormais servi par la version React (OrderBoardController).
    // Cette page reste accessible sur /commandes-legacy le temps de la comparaison.
    name('dashboard.commands.legacy');

    new class extends Component {
        public $search = '';
        public $showModal = false;
        public $showDetailsModal = false;
        public $showSoundModal = false;
        public $showAgentModal = false;
        public $showCartModal = false;
        public $orderId = null;
        public $status = '';
        public $order_details = null;
        public $agent_details = null;
        public $cart_details = null;
        public $showNotification = false;
        public $notificationMessage = '';
        public $notificationType = '';

        public function refreshTick()
        {
            // Méthode vide utilisée par wire:poll pour rafraîchir le composant
        }

        public function getOrdersProperty()
        {
            return order_detail::when($this->search, function ($q) {
                $q->where('ref', 'like', '%' . $this->search . '%')->orWhere('address', 'like', '%' . $this->search . '%');
            })
                ->orderBy('id', 'desc')
                ->with(['user', 'agent.user', 'carts.cart_items.product'])
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

        public function openAgentModal($id)
        {
            $order = order_detail::with('agent.user')->findOrFail($id);
            $this->agent_details = $order->agent;
            $this->showAgentModal = true;
        }

        public function openCartModal($id)
        {
            $order = order_detail::with('carts.cart_items.product')->findOrFail($id);
            $this->cart_details = $order->carts;
            $this->showCartModal = true;
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

        public function closeAgentModal()
        {
            $this->showAgentModal = false;
            $this->agent_details = null;
        }

        public function closeCartModal()
        {
            $this->showCartModal = false;
            $this->cart_details = null;
        }

        public function updateOrderStatus()
        {
            $this->validate([
                'status' => 'required|in:pending,Success,failed,waiting,process,want,take',
            ]);

            $order = order_detail::findOrFail($this->orderId);
            $order->status = $this->status;
            $order->save();
            $this->showNotification = true;
            $this->notificationMessage = 'Statut de la commande modifié avec succès !';
            $this->notificationType = 'success';
            $this->dispatch('auto-hide-notification');
            $this->closeModal();
        }

        public function markAsReady($id)
        {
            $order = order_detail::findOrFail($id);
            $order->status = 'process';
            $order->save();
            $this->showNotification = true;
            $this->notificationMessage = 'Commande marquée comme prête !';
            $this->notificationType = 'success';
            $this->dispatch('auto-hide-notification');
        }

        public function deleteOrder($id)
        {
            $order = order_detail::findOrFail($id);
            $order->delete();

            $this->showNotification = true;
            $this->notificationMessage = 'Commande supprimée avec succès !';
            $this->notificationType = 'success';
            $this->dispatch('auto-hide-notification');
        }

        public function deactivateOrder($id)
        {
            $order = order_detail::findOrFail($id);
            $order->status = 'failed';
            $order->save();
            $this->showNotification = true;
            $this->notificationMessage = 'Commande désactivée avec succès !';
            $this->notificationType = 'success';
            $this->dispatch('auto-hide-notification');
        }

        public function hideNotification()
        {
            $this->showNotification = false;
        }
    };
    ?>
    @volt
        <div id="app-container">
            @if ($showNotification)
                <div class="fixed top-4 right-4 z-50 transition-all duration-300 ease-in-out">
                    <div
                        class="border-l-4 p-4 rounded shadow-lg {{ $notificationType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700' }}">
                        <div class="flex justify-between items-center">
                            <p class="font-medium">{{ $notificationMessage }}</p>
                            <button wire:click="hideNotification" class="ml-4 text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12">
                                    </path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <script>
                document.addEventListener('livewire:init', () => {
                    Livewire.on('auto-hide-notification', () => {
                        setTimeout(() => {
                            @this.hideNotification();
                        }, 3000);
                    });
                });
            </script>

            <!-- Modal pour activer les notifications sonores -->
            <div class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden"
                id="enableSoundModal" tabindex="-1" aria-hidden="true">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">Activer les notifications sonores</h3>
                        <button type="button" onclick="window.NotificationManager?.closeSoundModal()"
                            class="text-gray-400 hover:text-gray-900">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">Cliquez sur "Activer" pour permettre la lecture des sons de
                        notification pour les nouvelles commandes.</p>
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="window.NotificationManager?.closeSoundModal()"
                            class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600">Annuler</button>
                        <button type="button" onclick="window.NotificationManager?.enableSound()"
                            class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">Activer</button>
                    </div>
                </div>
            </div>

            <!-- Conteneur pour les notifications -->
            <div id="notificationContainer" class="fixed top-4 right-4 z-50 w-80"></div>

            <div class="container mx-auto px-4 mt-6">
                <div class="flex justify-center mb-6">
                    <img src="{{ asset('images/logo.png') }}" class="h-40" alt="Flowbite Logo" />
                </div>


                <!-- Bouton pour activer le son manuellement -->
                <div class="flex justify-center mb-4">
                    <button id="enableSoundBtn" onclick="window.NotificationManager?.enableSound()"
                        class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 hidden">
                        Activer les notifications sonores
                    </button>
                </div>

                <!-- Rafraîchissement Livewire toutes les 5s -->
                <span wire:poll.5s="refreshTick" class="hidden" aria-hidden="true"></span>

                <!-- Barre de recherche -->
                <form class="flex items-center max-w-2xl mx-auto mb-6">
                    <button type="button" onclick="window.NotificationManager?.enableSound()"
                        class="bg-blue-600 text-white py-2 gap-2 mr-2 px-4 rounded-lg no-wrap hover:bg-blue-700">Activer le
                        son</button>
                    <label for="search" class="sr-only">Rechercher</label>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 20 20">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                            </svg>
                        </div>
                        <input type="text" id="search" wire:model.live="search"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 p-2.5"
                            placeholder="Rechercher une commande (référence ou adresse)" />
                    </div>
                    <button type="submit"
                        class="inline-flex items-center py-2.5 px-3 ml-2 text-sm font-medium text-white bg-blue-700 rounded-lg border border-blue-700 hover:bg-blue-800 focus:ring-4 focus:outline-none focus:ring-blue-300">
                        <svg class="w-4 h-4 mr-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 20 20">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="m19 19-4-4m0-7A7 7 0 1 1 1 8a7 7 0 0 1 14 0Z" />
                        </svg>Rechercher
                    </button>
                </form>

                <!-- Cartes de statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div
                        class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Commandes</h3>
                        <p class="text-3xl font-bold text-blue-600">{{ $this->totalOrders }}</p>
                    </div>
                    <div
                        class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Commandes En Cours</h3>
                        <p class="text-3xl font-bold text-blue-600">{{ $this->processingOrders }}</p>
                    </div>
                    <div
                        class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Montant Total</h3>
                        <p class="text-3xl font-bold text-blue-600">{{ number_format($this->totalAmount, 2, ',', ' ') }}
                            FCFA</p>
                    </div>
                </div>

                <!-- Tableau -->
                <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b">
                                <th class="py-3 px-4 text-gray-800">Actions</th>
                                <th class="py-3 px-4 text-gray-800">Agent</th>
                                <th class="py-3 px-4 text-gray-800">Panier</th>
                                <th class="py-3 px-4 text-gray-800">Montant</th>
                                <th class="py-3 px-4 text-gray-800">Frais de livraison</th>
                                <th class="py-3 px-4 text-gray-800">Adresse</th>
                                <th class="py-3 px-4 text-gray-800">Client</th>
                                <th class="py-3 px-4 text-gray-800">Contact</th>
                                <th class="py-3 px-4 text-gray-800">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->orders as $order)
                                <tr class="border-b hover:bg-gray-50 {{ $order->status === 'pending' ? 'bg-yellow-100' : '' }} {{ $order->status === 'Success' ? 'bg-green-100' : '' }}"
                                    data-order-id="{{ $order->id }}">
                                    <td class="py-3 px-4">
                                        <button wire:click="openDetailsModal({{ $order->id }})"
                                            class="text-blue-600 hover:text-blue-800 mr-2" title="Voir les détails">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <button wire:click="openModal({{ $order->id }})"
                                            class="text-gray-600 hover:text-gray-800" title="Modifier le statut">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                                </path>
                                            </svg>
                                        </button>
                                        <button wire:click="deleteOrder({{ $order->id }})"
                                            class="text-red-600 hover:text-red-800 ml-2" title="Supprimer">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                        @if ($order->status === 'pending')
                                            <button wire:click="markAsReady({{ $order->id }})"
                                                class="badge bg-green-600 text-white text-xs px-2 py-1 rounded-full ml-2">Colis
                                                prêt</button>
                                        @endif
                                        @if ($order->status === 'Success')
                                            <button wire:click="deactivateOrder({{ $order->id }})"
                                                class="badge bg-red-600 text-white text-xs px-2 py-1 rounded-full ml-2">Désactiver</button>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @if ($order->id_agent)
                                            <button wire:click="openAgentModal({{ $order->id }})"
                                                class="text-blue-600 hover:text-blue-800"
                                                title="Voir les détails de l'agent">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        @else
                                            Aucun agent
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @if ($order->id_cart)
                                            <button wire:click="openCartModal({{ $order->id }})"
                                                class="text-blue-600 hover:text-blue-800"
                                                title="Voir les détails du panier">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        @else
                                            Panier vide
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">{{ number_format($order->price, 2, ',', ' ') }} FCFA
                                        <br>
                                        @if ($order->status_paiement == 'Success')
                                            <p class="text-green-600 dark:text-sky-400">Payé</p>
                                        @endif
                                        @if ($order->status_paiement == 'pending')
                                            <p class="text-red-600 dark:text-sky-400">Non payé</p>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">{{ number_format($order->delivery_fees ?? 0, 2, ',', ' ') }}
                                        FCFA</td>
                                    <td class="py-3 px-4">{{ $order->address ?? 'N/A' }}</td>
                                    <td class="py-3 px-4">{{ $order->user ? $order->user->name : 'N/A' }}</td>
                                    <td class="py-3 px-4">{{ $order->user ? $order->user->phone : 'N/A' }}</td>
                                    <td class="py-3 px-4">
                                        <span
                                            class="badge px-2 py-1 text-xs font-semibold rounded-full
                                            {{ $order->status === 'Success'
                                                ? 'bg-green-100 text-green-800'
                                                : ($order->status === 'failed'
                                                    ? 'bg-red-100 text-red-800'
                                                    : ($order->status === 'waiting'
                                                        ? 'bg-yellow-100 text-yellow-800'
                                                        : ($order->status === 'process'
                                                            ? 'bg-blue-100 text-blue-800'
                                                            : ($order->status === 'want'
                                                                ? 'bg-orange-100 text-orange-800'
                                                                : ($order->status === 'take'
                                                                    ? 'bg-purple-100 text-purple-800'
                                                                    : 'bg-gray-100 text-gray-800'))))) }}">
                                            {{ $order->status === 'pending'
                                                ? 'En cours de préparation'
                                                : ($order->status === 'Success'
                                                    ? 'Livraison Terminée'
                                                    : ($order->status === 'failed'
                                                        ? 'Supprimé'
                                                        : ($order->status === 'waiting'
                                                            ? 'En attente d\'un agent'
                                                            : ($order->status === 'process'
                                                                ? 'En cours de livraison'
                                                                : ($order->status === 'want'
                                                                    ? 'Colis souhaité'
                                                                    : 'Colis pris'))))) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <!-- Pagination -->
                    <div class="mt-4">
                        {{ $this->orders->links('vendor.pagination.tailwind') }}
                    </div>
                </div>

                <!-- Modal pour modifier le statut -->
                <div wire:model="showModal"
                    class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 {{ $showModal ? '' : 'hidden' }}"
                    id="statusModal" tabindex="-1" aria-hidden="true">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Modifier le Statut de la Commande</h2>
                        <form wire:submit.prevent="updateOrderStatus" class="space-y-4">
                            <div>
                                <label class="block text-gray-700 text-sm mb-1" for="status">Statut <span
                                        class="text-red-500">*</span></label>
                                <select id="status" wire:model="status"
                                    class="w-full p-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    required>
                                    <option value="">Sélectionner</option>
                                    <option value="pending">En cours de préparation</option>
                                    <option value="Success">Livraison Terminée</option>
                                    <option value="failed">Supprimé</option>
                                    <option value="waiting">En attente d'un agent</option>
                                    <option value="process">En cours de livraison</option>
                                    <option value="want">Colis souhaité</option>
                                    <option value="take">Colis pris</option>
                                </select>
                                @error('status')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="flex justify-end mt-4">
                                <button type="button" wire:click="closeModal"
                                    class="bg-gray-500 text-white py-2 px-4 text-sm rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                                <button type="submit"
                                    class="bg-blue-600 text-white py-2 px-4 text-sm rounded-lg hover:bg-blue-700">Modifier</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Modal pour visualiser les détails -->
                <div wire:model="showDetailsModal"
                    class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 {{ $showDetailsModal ? '' : 'hidden' }}"
                    id="detailsModal" tabindex="-1" aria-hidden="true">
                    <div class="bg-white rounded-lg p-6 w-full max-w-4xl h-auto max-h-[75vh] overflow-y-auto">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Détails de la Commande</h2>
                        @if ($order_details)
                            <div class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">ID</label>
                                        <p class="text-sm">{{ $order_details->id }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Client</label>
                                        <p class="text-sm">{{ $order_details->user ? $order_details->user->name : 'N/A' }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Montant</label>
                                        <p class="text-sm">{{ number_format($order_details->price, 2, ',', ' ') }} FCFA
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Quantité</label>
                                        <p class="text-sm">{{ $order_details->qty ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Statut</label>
                                        <p class="text-sm">
                                            {{ $order_details->status === 'pending'
                                                ? 'En cours de préparation'
                                                : ($order_details->status === 'Success'
                                                    ? 'Livraison Terminée'
                                                    : ($order_details->status === 'failed'
                                                        ? 'Supprimé'
                                                        : ($order_details->status === 'waiting'
                                                            ? 'En attente d\'un agent'
                                                            : ($order_details->status === 'process'
                                                                ? 'En cours de livraison'
                                                                : ($order_details->status === 'want'
                                                                    ? 'Colis souhaité'
                                                                    : 'Colis pris'))))) }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Date de création</label>
                                        <p class="text-sm">
                                            {{ optional($order_details->created_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Date de mise à jour</label>
                                        <p class="text-sm">
                                            {{ optional($order_details->updated_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">ID Panier</label>
                                        <p class="text-sm">{{ $order_details->id_cart ?? 'N/A' }}</p>
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="block text-gray-700 text-sm mb-1">Adresse</label>
                                        <p class="text-sm">{{ $order_details->address ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Référence</label>
                                        <p class="text-sm">{{ $order_details->ref }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">ID Agent</label>
                                        <p class="text-sm">{{ $order_details->id_agent ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">ID Utilisateur</label>
                                        <p class="text-sm">{{ $order_details->id_user }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Méthode de Paiement</label>
                                        <p class="text-sm">{{ $order_details->payment_method ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Commission Vendeur</label>
                                        <p class="text-sm">
                                            {{ number_format($order_details->commission_seller, 2, ',', ' ') }} FCFA</p>
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
                                        <p class="text-sm">{{ $order_details->latShop }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Longitude Boutique</label>
                                        <p class="text-sm">{{ $order_details->lonShop }}</p>
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="block text-gray-700 text-sm mb-1">Nom Boutique</label>
                                        <p class="text-sm">{{ $order_details->shop_name }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Code de Livraison</label>
                                        <p class="text-sm">{{ $order_details->delivery_code ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm mb-1">Commission Agent</label>
                                        <p class="text-sm">{{ $order_details->commission_agent ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-end mt-4">
                                <button type="button" wire:click="closeDetailsModal"
                                    class="bg-gray-500 text-white py-2 px-4 text-sm rounded-lg hover:bg-gray-600">Fermer</button>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Modal pour les détails de l'agent -->
                <div wire:model="showAgentModal"
                    class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 {{ $showAgentModal ? '' : 'hidden' }}"
                    id="agentModal" tabindex="-1" aria-hidden="true">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Détails de l'Agent</h2>
                        @if ($agent_details && $agent_details->user)
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Nom</label>
                                    <p class="text-sm">{{ $agent_details->user->last_name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Prénom</label>
                                    <p class="text-sm">{{ $agent_details->user->name ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Référence</label>
                                    <p class="text-sm">{{ $agent_details->user->ref ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Contact</label>
                                    <p class="text-sm">{{ $agent_details->user->phone ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">WhatsApp</label>
                                    <p class="text-sm">{{ $agent_details->user->whatsapp ?? 'N/A' }}</p>
                                </div>
                            </div>
                            <div class="flex justify-end mt-4">
                                <button type="button" wire:click="closeAgentModal"
                                    class="bg-gray-500 text-white py-2 px-4 text-sm rounded-lg hover:bg-gray-600">Fermer</button>
                            </div>
                        @else
                            <p class="text-sm text-gray-600">Aucun agent assigné</p>
                            <div class="flex justify-end mt-4">
                                <button type="button" wire:click="closeAgentModal"
                                    class="bg-gray-500 text-white py-2 px-4 text-sm rounded-lg hover:bg-gray-600">Fermer</button>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Modal pour les détails du panier -->
                <div wire:model="showCartModal"
                    class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 {{ $showCartModal ? '' : 'hidden' }}"
                    id="cartModal" tabindex="-1" aria-hidden="true">
                    <div class="bg-white rounded-lg p-6 w-full max-w-md h-auto max-h-[75vh] overflow-y-auto">
                        <h2 class="text-lg font-semibold text-gray-800 mb-4">Détails du Panier</h2>
                        @if ($cart_details && $cart_details->cart_items && count($cart_details->cart_items) > 0)
                            <div class="space-y-4">
                                @foreach ($cart_details->cart_items as $item)
                                    <div class="border-b pb-4">
                                        <p class="text-sm font-semibold">
                                            {{ $item->product ? $item->product->name : 'Produit inconnu' }}</p>
                                        <p class="text-sm">Quantité : {{ $item->quantity ?? 1 }}</p>
                                        <p class="text-sm">Prix unitaire :
                                            {{ number_format($item->product->price ?? 0, 2, ',', ' ') }} FCFA</p>
                                        <p class="text-sm font-semibold">Total :
                                            {{ number_format(($item->product->price ?? 0) * ($item->quantity ?? 1), 2, ',', ' ') }}
                                            FCFA</p>
                                    </div>
                                @endforeach
                                <div class="mt-4">
                                    <p class="text-sm font-bold">Total du panier :
                                        {{ number_format($cart_details->total_amount ?? $cart_details->cart_items->sum(fn($item) => ($item->product->price ?? 0) * ($item->quantity ?? 1)), 2, ',', ' ') }}
                                        FCFA</p>
                                </div>
                            </div>
                            <div class="flex justify-end mt-4">
                                <button type="button" wire:click="closeCartModal"
                                    class="bg-gray-500 text-white py-2 px-4 text-sm rounded-lg hover:bg-gray-600">Fermer</button>
                            </div>
                        @else
                            <p class="text-sm text-gray-600">Panier vide</p>
                            <div class="flex justify-end mt-4">
                                <button type="button" wire:click="closeCartModal"
                                    class="bg-gray-500 text-white py-2 px-4 text-sm rounded-lg hover:bg-gray-600">Fermer</button>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Audio pour notification -->
                <audio id="notificationSound" preload="auto">
                    <source src="{{ asset('sounds/notification.mp3') }}" type="audio/mpeg">
                    <source src="{{ asset('sounds/notification.wav') }}" type="audio/wav">
                    <source src="{{ asset('sounds/notification.ogg') }}" type="audio/ogg">
                </audio>
            </div>

            <!-- Scripts -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/flowbite@2.5.2/dist/flowbite.min.js"></script>
            <script src="https://unpkg.com/htmx.org@1.9.10"></script>

            <script>
                if (typeof toastr !== 'undefined') {
                    toastr.options = {
                        closeButton: true,
                        progressBar: true,
                        positionClass: 'toast-top-right',
                        timeOut: 5000,
                        extendedTimeOut: 2000,
                        showMethod: 'fadeIn',
                        hideMethod: 'fadeOut'
                    };
                }

                class NotificationManager {
                    constructor() {
                        this.isSoundEnabled = localStorage.getItem('soundEnabled') === 'true';
                        this.desktopNotify = false;
                        this.lastOrderId = Number(localStorage.getItem('lastOrderId')) || 0;
                        this.isProcessingNotification = false;
                        this.originalTitle = document.title;
                        this.titleFlashIntervalId = null;
                        this.audioElement = null;
                        this.audioContext = null;

                        this.init();
                    }

                    init() {
                        console.log('[NotificationManager] Initialisation...');
                        this.initAudio();
                        this.requestDesktopNotificationPermission();
                        this.setupLivewireListeners();
                        this.setupUserInteractionHandlers();
                        if (!this.isSoundEnabled) {
                            this.showSoundModal();
                        }
                        this.startOrderPolling();
                        console.log('[NotificationManager] Initialisé avec succès');
                    }

                    initAudio() {
                        this.audioElement = document.getElementById('notificationSound');
                        if (!this.audioElement) {
                            console.warn('[NotificationManager] Élément audio non trouvé, création d\'un audio dynamique');
                            this.createFallbackAudio();
                        } else {
                            this.audioElement.load();
                            console.log('[NotificationManager] Audio element trouvé:', this.audioElement.src);
                        }

                        try {
                            this.audioContext = new(window.AudioContext || window.webkitAudioContext)();
                        } catch (error) {
                            console.warn('[NotificationManager] Web Audio API non supportée:', error);
                        }
                    }

                    createFallbackAudio() {
                        this.audioElement = document.createElement('audio');
                        this.audioElement.id = 'notificationSoundFallback';
                        this.audioElement.preload = 'auto';
                        const source1 = document.createElement('source');
                        source1.src = '/sounds/notification.mp3';
                        source1.type = 'audio/mpeg';
                        this.audioElement.appendChild(source1);
                        const source2 = document.createElement('source');
                        source2.src = '/sounds/notification.wav';
                        source2.type = 'audio/wav';
                        this.audioElement.appendChild(source2);
                        document.body.appendChild(this.audioElement);
                        this.audioElement.load();
                        console.log('[NotificationManager] Audio fallback créé');
                    }

                    generateNotificationSound() {
                        if (!this.audioContext) return Promise.reject('Web Audio API non disponible');
                        return new Promise((resolve, reject) => {
                            try {
                                if (this.audioContext.state === 'suspended') {
                                    this.audioContext.resume();
                                }
                                const oscillator = this.audioContext.createOscillator();
                                const gainNode = this.audioContext.createGain();
                                oscillator.connect(gainNode);
                                gainNode.connect(this.audioContext.destination);
                                oscillator.frequency.setValueAtTime(800, this.audioContext.currentTime);
                                oscillator.frequency.setValueAtTime(600, this.audioContext.currentTime + 0.1);
                                oscillator.frequency.setValueAtTime(800, this.audioContext.currentTime + 0.2);
                                gainNode.gain.setValueAtTime(0.3, this.audioContext.currentTime);
                                gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + 0.4);
                                oscillator.start(this.audioContext.currentTime);
                                oscillator.stop(this.audioContext.currentTime + 0.4);
                                oscillator.onended = () => resolve();
                            } catch (error) {
                                reject(error);
                            }
                        });
                    }

                    setupLivewireListeners() {
                        document.addEventListener('livewire:init', () => {
                            Livewire.on('show-notification', (data) => {
                                this.showToast(data[0].message, data[0].type);
                            });
                        });
                        window.addEventListener('show-notification', (event) => {
                            if (event.detail) {
                                this.showToast(event.detail.message, event.detail.type);
                            }
                        });
                    }

                    setupUserInteractionHandlers() {
                        const unlockAudio = () => {
                            if (this.audioContext && this.audioContext.state === 'suspended') {
                                this.audioContext.resume();
                            }
                            if (this.audioElement && !this.audioElement.readyState) {
                                this.audioElement.load();
                            }
                        };
                        document.addEventListener('click', unlockAudio, {
                            once: true
                        });
                        document.addEventListener('touchstart', unlockAudio, {
                            once: true
                        });
                        document.addEventListener('keydown', unlockAudio, {
                            once: true
                        });
                    }

                    requestDesktopNotificationPermission() {
                        if (typeof Notification === 'undefined') return;
                        if (Notification.permission === 'default') {
                            Notification.requestPermission().then(result => {
                                this.desktopNotify = result === 'granted';
                                if (result === 'granted') {
                                    this.showToast('Notifications desktop activées', 'success');
                                }
                            });
                        } else {
                            this.desktopNotify = Notification.permission === 'granted';
                        }
                    }

                    showSoundModal() {
                        const modal = document.getElementById('enableSoundModal');
                        const btn = document.getElementById('enableSoundBtn');
                        if (modal) {
                            modal.classList.remove('hidden');
                            console.log('[NotificationManager] Modal affiché');
                        }
                        if (btn) {
                            btn.classList.remove('hidden');
                        }
                    }

                    enableSound() {
                        console.log('[NotificationManager] Tentative d\'activation du son...');
                        if (this.audioElement) {
                            if (this.audioElement.readyState < 2) {
                                console.log('[NotificationManager] Audio non prêt, chargement en cours...');
                                this.audioElement.load();
                                this.audioElement.addEventListener('canplay', () => {
                                    this.playAudioElement();
                                }, {
                                    once: true
                                });
                                this.audioElement.addEventListener('error', () => {
                                    console.warn(
                                        '[NotificationManager] Erreur de chargement audio, essai Web Audio API');
                                    this.tryWebAudioFallback();
                                }, {
                                    once: true
                                });
                            } else {
                                this.playAudioElement();
                            }
                        } else {
                            console.warn('[NotificationManager] Aucun élément audio, essai Web Audio API');
                            this.tryWebAudioFallback();
                        }
                    }

                    playAudioElement() {
                        console.log('[NotificationManager] Tentative de lecture de l\'élément audio...');
                        this.audioElement.currentTime = 0;
                        const playPromise = this.audioElement.play();
                        if (playPromise !== undefined) {
                            playPromise.then(() => {
                                this.isSoundEnabled = true;
                                localStorage.setItem('soundEnabled', 'true');
                                this.showToast('Notifications sonores activées !', 'success');
                                this.hideSoundModal();
                                console.log('[NotificationManager] Son activé avec succès via audio element');
                            }).catch(error => {
                                console.warn('[NotificationManager] Échec de lecture audio element:', error);
                                this.showToast('Échec de l\'activation du son. Veuillez réessayer.', 'error');
                                this.tryWebAudioFallback();
                            });
                        }
                    }

                    tryWebAudioFallback() {
                        if (this.audioContext) {
                            console.log('[NotificationManager] Tentative avec Web Audio API...');
                            this.generateNotificationSound().then(() => {
                                this.isSoundEnabled = true;
                                localStorage.setItem('soundEnabled', 'true');
                                this.showToast('Notifications sonores activées (Web Audio) !', 'success');
                                this.hideSoundModal();
                                console.log('[NotificationManager] Son activé avec Web Audio API');
                            }).catch(error => {
                                console.error('[NotificationManager] Échec Web Audio API:', error);
                                this.showToast('Impossible d\'activer le son. Vérifiez les paramètres du navigateur.',
                                    'error');
                            });
                        } else {
                            console.warn('[NotificationManager] Web Audio API non disponible');
                            this.showToast('Audio non supporté par ce navigateur.', 'warning');
                        }
                    }

                    closeSoundModal() {
                        this.isSoundEnabled = false;
                        localStorage.removeItem('soundEnabled');
                        this.hideSoundModal();
                        console.log('[NotificationManager] Modal fermé manuellement');
                    }

                    hideSoundModal() {
                        const modal = document.getElementById('enableSoundModal');
                        const btn = document.getElementById('enableSoundBtn');
                        if (modal) {
                            modal.classList.add('hidden');
                            console.log('[NotificationManager] Modal caché');
                        }
                        if (btn) {
                            btn.classList.add('hidden');
                        }
                    }

                    testSound() {
                        console.log('[NotificationManager] Test du son...');
                        if (!this.isSoundEnabled) {
                            this.showSoundModal();
                            this.showToast('Veuillez activer le son d\'abord.', 'warning');
                            return;
                        }
                        this.playNotificationSound();
                        this.showToast('Test de notification sonore', 'info');
                    }

                    playNotificationSound() {
                        if (!this.isSoundEnabled) {
                            console.warn('[NotificationManager] Son désactivé');
                            this.showSoundModal();
                            return;
                        }
                        console.log('[NotificationManager] Lecture du son...');
                        if (this.audioElement && this.audioElement.src) {
                            try {
                                console.log('[NotificationManager] Tentative avec audio element:', this.audioElement.src);
                                this.audioElement.currentTime = 0;
                                const playPromise = this.audioElement.play();
                                if (playPromise !== undefined) {
                                    playPromise.then(() => {
                                        console.log('[NotificationManager] Son joué avec succès via audio element');
                                    }).catch(error => {
                                        console.warn(
                                            '[NotificationManager] Échec audio element, utilisation Web Audio:',
                                            error);
                                        this.generateNotificationSound().catch(err => {
                                            console.error('[NotificationManager] Échec Web Audio API:', err);
                                        });
                                    });
                                }
                            } catch (error) {
                                console.error('[NotificationManager] Erreur lors de la lecture audio:', error);
                                this.generateNotificationSound().catch(err => {
                                    console.error('[NotificationManager] Échec Web Audio API:', err);
                                });
                            }
                        } else {
                            console.log('[NotificationManager] Utilisation de Web Audio API');
                            this.generateNotificationSound().catch(error => {
                                console.error('[NotificationManager] Erreur Web Audio API:', error);
                            });
                        }
                    }

                    showToast(message, type = 'info') {
                        console.log('[NotificationManager] Affichage toast:', message, type);
                        if (typeof toastr !== 'undefined' && toastr[type]) {
                            try {
                                toastr[type](message);
                            } catch (error) {
                                console.error('[NotificationManager] Erreur Toastr:', error);
                                this.showCustomNotification(message, type);
                            }
                        } else {
                            this.showCustomNotification(message, type);
                        }
                    }

                    showCustomNotification(message, type) {
                        const container = document.getElementById('notificationContainer');
                        if (!container) return;
                        const notification = document.createElement('div');
                        notification.className =
                            `alert bg-${this.getColorClass(type)} text-white font-bold rounded-lg p-4 mt-4 shadow-lg transform transition-all duration-500 slideIn flex items-start space-x-2`;
                        notification.innerHTML = `
                            <svg class="w-5 h-5 text-white mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                            </svg>
                            <span>${message}</span>
                            <button type="button" onclick="this.parentElement.remove()" class="ml-auto text-white hover:text-gray-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        `;
                        container.appendChild(notification);
                        setTimeout(() => {
                            if (notification.parentElement) {
                                notification.remove();
                            }
                        }, 5000);
                    }

                    getColorClass(type) {
                        const colors = {
                            success: 'green-600',
                            error: 'red-600',
                            warning: 'yellow-600',
                            info: 'blue-600'
                        };
                        return colors[type] || 'blue-600';
                    }

                    showDesktopNotification(title, message) {
                        if (!this.desktopNotify || typeof Notification === 'undefined') return;
                        try {
                            new Notification(title, {
                                body: message,
                                icon: '/images/logo.png',
                                badge: '/images/logo.png'
                            });
                        } catch (error) {
                            console.error('[NotificationManager] Erreur notification desktop:', error);
                        }
                    }

                    startTitleFlash() {
                        if (this.titleFlashIntervalId) return;
                        let toggle = false;
                        this.titleFlashIntervalId = setInterval(() => {
                            document.title = toggle ? '🔔 Nouvelle commande !' : this.originalTitle;
                            toggle = !toggle;
                        }, 1000);
                        setTimeout(() => this.stopTitleFlash(), 30000);
                    }

                    stopTitleFlash() {
                        if (this.titleFlashIntervalId) {
                            clearInterval(this.titleFlashIntervalId);
                            this.titleFlashIntervalId = null;
                            document.title = this.originalTitle;
                        }
                    }

                    async startOrderPolling() {
                        console.log('[NotificationManager] Démarrage du polling des commandes...');
                        const firstOrderElement = document.querySelector('tr[data-order-id]');
                        if (firstOrderElement && this.lastOrderId === 0) {
                            this.lastOrderId = parseInt(firstOrderElement.getAttribute('data-order-id'));
                            localStorage.setItem('lastOrderId', String(this.lastOrderId));
                            console.log('[NotificationManager] lastOrderId initialisé à:', this.lastOrderId);
                        }
                        this.checkForNewOrders();
                    }

                    async checkForNewOrders() {
                        if (this.isProcessingNotification) return;
                        this.isProcessingNotification = true;
                        try {
                            const response = await fetch(`/check-new-orders?lastOrderId=${this.lastOrderId}&_t=${Date.now()}`, {
                                method: 'GET',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                credentials: 'same-origin'
                            });
                            if (response.ok) {
                                const data = await response.json();
                                if (data.hasNewOrder && data.newOrderId > this.lastOrderId) {
                                    this.handleNewOrder(data.newOrderId);
                                }
                            }
                        } catch (error) {
                            console.error('[NotificationManager] Erreur lors de la vérification des commandes:', error);
                        } finally {
                            this.isProcessingNotification = false;
                            setTimeout(() => this.checkForNewOrders(), 5000);
                        }
                    }

                    handleNewOrder(newOrderId) {
                        console.log('[NotificationManager] Nouvelle commande détectée:', newOrderId);
                        this.lastOrderId = newOrderId;
                        localStorage.setItem('lastOrderId', String(newOrderId));
                        this.enableSound();
                        this.showToast('🎉 Nouvelle commande reçue !', 'success');
                        this.showDesktopNotification('Nouvelle Commande', `Commande #${newOrderId} vient d'arriver`);
                        this.startTitleFlash();
                        if (typeof Livewire !== 'undefined') {
                            Livewire.emit('refreshComponent');
                        } else {
                            setTimeout(() => window.location.reload(), 2000);
                        }
                        setTimeout(() => {
                            const newRow = document.querySelector(`tr[data-order-id="${newOrderId}"]`);
                            if (newRow) {
                                newRow.classList.add('blink');
                                setTimeout(() => newRow.classList.remove('blink'), 5000);
                            }
                        }, 1000);
                    }
                }

                let notificationManagerInstance;
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => {
                        notificationManagerInstance = new NotificationManager();
                        window.NotificationManager = notificationManagerInstance;
                    });
                } else {
                    notificationManagerInstance = new NotificationManager();
                    window.NotificationManager = notificationManagerInstance;
                }
            </script>

            <style>
                table {
                    width: 100% !important;
                    table-layout: auto;
                }

                .no-wrap {
                    white-space: nowrap;
                }

                table th,
                table td {
                    padding: 8px 5px;
                    font-size: 0.85rem;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                table th:nth-child(1),
                table td:nth-child(1) {
                    width: 20%;
                }

                table th:nth-child(2),
                table td:nth-child(2) {
                    width: 10%;
                }

                table th:nth-child(3),
                table td:nth-child(3) {
                    width: 10%;
                }

                table th:nth-child(4),
                table td:nth-child(4) {
                    width: 10%;
                }

                table th:nth-child(5),
                table td:nth-child(5) {
                    width: 10%;
                }

                table th:nth-child(6),
                table td:nth-child(6) {
                    width: 15%;
                }

                table th:nth-child(7),
                table td:nth-child(7) {
                    width: 10%;
                }

                table th:nth-child(8),
                table td:nth-child(8) {
                    width: 10%;
                }

                table th:nth-child(9),
                table td:nth-child(9) {
                    width: 15%;
                }

                .badge {
                    font-size: 0.75rem;
                    padding: 4px 8px;
                }

                @media (max-width: 768px) {

                    table th,
                    table td {
                        font-size: 0.75rem;
                    }

                    table th:nth-child(6),
                    table td:nth-child(6) {
                        display: none;
                    }

                    table th:nth-child(7),
                    table td:nth-child(7) {
                        display: none;
                    }

                    table th:nth-child(1),
                    table td:nth-child(1) {
                        width: 25%;
                    }

                    table th:nth-child(2),
                    table td:nth-child(2) {
                        width: 15%;
                    }

                    table th:nth-child(3),
                    table td:nth-child(3) {
                        width: 15%;
                    }

                    table th:nth-child(4),
                    table td:nth-child(4) {
                        width: 15%;
                    }

                    table th:nth-child(5),
                    table td:nth-child(5) {
                        width: 15%;
                    }

                    table th:nth-child(8),
                    table td:nth-child(8) {
                        width: 15%;
                    }

                    table th:nth-child(9),
                    table td:nth-child(9) {
                        width: 15%;
                    }
                }

                .alert {
                    animation: slideIn 0.5s ease-in-out;
                }

                @keyframes slideIn {
                    from {
                        transform: translateX(100%);
                        opacity: 0;
                    }

                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }

                .blink {
                    animation: blink-animation 1s steps(5, start) infinite;
                    background-color: #fef3c7 !important;
                }

                @keyframes blink-animation {
                    0% {
                        background-color: #fef3c7 !important;
                    }

                    50% {
                        background-color: #fde68a !important;
                    }

                    100% {
                        background-color: #fef3c7 !important;
                    }
                }

                .toast-top-right {
                    top: 20px;
                    right: 20px;
                }

                .notification-button {
                    position: relative;
                    overflow: hidden;
                }

                .notification-button::after {
                    content: '';
                    position: absolute;
                    top: 0;
                    left: -100%;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
                    transition: left 0.5s;
                }

                .notification-button:hover::after {
                    left: 100%;
                }

                .new-order-highlight {
                    background: linear-gradient(45deg, #fef3c7, #fde68a, #fef3c7);
                    background-size: 400% 400%;
                    animation: gradientShift 2s ease infinite;
                }

                @keyframes gradientShift {
                    0% {
                        background-position: 0% 50%;
                    }

                    50% {
                        background-position: 100% 50%;
                    }

                    100% {
                        background-position: 0% 50%;
                    }
                }

                .modal-backdrop {
                    backdrop-filter: blur(4px);
                    background-color: rgba(0, 0, 0, 0.6);
                }

                .modal-content {
                    animation: modalSlideIn 0.3s ease-out;
                }

                @keyframes modalSlideIn {
                    from {
                        opacity: 0;
                        transform: translateY(-30px) scale(0.95);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                }

                .loading-spinner {
                    border: 2px solid #f3f3f3;
                    border-top: 2px solid #3498db;
                    border-radius: 50%;
                    width: 20px;
                    height: 20px;
                    animation: spin 1s linear infinite;
                    display: inline-block;
                    margin-right: 10px;
                }

                @keyframes spin {
                    0% {
                        transform: rotate(0deg);
                    }

                    100% {
                        transform: rotate(360deg);
                    }
                }

                .status-badge {
                    position: relative;
                    display: inline-flex;
                    align-items: center;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }

                .status-badge::before {
                    content: '';
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    margin-right: 6px;
                    display: inline-block;
                }

                .status-badge.status-success::before {
                    background-color: #10b981;
                    box-shadow: 0 0 6px rgba(16, 185, 129, 0.5);
                }

                .status-badge.status-pending::before {
                    background-color: #f59e0b;
                    box-shadow: 0 0 6px rgba(245, 158, 11, 0.5);
                }

                .status-badge.status-failed::before {
                    background-color: #ef4444;
                    box-shadow: 0 0 6px rgba(239, 68, 68, 0.5);
                }

                .status-badge.status-processing::before {
                    background-color: #3b82f6;
                    box-shadow: 0 0 6px rgba(59, 130, 246, 0.5);
                    animation: pulse 2s infinite;
                }

                @keyframes pulse {
                    0% {
                        transform: scale(1);
                        opacity: 1;
                    }

                    50% {
                        transform: scale(1.2);
                        opacity: 0.7;
                    }

                    100% {
                        transform: scale(1);
                        opacity: 1;
                    }
                }
            </style>
        </div>
    @endvolt
</body>

</html>
