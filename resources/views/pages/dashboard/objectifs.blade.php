<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\GoalCampaign;
use App\Models\GoalEnrollment;
use App\Models\GoalProgress;
use App\Support\ObjectifProgression;

name('dashboard.objectifs');

/*
| Objectifs et primes.
|
| L'application agent (écran « Objectifs ») lit getGoalCampaigns/pose
| enrollGoalCampaign, mais rien ne permettait de créer une campagne : les
| tables existaient, sans aucun écran pour les remplir. Cette page couvre le
| cycle de vie décrit dans le document de conception du 2026-08-23 : brouillon
| → publiée → clôturée (ou annulée), avec le modèle tout-ou-rien (un agent
| choisit une seule option, l'engagement se verrouille à la première course
| comptée, 9 sur 10 ne rapporte rien).
|
| Le calcul de progression vit dans App\Support\ObjectifProgression, partagé
| avec GoalController (application agent) — jamais dupliqué ici.
*/
new class extends Component {
    use WithPagination;

    public $showModal = false;
    public $showDetailModal = false;
    public $campagneDetailId = null;

    public $title = '';
    public $metric = 'rides';
    public $rideKind = '';
    public $startsAt = '';
    public $endsAt = '';
    public $enrollmentClosesAt = '';
    public $options = [
        ['label' => '', 'threshold' => '', 'reward' => ''],
        ['label' => '', 'threshold' => '', 'reward' => ''],
    ];

    public function getCampagnesProperty()
    {
        return GoalCampaign::withCount('enrollments')
            ->with('options')
            ->orderByDesc('id')
            ->paginate(10);
    }

    public function getStatsProperty(): array
    {
        return [
            'actives' => GoalCampaign::where('status', 'running')->count(),
            'engagements' => GoalEnrollment::count(),
            'en_attente' => GoalProgress::whereNotNull('achieved_at')->whereNull('paid_at')->count(),
            'a_verser' => (int) GoalProgress::whereNotNull('achieved_at')->whereNull('paid_at')->sum('amount_due'),
        ];
    }

    public function addOption(): void
    {
        $this->options[] = ['label' => '', 'threshold' => '', 'reward' => ''];
    }

    public function removeOption(int $index): void
    {
        if (count($this->options) <= 2) {
            return; // Un choix seul n'est pas un choix — voir le document de conception.
        }
        unset($this->options[$index]);
        $this->options = array_values($this->options);
    }

    public function openCreateModal(): void
    {
        $this->reset(['title', 'rideKind', 'startsAt', 'endsAt', 'enrollmentClosesAt']);
        $this->metric = 'rides';
        $this->options = [
            ['label' => '', 'threshold' => '', 'reward' => ''],
            ['label' => '', 'threshold' => '', 'reward' => ''],
        ];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function save(): void
    {
        $valide = $this->validate([
            'title' => 'required|string|max:191',
            'metric' => 'required|in:rides,active_days',
            'rideKind' => 'nullable|in:clando,delivery,courier',
            'startsAt' => 'required|date',
            'endsAt' => 'required|date|after:startsAt',
            'enrollmentClosesAt' => 'required|date|after_or_equal:startsAt|before_or_equal:endsAt',
            'options' => 'required|array|min:2',
            'options.*.label' => 'required|string|max:100',
            'options.*.threshold' => 'required|integer|min:1',
            'options.*.reward' => 'required|integer|min:1',
        ], [], [
            'startsAt' => 'date de début',
            'endsAt' => 'date de fin',
            'enrollmentClosesAt' => "fermeture de l'inscription",
        ]);

        // Seuils et montants strictement croissants — sinon une option n'a
        // aucune raison d'exister (règle du document de conception).
        $seuils = array_column($valide['options'], 'threshold');
        $montants = array_column($valide['options'], 'reward');
        if ($seuils !== collect($seuils)->unique()->sort()->values()->all()
            || $montants !== collect($montants)->unique()->sort()->values()->all()) {
            $this->addError('options', 'Les seuils et les montants doivent être strictement croissants, dans l\'ordre des options.');
            return;
        }

        $campagne = GoalCampaign::create([
            'title' => $valide['title'],
            'metric' => $valide['metric'],
            'ride_kind' => $valide['rideKind'] ?: null,
            'starts_at' => $valide['startsAt'],
            'ends_at' => $valide['endsAt'],
            'enrollment_closes_at' => $valide['enrollmentClosesAt'],
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        foreach ($valide['options'] as $i => $option) {
            $campagne->options()->create([
                'label' => $option['label'],
                'threshold' => $option['threshold'],
                'reward' => $option['reward'],
                'position' => $i,
            ]);
        }

        $this->dispatch('notify', ['message' => 'Campagne créée en brouillon.', 'type' => 'success']);
        $this->closeModal();
    }

    public function publier(int $id): void
    {
        $campagne = GoalCampaign::findOrFail($id);
        if ($campagne->status !== 'draft') {
            return;
        }
        $campagne->update(['status' => 'running']);
        $this->dispatch('notify', ['message' => 'Campagne publiée : visible dans l\'application agent.', 'type' => 'success']);
    }

    public function cloturer(int $id): void
    {
        $campagne = GoalCampaign::findOrFail($id);
        if ($campagne->status !== 'running') {
            return;
        }
        ObjectifProgression::figerALaCloture($campagne);
        $campagne->update(['status' => 'closed']);
        $this->dispatch('notify', ['message' => 'Campagne clôturée : progression figée pour tous les agents engagés.', 'type' => 'success']);
    }

    public function annuler(int $id): void
    {
        $campagne = GoalCampaign::findOrFail($id);
        if (! in_array($campagne->status, ['draft', 'running'], true)) {
            return;
        }
        $campagne->update(['status' => 'cancelled']);
        $this->dispatch('notify', ['message' => 'Campagne annulée.', 'type' => 'success']);
    }

    public function supprimer(int $id): void
    {
        $campagne = GoalCampaign::findOrFail($id);
        if ($campagne->status !== 'draft') {
            return; // Une campagne déjà vue par un agent ne se supprime pas, elle s'annule.
        }
        $campagne->delete();
        $this->dispatch('notify', ['message' => 'Brouillon supprimé.', 'type' => 'success']);
    }

    public function voirDetail(int $id): void
    {
        $this->campagneDetailId = $id;
        $this->showDetailModal = true;
    }

    public function fermerDetail(): void
    {
        $this->showDetailModal = false;
        $this->campagneDetailId = null;
    }

    public function getCampagneDetailProperty(): ?GoalCampaign
    {
        return $this->campagneDetailId ? GoalCampaign::with('options')->find($this->campagneDetailId) : null;
    }

    public function getEngagementsDetailProperty()
    {
        if (! $this->campagneDetailId) {
            return collect();
        }

        $campagne = $this->campagneDetail;
        $progressions = GoalProgress::where('campaign_id', $this->campagneDetailId)->get()->keyBy('agent_id');

        return GoalEnrollment::where('campaign_id', $this->campagneDetailId)
            ->with('option')
            ->get()
            ->map(function (GoalEnrollment $e) use ($campagne, $progressions) {
                $agent = \App\Models\User::find($e->agent_id);
                $progres = $progressions->get($e->agent_id);

                // Campagne encore active : on montre la progression en direct,
                // pas seulement la dernière valeur enregistrée.
                $vue = $campagne->status === 'running'
                    ? ObjectifProgression::calculer($campagne, $e->agent_id, $e->option_id)
                    : null;

                return [
                    'agent_id' => $e->agent_id,
                    'agent' => $agent?->name ?? ('#' . $e->agent_id),
                    'option' => $e->option?->label ?? '—',
                    'progress' => $vue['progress'] ?? $progres?->progress ?? 0,
                    'target' => $vue['target'] ?? $e->option?->threshold ?? 0,
                    'achieved' => $vue['achieved'] ?? ($progres?->achieved_at !== null),
                    'amount_due' => $progres?->amount_due,
                    'paid_at' => $progres?->paid_at,
                    'locked' => $e->locked_at !== null,
                ];
            });
    }

    public function marquerVerse(int $agentId): void
    {
        GoalProgress::where('campaign_id', $this->campagneDetailId)
            ->where('agent_id', $agentId)
            ->update(['paid_at' => now(), 'paid_by' => auth()->id()]);

        $this->dispatch('notify', ['message' => 'Prime marquée comme versée.', 'type' => 'success']);
    }

    public function libelleRideKind(?string $kind): string
    {
        return match ($kind) {
            'clando' => 'Clando',
            'delivery' => 'Livraison',
            'courier' => 'Coursier',
            default => 'Toutes les courses',
        };
    }

    public function libelleStatut(string $statut): string
    {
        return match ($statut) {
            'draft' => 'Brouillon',
            'running' => 'En cours',
            'closed' => 'Clôturée',
            'cancelled' => 'Annulée',
            default => $statut,
        };
    }

    public function toneStatut(string $statut): string
    {
        return match ($statut) {
            'draft' => 'gray',
            'running' => 'success',
            'closed' => 'brand',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }
};
?>

<x-layouts.app title="Objectifs et primes">
    @volt
        <div>
            <x-ui.page-header title="Objectifs et primes" subtitle="Campagnes proposées aux agents, engagement et versement des primes">
                <x-slot:actions>
                    <x-ui.button wire:click="openCreateModal">+ Nouvelle campagne</x-ui.button>
                </x-slot:actions>
            </x-ui.page-header>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Campagnes actives" :value="$this->stats['actives']" tone="brand"
                    icon="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25" />

                <x-ui.stat label="Engagements" :value="$this->stats['engagements']" tone="accent"
                    hint="agents inscrits, toutes campagnes"
                    icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952" />

                <x-ui.stat label="Primes en attente" :value="$this->stats['en_attente']"
                    :tone="$this->stats['en_attente'] > 0 ? 'warning' : 'success'"
                    hint="atteintes, non versées"
                    icon="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Montant à verser" :value="number_format($this->stats['a_verser'], 0, ',', ' ') . ' FCFA'"
                    :tone="$this->stats['a_verser'] > 0 ? 'warning' : 'success'"
                    icon="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6" />
            </div>

            <div class="mt-6">
                <x-ui.table :headers="['Campagne', 'Fenêtre', 'Options', 'Engagements', 'Statut', 'Actions']">
                    @forelse ($this->campagnes as $campagne)
                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-900">{{ $campagne->title }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $campagne->metric === 'rides' ? 'Nombre de courses' : 'Jours actifs' }}
                                    · {{ $this->libelleRideKind($campagne->ride_kind) }}
                                </p>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-600">
                                {{ $campagne->starts_at->format('d/m/Y') }} → {{ $campagne->ends_at->format('d/m/Y') }}
                                <p class="text-gray-400">Inscription jusqu'au {{ $campagne->enrollment_closes_at->format('d/m/Y H:i') }}</p>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($campagne->options as $option)
                                        <x-ui.badge tone="gray">{{ $option->label }} · {{ number_format($option->reward, 0, ',', ' ') }} F</x-ui.badge>
                                    @endforeach
                                </div>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <button type="button" wire:click="voirDetail({{ $campagne->id }})"
                                        class="font-semibold text-brand-700 hover:underline">
                                    {{ $campagne->enrollments_count }} agent{{ $campagne->enrollments_count > 1 ? 's' : '' }}
                                </button>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <x-ui.badge :tone="$this->toneStatut($campagne->status)">{{ $this->libelleStatut($campagne->status) }}</x-ui.badge>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($campagne->status === 'draft')
                                        <x-ui.button size="sm" variant="success" wire:click="publier({{ $campagne->id }})">Publier</x-ui.button>
                                        <x-ui.button size="sm" variant="danger" wire:click="supprimer({{ $campagne->id }})"
                                                     wire:confirm="Supprimer ce brouillon ?">Supprimer</x-ui.button>
                                    @elseif ($campagne->status === 'running')
                                        <x-ui.button size="sm" variant="secondary" wire:click="cloturer({{ $campagne->id }})"
                                                     wire:confirm="Clôturer fige la progression de tous les agents engagés. Continuer ?">Clôturer</x-ui.button>
                                        <x-ui.button size="sm" variant="danger" wire:click="annuler({{ $campagne->id }})"
                                                     wire:confirm="Annuler cette campagne ?">Annuler</x-ui.button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty :colspan="6" title="Aucune campagne"
                            message="Créez une campagne pour la proposer aux agents dans l'application." />
                    @endforelse
                </x-ui.table>

                @if ($this->campagnes->hasPages())
                    <div class="mt-4">{{ $this->campagnes->links() }}</div>
                @endif
            </div>

            {{-- Création --}}
            <x-ui.modal :show="$showModal" title="Nouvelle campagne d'objectifs" width="max-w-2xl">
                <form id="campagneForm" wire:submit.prevent="save" class="space-y-4">
                    <x-ui.field label="Titre" for="title" :required="true" :error="$errors->first('title')">
                        <x-ui.input id="title" wire:model="title" placeholder="Ex. Objectifs de la semaine" :error="$errors->has('title')" />
                    </x-ui.field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Métrique" for="metric" :required="true">
                            <x-ui.select id="metric" wire:model="metric">
                                <option value="rides">Nombre de courses</option>
                                <option value="active_days">Jours actifs</option>
                            </x-ui.select>
                        </x-ui.field>

                        <x-ui.field label="Type de course" for="rideKind" hint="Aucune sélection = toutes les courses">
                            <x-ui.select id="rideKind" wire:model="rideKind">
                                <option value="">Toutes les courses</option>
                                <option value="clando">Clando</option>
                                <option value="delivery">Livraison</option>
                                <option value="courier">Coursier</option>
                            </x-ui.select>
                        </x-ui.field>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.field label="Début" for="startsAt" :required="true" :error="$errors->first('startsAt')">
                            <x-ui.input type="datetime-local" id="startsAt" wire:model="startsAt" :error="$errors->has('startsAt')" />
                        </x-ui.field>

                        <x-ui.field label="Fin" for="endsAt" :required="true" :error="$errors->first('endsAt')">
                            <x-ui.input type="datetime-local" id="endsAt" wire:model="endsAt" :error="$errors->has('endsAt')" />
                        </x-ui.field>

                        <x-ui.field label="Fin de l'inscription" for="enrollmentClosesAt" :required="true" :error="$errors->first('enrollmentClosesAt')">
                            <x-ui.input type="datetime-local" id="enrollmentClosesAt" wire:model="enrollmentClosesAt" :error="$errors->has('enrollmentClosesAt')" />
                        </x-ui.field>
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label class="block text-xs font-semibold text-gray-700">
                                Options proposées <span class="text-red-500">*</span>
                            </label>
                            <button type="button" wire:click="addOption" class="text-xs font-semibold text-brand-700 hover:underline">+ Ajouter une option</button>
                        </div>

                        <p class="mb-2 text-xs text-gray-400">
                            Seuils et montants strictement croissants : la première option est la plus facile et la moins payante.
                        </p>

                        @if ($errors->has('options'))
                            <p class="mb-2 text-xs font-medium text-red-600">{{ $errors->first('options') }}</p>
                        @endif

                        <div class="space-y-2">
                            @foreach ($options as $i => $option)
                                <div class="flex items-center gap-2">
                                    <x-ui.input wire:model="options.{{ $i }}.label" placeholder="Libellé (ex. 10 courses)" class="flex-1" />
                                    <x-ui.input type="number" wire:model="options.{{ $i }}.threshold" placeholder="Seuil" class="w-24" />
                                    <x-ui.input type="number" wire:model="options.{{ $i }}.reward" placeholder="FCFA" class="w-28" />
                                    <button type="button" wire:click="removeOption({{ $i }})"
                                            class="shrink-0 rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                            title="Retirer cette option">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                        La campagne est créée en brouillon : elle n'est visible dans l'application agent qu'après avoir cliqué « Publier ».
                    </p>
                </form>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="closeModal">Annuler</x-ui.button>
                    <x-ui.button type="submit" form="campagneForm" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Créer le brouillon</span>
                        <span wire:loading wire:target="save">Création…</span>
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.modal>

            {{-- Détail : engagements et versement --}}
            <x-ui.modal :show="$showDetailModal" :title="$this->campagneDetail?->title ?? ''"
                        subtitle="Engagements et progression par agent" close="fermerDetail" width="max-w-3xl">
                <x-ui.table :headers="['Agent', 'Option', 'Progression', 'Statut', 'Prime']">
                    @forelse ($this->engagementsDetail as $ligne)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $ligne['agent'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $ligne['option'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">
                                {{ $ligne['progress'] }} / {{ $ligne['target'] }}
                                @unless ($ligne['locked'])
                                    <span class="ml-1 text-xs text-gray-400">(non verrouillé)</span>
                                @endunless
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($ligne['achieved'])
                                    <x-ui.badge tone="success">Atteint</x-ui.badge>
                                @else
                                    <x-ui.badge tone="gray">En cours</x-ui.badge>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if (! $ligne['achieved'] || ! $ligne['amount_due'])
                                    <span class="text-xs text-gray-400">—</span>
                                @elseif ($ligne['paid_at'])
                                    <x-ui.badge tone="brand">Versée le {{ $ligne['paid_at']->format('d/m/Y') }}</x-ui.badge>
                                @else
                                    <x-ui.button size="sm" variant="success"
                                                 wire:click="marquerVerse({{ $ligne['agent_id'] }})"
                                                 wire:confirm="Marquer cette prime comme versée ?">
                                        Marquer versée
                                    </x-ui.button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty :colspan="5" title="Aucun engagement" message="Aucun agent ne s'est encore engagé sur cette campagne." />
                    @endforelse
                </x-ui.table>
            </x-ui.modal>
        </div>
    @endvolt
</x-layouts.app>
