<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\IncidentSecurite;

name('dashboard.securite');

/*
| Page Sécurité : ce que produisent les boutons "Enregistrer" et "Signaler"
| de l'écran de course (clando.dart). Un signalement alerte déjà en direct
| le mur (Board.jsx, AlerteSecuriteOverlay) — cette page-ci est la trace qui
| reste après coup, pour les deux types d'incident, avec de quoi
| réellement enquêter (qui, sur quelle course, et l'enregistrement audio
| s'il y en a un).
*/
new class extends Component {
    use WithPagination;

    public string $filtreType = '';
    public string $filtreStatut = '';

    public const TYPES = [
        IncidentSecurite::SIGNALEMENT => 'Signalement',
        IncidentSecurite::ENREGISTREMENT => 'Enregistrement',
    ];

    public const STATUTS = [
        IncidentSecurite::NOUVEAU => 'Nouveau',
        IncidentSecurite::VU => 'Vu',
        IncidentSecurite::TRAITE => 'Traité',
    ];

    public function getIncidentsProperty()
    {
        return IncidentSecurite::query()
            ->with(['client:id,name,last_name,phone', 'agent:id,name,last_name,phone'])
            ->when($this->filtreType, fn ($q) => $q->where('type', $this->filtreType))
            ->when($this->filtreStatut, fn ($q) => $q->where('statut', $this->filtreStatut))
            ->orderByDesc('id')
            ->paginate(15);
    }

    public function getStatsProperty(): array
    {
        return [
            'nouveaux' => IncidentSecurite::where('statut', IncidentSecurite::NOUVEAU)->count(),
            'signalements' => IncidentSecurite::where('type', IncidentSecurite::SIGNALEMENT)->count(),
            'enregistrements' => IncidentSecurite::where('type', IncidentSecurite::ENREGISTREMENT)->count(),
        ];
    }

    public function marquerVu(int $id): void
    {
        IncidentSecurite::whereKey($id)->update(['statut' => IncidentSecurite::VU]);
        $this->dispatch('notify', ['message' => 'Incident marqué comme vu.', 'type' => 'success']);
    }

    public function marquerTraite(int $id): void
    {
        IncidentSecurite::whereKey($id)->update(['statut' => IncidentSecurite::TRAITE]);
        $this->dispatch('notify', ['message' => 'Incident marqué comme traité.', 'type' => 'success']);
    }

    public function updatedFiltreType()
    {
        $this->resetPage();
    }

    public function updatedFiltreStatut()
    {
        $this->resetPage();
    }
};
?>

<x-layouts.app title="Sécurité">
    @volt
        {{-- Un signalement non traité mérite un coup d'œil régulier même si
             personne ne rafraîchit la page — même intervalle que le mur
             (Board.jsx) pour la même raison : une alerte réelle ne doit pas
             attendre qu'un admin pense à recharger. --}}
        <div wire:poll.10s>
            <x-ui.page-header title="Sécurité" subtitle="Signalements et enregistrements déclenchés depuis l'écran de course" />

            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.stat label="Incidents non traités" :value="$this->stats['nouveaux']" tone="danger"
                    icon="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />

                <x-ui.stat label="Signalements" :value="$this->stats['signalements']" tone="warning"
                    icon="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />

                <x-ui.stat label="Enregistrements" :value="$this->stats['enregistrements']" tone="brand"
                    icon="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.select wire:model.live="filtreType" class="w-auto min-w-[10rem]">
                    <option value="">Tous les types</option>
                    @foreach (self::TYPES as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select wire:model.live="filtreStatut" class="w-auto min-w-[10rem]">
                    <option value="">Tous les statuts</option>
                    @foreach (self::STATUTS as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="mt-4">
                <x-ui.table target="filtreType,filtreStatut,gotoPage,previousPage,nextPage"
                    :headers="['Type', 'Course', 'Client', 'Agent', 'Preuve', 'Reçu', 'Statut', 'Actions']">
                    @forelse ($this->incidents as $incident)
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <x-ui.badge :tone="$incident->type === \App\Models\IncidentSecurite::SIGNALEMENT ? 'danger' : 'brand'">
                                    {{ self::TYPES[$incident->type] ?? $incident->type }}
                                </x-ui.badge>
                            </td>

                            <td class="px-4 py-3 font-mono text-sm text-gray-900">
                                {{ $incident->id_clando ? '#' . $incident->id_clando : ($incident->id_order ? 'cmd #' . $incident->id_order : '—') }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                <p class="font-medium text-gray-900">{{ $incident->client?->name ?? '—' }}</p>
                                @if ($incident->client?->phone)
                                    <p class="font-mono text-xs text-gray-500">{{ $incident->client->phone }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm">
                                <p class="font-medium text-gray-900">{{ $incident->agent?->name ?? '—' }}</p>
                                @if ($incident->agent?->phone)
                                    <p class="font-mono text-xs text-gray-500">{{ $incident->agent->phone }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                @if ($incident->audio_path)
                                    <audio controls preload="none" class="h-8 w-56"
                                        src="{{ route('dashboard.securite.audio', $incident) }}"></audio>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">
                                {{ $incident->created_at?->diffForHumans() }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                @php
                                    $tone = match ($incident->statut) {
                                        \App\Models\IncidentSecurite::TRAITE => 'success',
                                        \App\Models\IncidentSecurite::VU => 'info',
                                        default => 'danger',
                                    };
                                @endphp
                                <x-ui.badge :tone="$tone">{{ self::STATUTS[$incident->statut] ?? $incident->statut }}</x-ui.badge>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex gap-1">
                                    @if ($incident->statut === \App\Models\IncidentSecurite::NOUVEAU)
                                        <button type="button" wire:click="marquerVu({{ $incident->id }})"
                                                class="rounded bg-slate-600 px-2 py-1 text-[11px] font-bold text-white hover:bg-slate-700">Vu</button>
                                    @endif
                                    @if ($incident->statut !== \App\Models\IncidentSecurite::TRAITE)
                                        <button type="button" wire:click="marquerTraite({{ $incident->id }})"
                                                class="rounded bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white hover:bg-emerald-700">Traité</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty :colspan="8" title="Aucun incident"
                            message="Les signalements et enregistrements déclenchés depuis l'écran de course apparaîtront ici." />
                    @endforelse
                </x-ui.table>

                @if ($this->incidents->hasPages())
                    <div class="mt-4">{{ $this->incidents->links() }}</div>
                @endif
            </div>
        </div>
    @endvolt
</x-layouts.app>
