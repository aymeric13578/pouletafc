<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\order_detail;
use App\Models\User;

name('dashboard.customers');

/*
| Clients ayant déjà acheté, classés par montant dépensé.
|
| L'écran affichait « 1 200 clients », « 980 actifs », « 120 nouveaux » : trois
| nombres écrits en dur dans le gabarit, qui n'avaient jamais rien lu en base.
| On ne pouvait donc savoir ni qui achète, ni combien, ni depuis quand.
*/
new class extends Component {
    use WithPagination;

    public $search = '';

    /** Client dont on déplie l'historique. */
    public $clientOuvert = null;

    /**
     * Ce qui compte comme versé.
     *
     * Une commande annulée ou refusée n'a rien rapporté ; une commande encore
     * en cours non plus, tant qu'elle n'est pas remise. Confondre les deux
     * gonflerait le total d'un client qui n'a peut-être jamais payé.
     */
    public const ENCAISSEES = ['Success'];

    /** Commandes encore en vie, comptées à part. */
    public const EN_COURS = ['pending', 'waiting', 'want', 'take', 'process'];

    /**
     * Agrégat par client, en une seule requête.
     *
     * Compter les commandes client par client produirait autant de requêtes que
     * de lignes affichées.
     */
    protected function requeteDeBase()
    {
        $encaissees = "'" . implode("','", self::ENCAISSEES) . "'";
        $enCours = "'" . implode("','", self::EN_COURS) . "'";

        return order_detail::query()
            ->whereNotNull('id_user')
            ->selectRaw("
                id_user,
                COUNT(*) as commandes,
                SUM(CASE WHEN status IN ($encaissees) THEN 1 ELSE 0 END) as livrees,
                SUM(CASE WHEN status IN ($enCours) THEN 1 ELSE 0 END) as en_cours,
                SUM(CASE WHEN status IN ($encaissees) THEN price ELSE 0 END) as verse,
                MAX(created_at) as derniere,
                MIN(created_at) as premiere
            ")
            ->groupBy('id_user');
    }

    public function getClientsProperty()
    {
        $lignes = $this->requeteDeBase()
            ->orderByDesc('verse')
            ->paginate(20);

        // Les noms en une requête : la table des commandes ne les porte pas.
        $comptes = User::whereIn('id', $lignes->pluck('id_user'))
            ->get(['id', 'name', 'phone', 'whatsapp', 'email', 'created_at'])
            ->keyBy('id');

        $lignes->getCollection()->transform(function ($ligne) use ($comptes) {
            $ligne->compte = $comptes[$ligne->id_user] ?? null;

            return $ligne;
        });

        if ($this->search) {
            $terme = mb_strtolower($this->search);

            $lignes->setCollection(
                $lignes->getCollection()->filter(function ($ligne) use ($terme) {
                    $compte = $ligne->compte;

                    return $compte && (
                        str_contains(mb_strtolower((string) $compte->name), $terme)
                        || str_contains((string) $compte->phone, $terme)
                        || str_contains(mb_strtolower((string) $compte->email), $terme)
                    );
                })
            );
        }

        return $lignes;
    }

    /** Historique du client déplié, chargé seulement à l'ouverture. */
    public function getHistoriqueProperty()
    {
        if (! $this->clientOuvert) {
            return collect();
        }

        return order_detail::where('id_user', $this->clientOuvert)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'ref', 'address', 'price', 'status', 'created_at', 'id_cart', 'depart']);
    }

    public function getStatsProperty(): array
    {
        $encaissees = "'" . implode("','", self::ENCAISSEES) . "'";

        $global = order_detail::whereNotNull('id_user')
            ->selectRaw("
                COUNT(DISTINCT id_user) as clients,
                COUNT(*) as commandes,
                SUM(CASE WHEN status IN ($encaissees) THEN price ELSE 0 END) as verse
            ")
            ->first();

        $clients = (int) ($global->clients ?? 0);
        $verse = (int) ($global->verse ?? 0);

        return [
            'clients' => $clients,
            'commandes' => (int) ($global->commandes ?? 0),
            'verse' => $verse,
            // Panier moyen par client, et non par commande : c'est ce qu'un
            // client rapporte sur toute sa vie, la question que pose cet écran.
            'moyen' => $clients === 0 ? 0 : (int) round($verse / $clients),
        ];
    }

    public function basculer($idUser): void
    {
        $this->clientOuvert = $this->clientOuvert === $idUser ? null : $idUser;
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
};
?>

<x-layouts.app title="Clients">
    @volt
        <div>
            <x-ui.page-header title="Clients"
                subtitle="Ceux qui ont déjà acheté, classés par montant versé" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Clients acheteurs" :value="$this->stats['clients']" tone="brand"
                    icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />

                <x-ui.stat label="Commandes" :value="$this->stats['commandes']" tone="info"
                    icon="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A1.125 1.125 0 0119.75 22H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />

                <x-ui.stat label="Total versé"
                    :value="number_format($this->stats['verse'], 0, ',', ' ') . ' F'" tone="success"
                    hint="commandes livrées uniquement"
                    icon="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Versé par client"
                    :value="number_format($this->stats['moyen'], 0, ',', ' ') . ' F'" tone="accent"
                    hint="moyenne sur toute la relation"
                    icon="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.search model="search" placeholder="Nom, téléphone ou e-mail…" />
            </div>

            <div class="mt-4">
                <x-ui.table target="search,gotoPage,previousPage,nextPage"
                    :headers="['Rang', 'Client', 'Commandes', 'Livrées', 'Total versé', 'Dernière', '']">
                    @forelse ($this->clients as $rang => $ligne)
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 text-sm font-bold text-gray-500">
                                @if ($this->clients->currentPage() === 1 && $rang === 0)
                                    <span title="Meilleur client">🏆</span>
                                @else
                                    {{ $this->clients->firstItem() + $rang }}
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">
                                    {{ $ligne->compte?->name ?? 'Compte #' . $ligne->id_user }}
                                </p>
                                @if ($ligne->compte?->phone)
                                    <p class="font-mono text-xs text-gray-500">{{ $ligne->compte->phone }}</p>
                                @endif
                                @if ($ligne->compte?->email)
                                    <p class="text-xs text-gray-400">{{ $ligne->compte->email }}</p>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-sm text-gray-700">
                                {{ (int) $ligne->commandes }}
                                @if ((int) $ligne->en_cours > 0)
                                    <span class="ml-1 text-xs text-amber-600">
                                        dont {{ (int) $ligne->en_cours }} en cours
                                    </span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-sm text-gray-700">
                                {{ (int) $ligne->livrees }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-gray-900">
                                {{ number_format((int) $ligne->verse, 0, ',', ' ') }} F
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">
                                {{ $ligne->derniere ? \Illuminate\Support\Carbon::parse($ligne->derniere)->timezone('Africa/Douala')->format('d/m/Y') : '—' }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" wire:click="basculer({{ $ligne->id_user }})"
                                        class="text-xs font-semibold text-brand-600 hover:underline">
                                    {{ $clientOuvert === $ligne->id_user ? 'Masquer' : 'Historique' }}
                                </button>
                            </td>
                        </tr>

                        @if ($clientOuvert === $ligne->id_user)
                            <tr>
                                <td colspan="7" class="bg-gray-50 px-4 py-4">
                                    <p class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-600">
                                        Historique de {{ $ligne->compte?->name ?? 'ce client' }}
                                    </p>

                                    @forelse ($this->historique as $commande)
                                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 py-2 last:border-0">
                                            <div class="min-w-0">
                                                <p class="font-mono text-xs font-semibold text-gray-800">
                                                    {{ $commande->ref }}
                                                    @if ($commande->id_cart === null && trim((string) $commande->depart) !== '')
                                                        <span class="ml-1 rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold text-orange-700">Course</span>
                                                    @endif
                                                </p>
                                                <p class="truncate text-xs text-gray-500">{{ $commande->address ?: '—' }}</p>
                                            </div>

                                            <div class="flex items-center gap-3">
                                                <span class="text-xs text-gray-400">
                                                    {{ $commande->created_at?->timezone('Africa/Douala')->format('d/m/Y') }}
                                                </span>
                                                <x-ui.badge :tone="$commande->status === 'Success' ? 'success' : (in_array($commande->status, ['failed', 'declin'], true) ? 'danger' : 'warning')">
                                                    {{ $commande->status }}
                                                </x-ui.badge>
                                                <span class="tabular-nums text-sm font-semibold text-gray-900">
                                                    {{ number_format((int) $commande->price, 0, ',', ' ') }} F
                                                </span>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-gray-500">Aucune commande.</p>
                                    @endforelse
                                </td>
                            </tr>
                        @endif
                    @empty
                        <x-ui.empty :colspan="7" title="Aucun client"
                            message="Les clients apparaîtront ici dès leur première commande." />
                    @endforelse
                </x-ui.table>

                @if ($this->clients->hasPages())
                    <div class="mt-4">{{ $this->clients->links() }}</div>
                @endif
            </div>
        </div>
    @endvolt
</x-layouts.app>
