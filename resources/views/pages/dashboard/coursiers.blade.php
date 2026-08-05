<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Clando;
use App\Models\order_detail;

name('dashboard.coursiers');

/*
| Courses de coursier : un colis à déposer.
|
| Même mécanique qu'une course Clando, à ceci près qu'un colis est transporté et
| que la course naît d'une commande (createclandoorder). Elles se reconnaissent à
| leur id_order renseigné ; les trajets sans colis relèvent de l'écran Clando.
|
| Le service n'avait aucun écran : ni ici, ni ailleurs.
*/
new class extends Component {
    use WithPagination;

    public $search = '';
    public $statutFiltre = '';

    public const STATUTS = [
        'pending' => 'En attente',
        'want' => 'Demandée',
        'take' => 'Prise en charge',
        'process' => 'En cours',
        'Success' => 'Livrée',
        'declin' => 'Refusée',
        'failed' => 'Échec',
    ];

    /**
     * Courses de coursier, reconnues par delivery_type « delivery ».
     *
     * C'est createclandoorder qui pose cette valeur. id_order, qu'on aurait pu
     * croire suffisant, n'est renseigné sur aucune course en base : s'y fier
     * laissait cet écran vide.
     */
    protected function requeteDeBase()
    {
        return Clando::query()->where('delivery_type', 'delivery');
    }

    public function getCoursesProperty()
    {
        return $this->requeteDeBase()
            ->with(['users:id,name,phone', 'agent.user:id,name,phone'])
            ->when($this->search, function ($q) {
                $terme = '%' . $this->search . '%';
                $q->where(function ($sub) use ($terme) {
                    $sub->where('ref', 'like', $terme)
                        ->orWhere('destinationName', 'like', $terme)
                        ->orWhereHas('users', fn ($r) => $r->where('name', 'like', $terme));
                });
            })
            ->when($this->statutFiltre, fn ($q) => $q->where('status', $this->statutFiltre))
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * Commandes d'origine des courses affichées, chargées en une requête.
     *
     * clando.id_order ne porte pas de relation Eloquent : on résout donc les
     * commandes séparément plutôt que d'en interroger une par ligne.
     */
    public function getCommandesProperty()
    {
        return order_detail::whereIn('id', $this->courses->pluck('id_order')->filter())
            ->get(['id', 'ref', 'address', 'price', 'status'])
            ->keyBy('id');
    }

    public function getStatsProperty(): array
    {
        return [
            'total' => $this->requeteDeBase()->count(),
            'en_cours' => $this->requeteDeBase()->whereIn('status', ['pending', 'want', 'take', 'process'])->count(),
            'livrees' => $this->requeteDeBase()->where('status', 'Success')->count(),
            'ca' => (int) $this->requeteDeBase()->where('status', 'Success')->sum('price'),
        ];
    }

    public function changerStatut($id, $statut)
    {
        abort_unless(array_key_exists($statut, self::STATUTS), 422);

        $course = $this->requeteDeBase()->findOrFail($id);
        $course->status = $statut;
        $course->save();

        $this->dispatch('notify', ['message' => 'Statut mis à jour !', 'type' => 'success']);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
};
?>

<x-layouts.app title="Coursiers">
    @volt
        <div>
            <x-ui.page-header title="Coursiers" subtitle="Colis à déposer, rattachés à une commande" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Courses" :value="$this->stats['total']" tone="brand"
                    icon="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a48.7 48.7 0 00-16.5 0" />

                <x-ui.stat label="En cours" :value="$this->stats['en_cours']" tone="warning"
                    icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Livrées" :value="$this->stats['livrees']" tone="success"
                    icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Chiffre d'affaires"
                    :value="number_format($this->stats['ca'], 0, ',', ' ') . ' F'" tone="accent"
                    icon="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.search model="search" placeholder="Référence, destination ou client…" />

                <x-ui.select wire:model.live="statutFiltre" class="w-auto min-w-[12rem]">
                    <option value="">Tous les statuts</option>
                    @foreach (self::STATUTS as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="mt-4">
                <x-ui.table target="search,statutFiltre,gotoPage,previousPage,nextPage"
                    :headers="['Référence', 'Commande', 'Client', 'Destination', 'Agent', 'Prix', 'Statut']">
                    @forelse ($this->courses as $course)
                        @php $commande = $this->commandes[$course->id_order] ?? null; @endphp
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-sm font-semibold text-gray-900">
                                {{ $course->ref ?: '#' . $course->id }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($commande)
                                    <p class="font-mono text-xs text-brand-700">{{ $commande->ref }}</p>
                                    <p class="text-xs text-gray-500">{{ number_format((int) $commande->price, 0, ',', ' ') }} F</p>
                                @else
                                    {{-- Une course peut pointer vers une commande supprimée. --}}
                                    <span class="text-xs text-gray-400">Commande introuvable</span>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $course->users?->name ?? '—' }}</p>
                                @if ($course->users?->phone)
                                    <p class="font-mono text-xs text-gray-500">{{ $course->users->phone }}</p>
                                @endif
                            </td>

                            <td class="max-w-xs px-4 py-3 text-sm text-gray-600">
                                {{ $course->destinationName ?: ($commande->address ?? '—') }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                @if ($course->agent?->user)
                                    <p class="font-medium text-gray-900">{{ $course->agent->user->name }}</p>
                                    <p class="font-mono text-xs text-gray-500">{{ $course->agent->user->phone }}</p>
                                @else
                                    <span class="text-gray-400">Non attribuée</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 font-semibold tabular-nums text-gray-900">
                                {{ number_format((int) $course->price, 0, ',', ' ') }} F
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                @php
                                    $tone = match ($course->status) {
                                        'Success' => 'success',
                                        'failed', 'declin' => 'danger',
                                        'pending', 'want' => 'warning',
                                        default => 'info',
                                    };
                                @endphp
                                <x-ui.badge :tone="$tone">
                                    {{ self::STATUTS[$course->status] ?? $course->status }}
                                </x-ui.badge>

                                @if (! in_array($course->status, ['Success', 'failed', 'declin'], true))
                                    <div class="mt-1.5 flex gap-1">
                                        <button type="button" wire:click="changerStatut({{ $course->id }}, 'Success')"
                                                class="rounded bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white hover:bg-emerald-700">Livrée</button>
                                        <button type="button" wire:click="changerStatut({{ $course->id }}, 'failed')"
                                                class="rounded bg-red-600 px-2 py-1 text-[11px] font-bold text-white hover:bg-red-700">Annuler</button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty :colspan="7" title="Aucune course de coursier"
                            message="Les demandes de coursier passées depuis l'application apparaîtront ici." />
                    @endforelse
                </x-ui.table>

                @if ($this->courses->hasPages())
                    <div class="mt-4">{{ $this->courses->links() }}</div>
                @endif
            </div>
        </div>
    @endvolt
</x-layouts.app>
