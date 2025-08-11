<?php
use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\Payment;

name('dashboard.transactions');

new class extends Component {
    public $search = '';
    public $showDetailsModal = false;
    public $showStatusModal = false;
    public $transactionId = null;
    public $status = '';
    public $transactionDetails = null;

    public function getTransactionsProperty()
    {
        return Payment::with('user')
            ->when($this->search, function ($query) {
                $query->where('num_transaction', 'like', '%' . $this->search . '%')
                      ->orWhere('id', 'like', '%' . $this->search . '%');
            })
            ->orderBy('id', 'desc')
            ->paginate(10);
    }

    public function getTotalTransactionsProperty()
    {
        return Payment::count();
    }

    public function getTotalAmountProperty()
    {
        return Payment::sum('amount');
    }

    public function getSuccessfulTransactionsProperty()
    {
        return Payment::where('status', 'Success')->count();
    }

    public function openDetailsModal($id)
    {
        $this->transactionDetails = Payment::with('user')->findOrFail($id);
        $this->showDetailsModal = true;
    }

    public function closeDetailsModal()
    {
        $this->showDetailsModal = false;
        $this->transactionDetails = null;
    }

    public function openStatusModal($id)
    {
        $this->transactionId = $id;
        $transaction = Payment::findOrFail($id);
        $this->status = $transaction->status;
        $this->showStatusModal = true;
    }

    public function closeStatusModal()
    {
        $this->showStatusModal = false;
        $this->reset(['transactionId', 'status']);
        $this->resetValidation();
    }

    public function updateTransactionStatus()
    {
        $this->validate([
            'status' => 'required|in:pending,Success,failed',
        ]);

        $transaction = Payment::findOrFail($this->transactionId);
        $transaction->status = $this->status;
        $transaction->save();

        $this->dispatch('notify', ['message' => 'Statut de la transaction modifié avec succès !', 'type' => 'success']);
        $this->closeStatusModal();
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
                    <input type="text" id="search" wire:model.live="search" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" placeholder="Rechercher une transaction (ID ou numéro)" />
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
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Transactions</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->totalTransactions }}</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Montant Total</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ number_format($this->totalAmount, 2, ',', ' ') }} FCFA</p>
                </div>
                <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Transactions Réussies</h3>
                    <p class="text-3xl font-bold text-indigo-600">{{ $this->successfulTransactions }}</p>
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
                            <th class="py-3 px-4 text-gray-800">Statut</th>
                            <th class="py-3 px-4 text-gray-800">Date</th>
                            <th class="py-3 px-4 text-gray-800">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->transactions as $transaction)
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4">{{ $transaction->id }}</td>
                                <td class="py-3 px-4">{{ $transaction->user ? $transaction->user->name : 'N/A' }}</td>
                                <td class="py-3 px-4">{{ number_format($transaction->amount, 2, ',', ' ') }} FCFA</td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $transaction->status === 'Success' ? 'bg-green-100 text-green-800' :
                                           ($transaction->status === 'failed' ? 'bg-red-100 text-red-800' :
                                           'bg-yellow-100 text-yellow-800') }}">
                                        {{ $transaction->status === 'pending' ? 'En attente' :
                                           ($transaction->status === 'Success' ? 'Réussie' : 'Échouée') }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">{{ optional($transaction->created_at)->format('Y-m-d') }}</td>
                                <td class="py-3 px-4">
                                    <button wire:click="openDetailsModal({{ $transaction->id }})" class="text-blue-600 hover:text-blue-800 mr-2" title="Voir les détails">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <button wire:click="openStatusModal({{ $transaction->id }})" class="text-gray-600 hover:text-gray-800" title="Modifier le statut">
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
                    {{ $this->transactions->links() }}
                </div>
            </div>

            <!-- Modal pour visualiser les détails -->
            <div wire:model="showDetailsModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showDetailsModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-4xl h-auto max-h-[75vh] overflow-y-auto">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Détails de la Transaction</h2>
                    @if ($transactionDetails)
                        <div class="space-y-4">
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID</label>
                                    <p class="text-sm">{{ $transactionDetails->id }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Client</label>
                                    <p class="text-sm">{{ $transactionDetails->user ? $transactionDetails->user->name : 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Montant</label>
                                    <p class="text-sm">{{ number_format($transactionDetails->amount, 2, ',', ' ') }} FCFA</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Statut</label>
                                    <p class="text-sm">
                                        {{ $transactionDetails->status === 'pending' ? 'En attente' :
                                           ($transactionDetails->status === 'Success' ? 'Réussie' : 'Échouée') }}
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Date de création</label>
                                    <p class="text-sm">{{ optional($transactionDetails->created_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Date de mise à jour</label>
                                    <p class="text-sm">{{ optional($transactionDetails->updated_at)->format('Y-m-d H:i:s') ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID Commande</label>
                                    <p class="text-sm">{{ $transactionDetails->id_order_details ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID Utilisateur</label>
                                    <p class="text-sm">{{ $transactionDetails->id_user ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">ID Opérateur</label>
                                    <p class="text-sm">{{ $transactionDetails->id_operator ?? 'N/A' }}</p>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-gray-700 text-sm mb-1">Numéro de Transaction</label>
                                    <p class="text-sm">{{ $transactionDetails->num_transaction ?? 'N/A' }}</p>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-gray-700 text-sm mb-1">Paytoken</label>
                                    <p class="text-sm">{{ $transactionDetails->paytoken ?? 'N/A' }}</p>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-gray-700 text-sm mb-1">Access Token</label>
                                    <p class="text-sm">{{ $transactionDetails->access_token ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Scope</label>
                                    <p class="text-sm">{{ $transactionDetails->scope ?? 'N/A' }}</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm mb-1">Expires In</label>
                                    <p class="text-sm">{{ $transactionDetails->expires_in ?? 'N/A' }}</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeDetailsModal" class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600">Fermer</button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Modal pour modifier le statut -->
            <div wire:model="showStatusModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center {{ $showStatusModal ? '' : 'hidden' }}">
                <div class="bg-white rounded-lg p-6 w-full max-w-md">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Modifier le Statut de la Transaction</h2>
                    <form wire:submit.prevent="updateTransactionStatus" class="space-y-4">
                        <div>
                            <label class="block text-gray-700 text-sm mb-1" for="status">Statut <span class="text-red-500">*</span></label>
                            <select id="status" wire:model="status" class="w-full p-1 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500" required>
                                <option value="">Sélectionner</option>
                                <option value="pending">En attente</option>
                                <option value="Success">Réussie</option>
                                <option value="failed">Échouée</option>
                            </select>
                            @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end mt-4">
                            <button type="button" wire:click="closeStatusModal" class="bg-gray-500 text-white py-1 px-3 text-sm rounded-lg hover:bg-gray-600 mr-2">Annuler</button>
                            <button type="submit" class="bg-indigo-600 text-white py-1 px-3 text-sm rounded-lg hover:bg-indigo-700">Modifier</button>
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
