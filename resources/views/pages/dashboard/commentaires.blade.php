<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Clando;
use App\Models\Commentaire;
use App\Models\order_detail;
use App\Models\User;

name('dashboard.commentaires');

/*
| Ce que les clients disent des prestations.
|
| Distinct de l'écran Notes, et pas par goût de la séparation : une note se
| donne une fois par prestation et se referme avec son émoticône. Un
| commentaire peut venir après coup, se répéter, signaler un problème apparu à
| la réception — et appeler une réponse. Les mélanger aurait forcé l'un des deux
| à entrer dans le moule de l'autre.
*/
new class extends Component {
    use WithPagination;

    public $search = '';
    public $prestationFiltre = '';
    public $etatFiltre = '';

    /** Commentaire auquel on rédige une réponse. */
    public $commentaireOuvert = null;
    public $reponse = '';

    public const PRESTATIONS = [
        'commande' => 'Commande',
        'course' => 'Course',
        'clando' => 'Clando',
    ];

    public const ETATS = [
        'sans_reponse' => 'Sans réponse',
        'repondus' => 'Répondus',
        'masques' => 'Masqués',
    ];

    /**
     * Identifiants des commandes qui sont en réalité des courses.
     *
     * Un commentaire ne dit pas de quel genre de prestation il parle : il pointe
     * une commande ou une course clando. Une commande sans panier avec un point
     * de départ est une course — la même règle que partout ailleurs.
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
        return Commentaire::query()
            ->when($this->etatFiltre === 'masques', fn ($q) => $q->where('masque', true))
            ->when($this->etatFiltre !== 'masques', fn ($q) => $q->where('masque', false))
            ->when($this->etatFiltre === 'sans_reponse', fn ($q) => $q->whereNull('reponse'))
            ->when($this->etatFiltre === 'repondus', fn ($q) => $q->whereNotNull('reponse'))
            ->when($this->prestationFiltre === 'clando', fn ($q) => $q->whereNotNull('id_clando'))
            ->when($this->prestationFiltre === 'course', fn ($q) => $q->whereIn('id_order', $this->idsDesCourses()))
            ->when($this->prestationFiltre === 'commande', fn ($q) => $q->whereNotNull('id_order')
                ->whereNotIn('id_order', $this->idsDesCourses()))
            ->when($this->search, function ($q) {
                $terme = '%' . $this->search . '%';
                $q->where(function ($sub) use ($terme) {
                    $sub->where('contenu', 'like', $terme)
                        ->orWhere('reponse', 'like', $terme)
                        ->orWhereHas('client', fn ($r) => $r->where('name', 'like', $terme))
                        ->orWhereHas('agent', fn ($r) => $r->where('name', 'like', $terme));
                });
            });
    }

    public function getCommentairesProperty()
    {
        return $this->requeteDeBase()
            ->with(['client:id,name,phone', 'agent:id,name'])
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * Prestations commentées sur la page courante, en deux requêtes.
     *
     * Un commentaire sévère reste invérifiable si l'on ne sait pas de quelle
     * course il parle.
     */
    public function getPrestationsProperty(): array
    {
        $lignes = $this->commentaires;

        return [
            'commandes' => order_detail::whereIn('id', $lignes->pluck('id_order')->filter())
                ->get(['id', 'ref', 'address', 'depart', 'id_cart'])
                ->keyBy('id'),
            'clandos' => Clando::whereIn('id', $lignes->pluck('id_clando')->filter())
                ->get(['id', 'ref', 'destinationName'])
                ->keyBy('id'),
        ];
    }

    public function getStatsProperty(): array
    {
        return [
            'total' => Commentaire::where('masque', false)->count(),
            'sans_reponse' => Commentaire::where('masque', false)->whereNull('reponse')->count(),
            'agents' => Commentaire::where('masque', false)->whereNotNull('id_agent')
                ->distinct('id_agent')->count('id_agent'),
            'masques' => Commentaire::where('masque', true)->count(),
        ];
    }

    public function ouvrirReponse($id): void
    {
        if ($this->commentaireOuvert === $id) {
            $this->commentaireOuvert = null;
            $this->reponse = '';

            return;
        }

        $this->commentaireOuvert = $id;
        $this->reponse = Commentaire::whereKey($id)->value('reponse') ?? '';
        $this->resetValidation();
    }

    public function repondre(): void
    {
        $valide = $this->validate(
            ['reponse' => 'required|string|min:2|max:2000'],
            [
                'reponse.required' => 'Écrivez une réponse.',
                'reponse.min' => 'Une réponse d\'un caractère n\'en est pas une.',
            ]
        );

        $commentaire = Commentaire::findOrFail($this->commentaireOuvert);

        $commentaire->update([
            'reponse' => trim($valide['reponse']),
            'repondu_le' => now(),
        ]);

        $this->commentaireOuvert = null;
        $this->reponse = '';

        $this->dispatch('notify', ['message' => 'Réponse enregistrée.', 'type' => 'success']);
    }

    /**
     * Masque un commentaire, ou le remet.
     *
     * Masquer plutôt que supprimer : effacer ferait disparaître la trace d'un
     * incident que l'exploitation peut avoir besoin de retrouver.
     */
    public function basculerMasque($id): void
    {
        $commentaire = Commentaire::findOrFail($id);
        $commentaire->update(['masque' => ! $commentaire->masque]);

        $this->dispatch('notify', [
            'message' => $commentaire->masque
                ? 'Commentaire masqué. Il reste consultable par le filtre « Masqués ».'
                : 'Commentaire réaffiché.',
            'type' => 'success',
        ]);
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedPrestationFiltre()
    {
        $this->resetPage();
    }

    public function updatedEtatFiltre()
    {
        $this->resetPage();
    }
};
?>

<x-layouts.app title="Commentaires">
    @volt
        <div>
            <x-ui.page-header title="Commentaires"
                subtitle="Ce que les clients disent de chaque prestation — commande, course ou clando" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Commentaires" :value="$this->stats['total']" tone="brand"
                    icon="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />

                <x-ui.stat label="Sans réponse" :value="$this->stats['sans_reponse']"
                    :tone="$this->stats['sans_reponse'] > 0 ? 'warning' : 'success'"
                    hint="en attente d'un mot de votre part"
                    icon="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />

                <x-ui.stat label="Agents concernés" :value="$this->stats['agents']" tone="info"
                    icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />

                <x-ui.stat label="Masqués" :value="$this->stats['masques']" tone="accent"
                    hint="retirés de l'affichage, jamais supprimés"
                    icon="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.search model="search" placeholder="Commentaire, réponse, client ou agent…" />

                <x-ui.select wire:model.live="prestationFiltre" class="w-auto min-w-[11rem]">
                    <option value="">Toutes les prestations</option>
                    @foreach (self::PRESTATIONS as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select wire:model.live="etatFiltre" class="w-auto min-w-[11rem]">
                    <option value="">Tous les commentaires</option>
                    @foreach (self::ETATS as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="mt-4">
                <x-ui.table target="search,prestationFiltre,etatFiltre,gotoPage,previousPage,nextPage"
                    :headers="['Commentaire', 'Prestation', 'Client', 'Agent', 'Date', '']">
                    @forelse ($this->commentaires as $commentaire)
                        @php
                            $commande = $commentaire->id_order ? ($this->prestations['commandes'][$commentaire->id_order] ?? null) : null;
                            $clando = $commentaire->id_clando ? ($this->prestations['clandos'][$commentaire->id_clando] ?? null) : null;
                            $estCourse = $commande && $commande->id_cart === null && trim((string) $commande->depart) !== '';
                            $genre = $clando ? 'Clando' : ($estCourse ? 'Course' : 'Commande');
                        @endphp

                        <tr class="transition-colors hover:bg-gray-50">
                            <td class="max-w-md px-4 py-3 text-sm">
                                <p class="italic text-gray-800">« {{ $commentaire->contenu }} »</p>

                                @if ($commentaire->reponse)
                                    <div class="mt-2 rounded-lg border-l-2 border-brand-400 bg-brand-50 px-3 py-2">
                                        <p class="text-[11px] font-bold uppercase tracking-wider text-brand-700">
                                            Votre réponse
                                            @if ($commentaire->repondu_le)
                                                · {{ $commentaire->repondu_le->timezone('Africa/Douala')->format('d/m/Y') }}
                                            @endif
                                        </p>
                                        <p class="mt-0.5 text-xs text-gray-700">{{ $commentaire->reponse }}</p>
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm">
                                <x-ui.badge :tone="$genre === 'Clando' ? 'info' : ($genre === 'Course' ? 'warning' : 'brand')">
                                    {{ $genre }}
                                </x-ui.badge>
                                @if ($commande)
                                    <p class="mt-1 font-mono text-[11px] text-gray-500">{{ $commande->ref }}</p>
                                    <p class="text-[11px] text-gray-400">{{ Str::limit($commande->address, 30) }}</p>
                                @elseif ($clando)
                                    <p class="mt-1 font-mono text-[11px] text-gray-500">{{ $clando->ref }}</p>
                                    <p class="text-[11px] text-gray-400">{{ Str::limit($clando->destinationName, 30) }}</p>
                                @else
                                    {{-- La prestation a pu être supprimée depuis. --}}
                                    <p class="mt-1 text-[11px] text-gray-400">Prestation introuvable</p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm">
                                <p class="font-medium text-gray-900">{{ $commentaire->client?->name ?? '—' }}</p>
                                @if ($commentaire->client?->phone)
                                    <p class="font-mono text-xs text-gray-500">{{ $commentaire->client->phone }}</p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-700">
                                {{ $commentaire->agent?->name ?? 'Non attribuée' }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">
                                {{ $commentaire->created_at?->timezone('Africa/Douala')->format('d/m/Y H:i') ?? '—' }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <button type="button" wire:click="ouvrirReponse({{ $commentaire->id }})"
                                        class="text-xs font-semibold text-brand-600 hover:underline">
                                    {{ $commentaireOuvert === $commentaire->id ? 'Fermer' : ($commentaire->reponse ? 'Modifier' : 'Répondre') }}
                                </button>

                                <button type="button" wire:click="basculerMasque({{ $commentaire->id }})"
                                        class="ml-3 text-xs font-semibold text-gray-500 hover:underline">
                                    {{ $commentaire->masque ? 'Réafficher' : 'Masquer' }}
                                </button>
                            </td>
                        </tr>

                        @if ($commentaireOuvert === $commentaire->id)
                            <tr>
                                <td colspan="6" class="bg-gray-50 px-4 py-4">
                                    <label for="reponse" class="text-xs font-bold uppercase tracking-wider text-gray-600">
                                        Répondre à {{ $commentaire->client?->name ?? 'ce client' }}
                                    </label>

                                    <textarea id="reponse" wire:model="reponse" rows="3"
                                              class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-100"></textarea>

                                    @error('reponse')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror

                                    <div class="mt-2 flex items-center gap-3">
                                        <button type="button" wire:click="repondre"
                                                class="rounded-lg bg-brand-600 px-4 py-2 text-xs font-bold text-white hover:bg-brand-700">
                                            Enregistrer la réponse
                                        </button>
                                        <button type="button" wire:click="ouvrirReponse({{ $commentaire->id }})"
                                                class="text-xs font-semibold text-gray-600 hover:underline">
                                            Annuler
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <x-ui.empty :colspan="6" title="Aucun commentaire"
                            message="Les commentaires laissés depuis l'application apparaîtront ici." />
                    @endforelse
                </x-ui.table>

                @if ($this->commentaires->hasPages())
                    <div class="mt-4">{{ $this->commentaires->links() }}</div>
                @endif
            </div>
        </div>
    @endvolt
</x-layouts.app>
