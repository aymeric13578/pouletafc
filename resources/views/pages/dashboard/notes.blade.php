<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Clando;
use App\Models\Note;
use App\Models\order_detail;
use App\Models\Parameter;
use App\Models\User;
use App\Support\NotationAgent;

name('dashboard.notes');

/*
| Notes et commentaires laissés par les clients.
|
| Les appréciations existaient depuis toujours en base et n'étaient lisibles
| nulle part : l'application agent affichait un score, personne ne pouvait voir
| ce qui le composait ni ce que les clients avaient écrit. Un commentaire sévère
| se perdait sans que quiconque le lise.
|
| Cet écran réunit les trois questions qu'on se pose sur la notation : combien
| valent les appréciations, qui sont les meilleurs agents, et qu'a-t-on dit de
| chaque prestation.
*/
new class extends Component {
    use WithPagination;

    public $search = '';
    public $prestationFiltre = '';
    public $appreciationFiltre = '';
    public $agentFiltre = '';

    /** Barème en cours de saisie. */
    public array $points = [];

    public function mount(): void
    {
        $this->points = NotationAgent::bareme();
    }

    public const PRESTATIONS = [
        'commande' => 'Commande',
        'course' => 'Course',
        'clando' => 'Clando',
    ];

    /**
     * Identifiants des commandes qui sont en réalité des courses.
     *
     * Une note ne dit pas de quel genre de prestation elle parle : elle pointe
     * une commande ou une course clando. Or une commande sans panier et avec un
     * point de départ est une course de coursier — la même règle que sur le mur
     * des commandes et dans la page Courses. Sans cette distinction, les trois
     * prestations que l'on veut pouvoir filtrer n'en feraient que deux.
     */
    protected function idsDesCourses()
    {
        return order_detail::query()
            ->whereNull('id_cart')
            ->whereNotNull('depart')
            ->where('depart', '!=', '')
            ->select('id');
    }

    protected function requeteDeBase()
    {
        return Note::query()
            ->when($this->appreciationFiltre, fn ($q) => $q->where('note', $this->appreciationFiltre))
            ->when($this->agentFiltre, fn ($q) => $q->where('id_agent', $this->agentFiltre))
            ->when($this->prestationFiltre === 'clando', fn ($q) => $q->whereNotNull('id_clando'))
            ->when($this->prestationFiltre === 'course', fn ($q) => $q->whereIn('id_order', $this->idsDesCourses()))
            ->when($this->prestationFiltre === 'commande', fn ($q) => $q->whereNotNull('id_order')
                ->whereNotIn('id_order', $this->idsDesCourses()))
            ->when($this->search, function ($q) {
                $terme = '%' . $this->search . '%';
                $q->where(function ($sub) use ($terme) {
                    $sub->where('comment', 'like', $terme)
                        ->orWhereHas('client', fn ($r) => $r->where('name', 'like', $terme))
                        ->orWhereHas('agent', fn ($r) => $r->where('name', 'like', $terme));
                });
            });
    }

    public function getAppreciationsProperty()
    {
        return $this->requeteDeBase()
            ->with(['client:id,name,phone', 'agent:id,name,phone'])
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * Prestations concernées par les appréciations affichées.
     *
     * Résolues en deux requêtes plutôt qu'une par ligne : l'écran doit dire de
     * quelle commande ou de quelle course on parle, sans quoi un commentaire
     * sévère reste invérifiable.
     */
    public function getPrestationsProperty(): array
    {
        $notes = $this->appreciations;

        $commandes = order_detail::whereIn('id', $notes->pluck('id_order')->filter())
            ->get(['id', 'ref', 'address', 'depart', 'id_cart', 'price'])
            ->keyBy('id');

        $courses = Clando::whereIn('id', $notes->pluck('id_clando')->filter())
            ->get(['id', 'ref', 'destinationName', 'price'])
            ->keyBy('id');

        return ['commandes' => $commandes, 'clandos' => $courses];
    }

    /**
     * Agents classés du mieux noté au moins bien noté.
     *
     * Sur la note sur cinq et non sur le total : celui-ci récompense
     * l'ancienneté autant que la qualité, et placerait toujours l'agent aux
     * mille courses correctes devant celui aux dix excellentes.
     */
    public function getClassementProperty()
    {
        $idsNotes = Note::select('id_agent')->distinct()->pluck('id_agent');

        if ($idsNotes->isEmpty()) {
            return collect();
        }

        $bilans = NotationAgent::pourAgents($idsNotes);
        $agents = User::whereIn('id', $idsNotes)->get(['id', 'name', 'phone'])->keyBy('id');

        return collect($bilans)
            ->map(fn (array $bilan, int $id) => [
                'id' => $id,
                'nom' => $agents[$id]->name ?? 'Agent #' . $id,
                'phone' => $agents[$id]->phone ?? null,
            ] + $bilan)
            ->sortByDesc(fn ($ligne) => [$ligne['sur_cinq'] ?? -1, $ligne['nombre']])
            ->values();
    }

    public function getStatsProperty(): array
    {
        $global = NotationAgent::bilan(Note::pluck('note')->countBy()->toArray());
        $meilleur = $this->classement->first();

        return [
            'total' => $global['nombre'],
            'moyenne' => $global['sur_cinq'],
            'commentaires' => Note::whereNotNull('comment')->where('comment', '!=', '')->count(),
            'meilleur' => $meilleur,
        ];
    }

    /** Enregistre le barème sans quitter l'écran. */
    public function enregistrerBareme(): void
    {
        $grille = Parameter::active();

        if (! $grille) {
            $this->dispatch('notify', [
                'message' => "Activez d'abord une configuration.",
                'type' => 'error',
            ]);

            return;
        }

        $valide = $this->validate(
            collect(Parameter::APPRECIATIONS)
                ->mapWithKeys(fn ($a) => ['points.' . $a => 'required|numeric|min:-10|max:10'])
                ->toArray(),
            ['points.*.required' => 'Valeur obligatoire', 'points.*.numeric' => 'Entrez un nombre',
             'points.*.min' => 'Le barème va de -10 à 10', 'points.*.max' => 'Le barème va de -10 à 10']
        );

        $grille->update(
            collect(Parameter::APPRECIATIONS)
                ->mapWithKeys(fn ($a) => ['note_points_' . $a => $valide['points'][$a]])
                ->toArray()
        );

        // Le barème est mémorisé le temps d'une requête : sans cet oubli, les
        // scores affichés juste en dessous resteraient ceux d'avant la saisie.
        NotationAgent::oublierBareme();
        $this->points = NotationAgent::bareme();

        $this->dispatch('notify', ['message' => 'Barème enregistré !', 'type' => 'success']);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPrestationFiltre()
    {
        $this->resetPage();
    }

    public function updatedAppreciationFiltre()
    {
        $this->resetPage();
    }

    public function updatedAgentFiltre()
    {
        $this->resetPage();
    }
};
?>

<x-layouts.app title="Notes">
    @volt
        <div>
            <x-ui.page-header title="Notes"
                subtitle="Appréciations et commentaires laissés par les clients après chaque prestation" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Appréciations" :value="$this->stats['total']" tone="brand"
                    icon="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />

                <x-ui.stat label="Note moyenne"
                    :value="$this->stats['moyenne'] === null ? '—' : $this->stats['moyenne'] . ' / 5'"
                    hint="toutes prestations confondues" tone="accent"
                    icon="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />

                <x-ui.stat label="Commentaires" :value="$this->stats['commentaires']" tone="info"
                    icon="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />

                <x-ui.stat label="Mieux noté"
                    :value="$this->stats['meilleur']['nom'] ?? '—'"
                    :hint="$this->stats['meilleur'] ? $this->stats['meilleur']['sur_cinq'] . ' / 5 sur ' . $this->stats['meilleur']['nombre'] . ' avis' : 'aucune note'"
                    tone="success"
                    icon="M16.5 18.75h-9m9 0a3 3 0 013 3h-15a3 3 0 013-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 01-.982-3.172M9.497 14.25a7.454 7.454 0 00.981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 007.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 002.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 012.916.52 6.003 6.003 0 01-5.395 4.972m0 0a6.726 6.726 0 01-2.749 1.35m0 0a6.772 6.772 0 01-3.044 0" />
            </div>

            {{-- Barème : ce que vaut chaque appréciation --}}
            <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Barème de notation</h2>
                        <p class="mt-1 text-xs text-gray-500">
                            Points attribués à chaque appréciation. Le score d'un agent est la somme
                            de ses points ; sa note sur 5 en est la moyenne, ramenée sur cette échelle.
                            Les valeurs peuvent être négatives et décimales.
                        </p>
                    </div>

                    <button type="button" wire:click="enregistrerBareme"
                            wire:loading.attr="disabled"
                            class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition-colors hover:bg-brand-700 disabled:opacity-60">
                        Enregistrer le barème
                    </button>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-3 xl:grid-cols-5">
                    @foreach (\App\Models\Parameter::APPRECIATIONS as $appreciation)
                        <x-ui.field
                            :label="\App\Support\NotationAgent::EMOJIS[$appreciation] . ' ' . \App\Support\NotationAgent::LIBELLES[$appreciation]"
                            :for="'points-' . $appreciation"
                            :error="$errors->first('points.' . $appreciation)">
                            <x-ui.input :id="'points-' . $appreciation" type="number" step="0.5" min="-10" max="10"
                                        wire:model="points.{{ $appreciation }}"
                                        :error="$errors->has('points.' . $appreciation)" />
                        </x-ui.field>
                    @endforeach
                </div>
            </div>

            {{-- Classement des agents --}}
            <div class="mt-6">
                <h2 class="text-sm font-bold text-gray-900">Classement des agents</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Du mieux noté au moins bien noté, sur la note sur 5 : un total élevé
                    récompense d'abord le nombre de prestations.
                </p>

                <div class="mt-3">
                    <x-ui.table :headers="['Rang', 'Agent', 'Note', 'Détail', 'Avis', 'Total']">
                        @forelse ($this->classement as $rang => $ligne)
                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-bold text-gray-500">
                                    @if ($rang === 0)
                                        <span title="Mieux noté">🏆</span>
                                    @else
                                        {{ $rang + 1 }}
                                    @endif
                                </td>

                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ $ligne['nom'] }}</p>
                                    @if ($ligne['phone'])
                                        <p class="font-mono text-xs text-gray-500">{{ $ligne['phone'] }}</p>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-ui.badge :tone="($ligne['sur_cinq'] ?? 0) >= 3.5 ? 'success' : (($ligne['sur_cinq'] ?? 0) >= 2 ? 'warning' : 'danger')">
                                        {{ $ligne['sur_cinq'] === null ? '—' : $ligne['sur_cinq'] . ' / 5' }}
                                    </x-ui.badge>
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 text-sm">
                                    @foreach (\App\Models\Parameter::APPRECIATIONS as $appreciation)
                                        @if ($ligne['compte'][$appreciation] > 0)
                                            <span class="mr-2 inline-block"
                                                  title="{{ \App\Support\NotationAgent::LIBELLES[$appreciation] }}">
                                                {{ \App\Support\NotationAgent::EMOJIS[$appreciation] }}
                                                <span class="text-xs text-gray-600">{{ $ligne['compte'][$appreciation] }}</span>
                                            </span>
                                        @endif
                                    @endforeach
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-sm text-gray-600">
                                    {{ $ligne['nombre'] }}
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-gray-900">
                                    {{ $ligne['total'] }}
                                    <button type="button" wire:click="$set('agentFiltre', {{ $ligne['id'] }})"
                                            class="ml-2 text-xs font-normal text-brand-600 hover:underline">
                                        voir les avis
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty :colspan="6" title="Aucun agent noté"
                                message="Les appréciations laissées par les clients apparaîtront ici." />
                        @endforelse
                    </x-ui.table>
                </div>
            </div>

            {{-- Appréciations récentes --}}
            <div class="mt-8">
                <h2 class="text-sm font-bold text-gray-900">Appréciations récentes</h2>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <x-ui.search model="search" placeholder="Commentaire, client ou agent…" />

                    <x-ui.select wire:model.live="prestationFiltre" class="w-auto min-w-[11rem]">
                        <option value="">Toutes les prestations</option>
                        @foreach (self::PRESTATIONS as $cle => $libelle)
                            <option value="{{ $cle }}">{{ $libelle }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select wire:model.live="appreciationFiltre" class="w-auto min-w-[11rem]">
                        <option value="">Toutes les appréciations</option>
                        @foreach (\App\Models\Parameter::APPRECIATIONS as $appreciation)
                            <option value="{{ $appreciation }}">
                                {{ \App\Support\NotationAgent::EMOJIS[$appreciation] }}
                                {{ \App\Support\NotationAgent::LIBELLES[$appreciation] }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    @if ($agentFiltre)
                        <button type="button" wire:click="$set('agentFiltre', '')"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Retirer le filtre agent
                        </button>
                    @endif
                </div>

                <div class="mt-4">
                    <x-ui.table target="search,prestationFiltre,appreciationFiltre,agentFiltre,gotoPage,previousPage,nextPage"
                        :headers="['Appréciation', 'Prestation', 'Client', 'Agent', 'Commentaire', 'Date']">
                        @forelse ($this->appreciations as $note)
                            @php
                                $commande = $note->id_order ? ($this->prestations['commandes'][$note->id_order] ?? null) : null;
                                $clando = $note->id_clando ? ($this->prestations['clandos'][$note->id_clando] ?? null) : null;
                                $estCourse = $commande && $commande->id_cart === null && trim((string) $commande->depart) !== '';
                                $genre = $clando ? 'Clando' : ($estCourse ? 'Course' : 'Commande');
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="text-lg">{{ \App\Support\NotationAgent::EMOJIS[$note->note] ?? '' }}</span>
                                    <span class="align-middle text-xs font-medium text-gray-700">
                                        {{ \App\Support\NotationAgent::LIBELLES[$note->note] ?? $note->note }}
                                    </span>
                                    <p class="mt-0.5 text-[11px] text-gray-400">
                                        {{ $this->points[$note->note] ?? 0 }} pt
                                    </p>
                                </td>

                                <td class="px-4 py-3 text-sm">
                                    <x-ui.badge :tone="$genre === 'Clando' ? 'info' : ($genre === 'Course' ? 'warning' : 'brand')">
                                        {{ $genre }}
                                    </x-ui.badge>
                                    @if ($commande)
                                        <p class="mt-1 font-mono text-[11px] text-gray-500">{{ $commande->ref }}</p>
                                        <p class="text-[11px] text-gray-400">{{ Str::limit($commande->address, 34) }}</p>
                                    @elseif ($clando)
                                        <p class="mt-1 font-mono text-[11px] text-gray-500">{{ $clando->ref }}</p>
                                        <p class="text-[11px] text-gray-400">{{ Str::limit($clando->destinationName, 34) }}</p>
                                    @else
                                        {{-- La prestation a pu être supprimée depuis. --}}
                                        <p class="mt-1 text-[11px] text-gray-400">Prestation introuvable</p>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-sm">
                                    <p class="font-medium text-gray-900">{{ $note->client?->name ?? '—' }}</p>
                                    @if ($note->client?->phone)
                                        <p class="font-mono text-xs text-gray-500">{{ $note->client->phone }}</p>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-sm">
                                    <p class="font-medium text-gray-900">{{ $note->agent?->name ?? '—' }}</p>
                                </td>

                                <td class="max-w-sm px-4 py-3 text-sm text-gray-600">
                                    @if (trim((string) $note->comment) !== '')
                                        <span class="italic">« {{ $note->comment }} »</span>
                                    @else
                                        <span class="text-xs text-gray-400">Sans commentaire</span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">
                                    {{ $note->created_at?->timezone('Africa/Douala')->format('d/m/Y H:i') ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty :colspan="6" title="Aucune appréciation"
                                message="Aucune note ne correspond à ces filtres." />
                        @endforelse
                    </x-ui.table>

                    @if ($this->appreciations->hasPages())
                        <div class="mt-4">{{ $this->appreciations->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endvolt
</x-layouts.app>
