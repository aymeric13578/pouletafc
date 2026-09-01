<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\WithdrawalRequest;

name('dashboard.retraits');

/**
 * Validation des demandes de retrait des agents (bouton "Demander un
 * retrait", finances de l'app agent). Ne déclenche aucun virement : valider
 * ici confirme seulement que quelqu'un a pris contact avec l'agent, comme
 * l'app le lui promet au moment de la demande.
 */
new class extends Component {
    public $search = '';

    public function valider($id)
    {
        $demande = WithdrawalRequest::findOrFail($id);
        $demande->update(['status' => 'validated', 'validated_at' => now()]);

        // Double écriture Phase 1 (App\Support\LivreDeComptes) : le retrait
        // validé débite le compte de l'agent au livre, en parallèle de
        // Fonction::solde() qui le soustrait déjà de son côté.
        app(\App\Support\LivreDeComptes::class)->retrait(
            (int) $demande->id_agent,
            (float) $demande->amount,
            (int) $demande->id,
        );

        $this->dispatch('notify', ['message' => 'Demande de retrait validée.', 'type' => 'success']);
    }

    public function getDemandesProperty()
    {
        return WithdrawalRequest::with(['agent' => fn ($q) => $q->select('id', 'id_user', 'agent_name', 'phone')])
            ->when($this->search, function ($query) {
                $query->whereHas('agent', fn ($q) => $q->where('agent_name', 'like', '%' . $this->search . '%'));
            })
            ->orderByRaw("status = 'pending' desc")
            ->orderByDesc('id')
            ->get();
    }

    public function getEnAttenteCountProperty()
    {
        return WithdrawalRequest::where('status', 'pending')->count();
    }

    public function getTotalEnAttenteProperty()
    {
        return WithdrawalRequest::where('status', 'pending')->sum('amount');
    }
};
?>

<x-layouts.app>
    @volt
        <div>
            <div class="container mx-auto px-2 mt-6">

                <!-- Barre de recherche -->
                <form class="flex items-center max-w-lg mx-auto mb-6">
                    <label for="search" class="sr-only">Rechercher</label>
                    <div class="relative w-full">
                        <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                            <svg class="w-4 h-4 text-gray-500" aria-hidden="true"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 21 21">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11.15 5.6h.01m3.337 1.913h.01m-6.979 0h.01M5.541 11h.01M15 15h2.706a1.957 1.957 0 0 0 1.883-1.325A9 9 0 1 0 2.043 11.89 9.1 9.1 0 0 0 7.2 19.1a8.62 8.62 0 0 0 3.769.9A2.013 2.013 0 0 0 13 18v-.857A2.034 2.034 0 0 1 15 15Z" />
                            </svg>
                        </div>
                        <input type="text" id="search" wire:model.live="search"
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full ps-10 p-2.5"
                            placeholder="Rechercher un agent" />
                    </div>
                </form>

                <!-- Cartes de statistiques -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Demandes en attente</h3>
                        <p class="text-3xl font-bold text-amber-500">{{ $this->enAttenteCount }}</p>
                    </div>
                    <div class="bg-white rounded-2xl shadow-lg p-6 text-center transition transform hover:-translate-y-2 hover:shadow-2xl">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Montant en attente</h3>
                        <p class="text-3xl font-bold text-indigo-600">{{ number_format($this->totalEnAttente, 0, ',', ' ') }} F</p>
                    </div>
                </div>

                {{--
                  L'API v1.0 n'a aucune authentification (voir CLAUDE.md règle 8) :
                  une demande de retrait — numéro Orange Money compris — peut donc
                  être déposée par n'importe quel appelant se faisant passer pour
                  l'agent, sans passer par l'application. La confirmation
                  biométrique de l'app ne protège que contre quelqu'un qui aurait
                  le téléphone de l'agent en main, jamais contre un appel direct à
                  l'API. L'appel téléphonique de vérification reste donc le seul
                  vrai contrôle avant de verser quoi que ce soit.
                --}}
                <div class="mb-6 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <p class="text-sm text-amber-900">
                        <span class="font-semibold">Vérifiez le numéro de vive voix avant tout dépôt.</span>
                        Le montant et le numéro Orange Money affichés sont ceux <em>déclarés dans la demande</em> :
                        ils ne prouvent pas à eux seuls que la demande vient bien de l'agent. Appelez-le au numéro
                        de son compte (colonne « Téléphone ») pour confirmer avant de payer.
                    </p>
                </div>

                <!-- Tableau des demandes -->
                <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600">Agent</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600">Téléphone</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600">Montant</th>
                                {{-- Comment payer : sans ces deux colonnes, il fallait rappeler
                                     l'agent pour le savoir, ce que la demande dit maintenant. --}}
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600">Versement</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600">Demandée le</th>
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-600">Statut</th>
                                <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-gray-600">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($this->demandes as $demande)
                                <tr wire:key="retrait-{{ $demande->id }}" class="{{ $demande->status === 'pending' ? 'bg-amber-50/40' : '' }}">
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $demande->agent?->agent_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $demande->agent?->phone ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm font-bold text-indigo-700">{{ number_format($demande->amount, 0, ',', ' ') }} F</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($demande->mode === 'om')
                                            <span class="rounded-full bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-700">Orange Money</span>
                                            {{-- Le numéro à créditer n'est pas forcément celui du
                                                 compte agent : c'est celui saisi dans la demande. --}}
                                            <div class="mt-1 font-mono text-xs text-gray-700">{{ $demande->phone ?? '—' }}</div>
                                        @else
                                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Espèces</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">{{ $demande->created_at?->format('d/m/Y H:i') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold
                                            {{ $demande->status === 'validated' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ $demande->status === 'validated' ? 'Validée' : 'En attente' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($demande->status === 'pending')
                                            <button wire:click="valider({{ $demande->id }})"
                                                    wire:confirm="Confirmer avoir contacté cet agent et validé son retrait ?"
                                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                                Valider
                                            </button>
                                        @else
                                            <span class="text-xs text-gray-400">{{ $demande->validated_at?->format('d/m/Y H:i') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
                                        Aucune demande de retrait pour le moment.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endvolt
</x-layouts.app>
