<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Note;
use App\Models\order_detail;
use App\Support\NotationAgent;

name('dashboard.courses');

/*
| Courses : un colis à porter d'un point à un autre.
|
| Cet écran cherchait ses courses dans la table clando, parmi les lignes
| « delivery_type = delivery ». Il n'en existe aucune : toutes les lignes clando
| sont des trajets, et l'écran restait vide depuis sa création.
|
| Une demande de course part de l'application cliente, passe par
| CoursierController@storeDeliveryOrder, et crée un order_detail — pas un clando.
| Elle se reconnaît à l'absence de panier et à la présence d'un point de départ,
| exactement comme sur le mur des commandes et dans l'historique de
| l'application. Trois écrans, une seule règle : dès qu'elles divergent, la même
| course se met à exister ici et pas là.
*/
new class extends Component {
    use WithPagination;

    public $search = '';
    public $statutFiltre = '';

    public const STATUTS = [
        'pending' => 'En attente',
        'waiting' => 'Colis prêt',
        'want' => 'Demandée',
        'take' => 'Prise en charge',
        'process' => 'En cours',
        'Success' => 'Livrée',
        'declin' => 'Refusée',
        'failed' => 'Échec',
    ];

    /**
     * Les courses, et rien qu'elles.
     *
     * Le point de départ est ce qui sépare une course d'une commande sans
     * panier : sur les données réelles, la moitié des commandes sans panier
     * n'ont aucun départ et ne sont donc pas des courses.
     */
    protected function requeteDeBase()
    {
        return order_detail::query()
            ->whereNull('id_cart')
            ->whereNotNull('depart')
            ->where('depart', '!=', '');
    }

    public function getCoursesProperty()
    {
        return $this->requeteDeBase()
            ->with(['user:id,name,phone', 'agent.user:id,name,phone'])
            ->when($this->search, function ($q) {
                $terme = '%' . $this->search . '%';
                $q->where(function ($sub) use ($terme) {
                    $sub->where('ref', 'like', $terme)
                        ->orWhere('address', 'like', $terme)
                        ->orWhere('depart', 'like', $terme)
                        ->orWhere('delivery_code', 'like', $terme)
                        ->orWhereHas('user', fn ($r) => $r->where('name', 'like', $terme));
                });
            })
            ->when($this->statutFiltre, fn ($q) => $q->where('status', $this->statutFiltre))
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * Appréciations des courses affichées, chargées en une requête.
     *
     * L'administrateur doit lire la note et le commentaire là où il regarde la
     * course, sans avoir à ouvrir un autre écran pour les retrouver.
     */
    public function getAppreciationsProperty()
    {
        return Note::with('client:id,name')
            ->whereIn('id_order', $this->courses->pluck('id'))
            ->get()
            ->keyBy('id_order');
    }

    public function getStatsProperty(): array
    {
        $notes = Note::whereIn('id_order', $this->requeteDeBase()->select('id'))->pluck('note')->countBy()->toArray();
        $bilan = NotationAgent::bilan($notes);

        return [
            'total' => $this->requeteDeBase()->count(),
            'en_cours' => $this->requeteDeBase()->whereIn('status', ['pending', 'waiting', 'want', 'take', 'process'])->count(),
            'livrees' => $this->requeteDeBase()->where('status', 'Success')->count(),
            'note' => $bilan['sur_cinq'],
            'notees' => $bilan['nombre'],
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

<x-layouts.app title="Courses">
    @volt
        <div>
            <x-ui.page-header title="Courses" subtitle="Colis portés d'un point à un autre, à la demande d'un client" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Courses" :value="$this->stats['total']" tone="brand"
                    icon="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a48.7 48.7 0 00-16.5 0" />

                <x-ui.stat label="En cours" :value="$this->stats['en_cours']" tone="warning"
                    icon="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Livrées" :value="$this->stats['livrees']" tone="success"
                    icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Satisfaction"
                    :value="$this->stats['note'] === null ? '—' : $this->stats['note'] . ' / 5'"
                    :hint="$this->stats['notees'] . ' course(s) notée(s)'" tone="accent"
                    icon="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.search model="search" placeholder="Référence, départ, destination ou client…" />

                <x-ui.select wire:model.live="statutFiltre" class="w-auto min-w-[12rem]">
                    <option value="">Tous les statuts</option>
                    @foreach (self::STATUTS as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="mt-4">
                <x-ui.table target="search,statutFiltre,gotoPage,previousPage,nextPage"
                    :headers="['Référence', 'Trajet', 'Client', 'Agent', 'Appréciation', 'Prix', 'Statut']">
                    @forelse ($this->courses as $course)
                        @php $appreciation = $this->appreciations[$course->id] ?? null; @endphp
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-sm font-semibold text-gray-900">
                                {{ $course->ref ?: '#' . $course->id }}
                                @if ($course->delivery_code)
                                    <p class="mt-0.5 text-[11px] font-normal text-gray-400">code {{ $course->delivery_code }}</p>
                                @endif
                            </td>

                            <td class="max-w-xs px-4 py-3 text-sm text-gray-600">
                                <p><span class="text-gray-400">De</span> {{ $course->depart }}</p>
                                <p><span class="text-gray-400">À</span> {{ $course->address ?: '—' }}</p>
                            </td>

                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $course->user?->name ?? '—' }}</p>
                                @if ($course->user?->phone)
                                    <p class="font-mono text-xs text-gray-500">{{ $course->user->phone }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm">
                                @if ($course->agent?->user)
                                    <p class="font-medium text-gray-900">{{ $course->agent->user->name }}</p>
                                    <p class="font-mono text-xs text-gray-500">{{ $course->agent->user->phone }}</p>
                                @else
                                    <span class="text-gray-400">Non attribuée</span>
                                @endif
                            </td>

                            <td class="max-w-xs px-4 py-3 text-sm">
                                @if ($appreciation)
                                    <p class="text-base" title="{{ \App\Support\NotationAgent::LIBELLES[$appreciation->note] ?? '' }}">
                                        {{ \App\Support\NotationAgent::EMOJIS[$appreciation->note] ?? '' }}
                                        <span class="align-middle text-xs text-gray-600">{{ \App\Support\NotationAgent::LIBELLES[$appreciation->note] ?? $appreciation->note }}</span>
                                    </p>
                                    @if ($appreciation->comment)
                                        <p class="mt-0.5 text-xs italic text-gray-500">« {{ $appreciation->comment }} »</p>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400">Pas encore notée</span>
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
                                        'pending', 'waiting', 'want' => 'warning',
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
                        <x-ui.empty :colspan="7" title="Aucune course"
                            message="Les demandes de course passées depuis l'application apparaîtront ici." />
                    @endforelse
                </x-ui.table>

                @if ($this->courses->hasPages())
                    <div class="mt-4">{{ $this->courses->links() }}</div>
                @endif
            </div>
        </div>
    @endvolt
</x-layouts.app>
