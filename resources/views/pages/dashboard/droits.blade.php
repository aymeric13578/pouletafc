<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\MenuPermission;
use App\Models\User;
use App\Support\MenuTableauDeBord;

name('dashboard.droits');

/*
| Qui a accès à quoi dans le tableau de bord.
|
| Entrer dans le back-office donnait tout : un employé chargé des commandes
| voyait la grille des commissions et pouvait supprimer un produit. Masquer le
| lien n'y changeait rien — /dashboard/configuration se devine.
|
| Les droits sont accordés menu par menu plutôt que par rôles figés : les
| responsabilités d'un employé changent, et on ne veut pas inventer un rôle
| chaque fois qu'une combinaison nouvelle apparaît.
*/
new class extends Component {
    public $search = '';

    /** Employé dont on règle les droits. */
    public $employeOuvert = null;

    /** Comptes pouvant entrer dans le tableau de bord. */
    public const ROLES_INTERNES = ['admin', 'employee_afc'];

    public function getEmployesProperty()
    {
        return User::whereIn('role', self::ROLES_INTERNES)
            ->when($this->search, function ($q) {
                $terme = '%' . $this->search . '%';
                $q->where(function ($sub) use ($terme) {
                    $sub->where('name', 'like', $terme)
                        ->orWhere('email', 'like', $terme)
                        ->orWhere('phone', 'like', $terme);
                });
            })
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'role']);
    }

    /** Comptes qu'on peut promouvoir employé. */
    public function getCandidatsProperty()
    {
        return User::whereNotIn('role', self::ROLES_INTERNES)
            ->when($this->search, function ($q) {
                $terme = '%' . $this->search . '%';
                $q->where(function ($sub) use ($terme) {
                    $sub->where('name', 'like', $terme)
                        ->orWhere('email', 'like', $terme)
                        ->orWhere('phone', 'like', $terme);
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'name', 'email', 'phone', 'role']);
    }

    /** Droits accordés, par employé, en une requête. */
    public function getDroitsProperty()
    {
        return MenuPermission::whereIn('user_id', $this->employes->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($lignes) => $lignes->pluck('menu')->all());
    }

    public function getStatsProperty(): array
    {
        return [
            'employes' => User::where('role', 'employee_afc')->count(),
            'admins' => User::where('role', 'admin')->count(),
            'menus' => count(MenuTableauDeBord::routes()),
            'sans_droit' => User::where('role', 'employee_afc')
                ->whereNotIn('id', MenuPermission::select('user_id'))
                ->count(),
        ];
    }

    public function ouvrir($id): void
    {
        $this->employeOuvert = $this->employeOuvert === $id ? null : $id;
    }

    /**
     * Nomme un compte employé AFC.
     */
    public function nommerEmploye($id): void
    {
        $utilisateur = User::findOrFail($id);
        $utilisateur->update(['role' => 'employee_afc']);

        $this->employeOuvert = $utilisateur->id;

        $this->dispatch('notify', [
            'message' => $utilisateur->name . " est désormais employé AFC. Accordez-lui ses menus ci-dessous.",
            'type' => 'success',
        ]);
    }

    /**
     * Retire le rôle d'employé, et les droits avec.
     *
     * Les laisser en place ferait retrouver ses accès au compte s'il était
     * renommé employé plus tard, sans que personne ne l'ait décidé.
     */
    public function retirerEmploye($id): void
    {
        $utilisateur = User::findOrFail($id);

        if ($utilisateur->role === 'admin') {
            $this->dispatch('notify', [
                'message' => "Un administrateur ne se rétrograde pas depuis cet écran.",
                'type' => 'error',
            ]);

            return;
        }

        MenuPermission::where('user_id', $utilisateur->id)->delete();
        $utilisateur->update(['role' => 'user']);

        if ($this->employeOuvert === $utilisateur->id) {
            $this->employeOuvert = null;
        }

        $this->dispatch('notify', [
            'message' => $utilisateur->name . " n'est plus employé ; ses droits sont retirés.",
            'type' => 'success',
        ]);
    }

    /**
     * Accorde ou retire un menu.
     */
    public function basculerMenu($idUser, $menu): void
    {
        $utilisateur = User::findOrFail($idUser);

        if ($utilisateur->role === 'admin') {
            $this->dispatch('notify', [
                'message' => 'Un administrateur voit tout : ses droits ne se règlent pas.',
                'type' => 'error',
            ]);

            return;
        }

        // Un menu inventé ne s'accorde pas : la liste fait foi.
        if (! in_array($menu, MenuTableauDeBord::routes(), true)) {
            return;
        }

        $existant = MenuPermission::where('user_id', $utilisateur->id)->where('menu', $menu)->first();

        if ($existant) {
            $existant->delete();
            $message = MenuTableauDeBord::libelle($menu) . ' retiré à ' . $utilisateur->name . '.';
        } else {
            MenuPermission::create(['user_id' => $utilisateur->id, 'menu' => $menu]);
            $message = MenuTableauDeBord::libelle($menu) . ' accordé à ' . $utilisateur->name . '.';
        }

        $this->dispatch('notify', ['message' => $message, 'type' => 'success']);
    }

    /** Accorde tous les menus d'une section d'un coup. */
    public function accorderSection($idUser, $titre): void
    {
        $utilisateur = User::findOrFail($idUser);

        if ($utilisateur->role === 'admin') {
            return;
        }

        foreach (MenuTableauDeBord::sections() as [$section, $liens]) {
            if ($section !== $titre) {
                continue;
            }

            foreach ($liens as $lien) {
                MenuPermission::firstOrCreate(['user_id' => $utilisateur->id, 'menu' => $lien[0]]);
            }
        }

        $this->dispatch('notify', [
            'message' => 'Section « ' . $titre . ' » accordée à ' . $utilisateur->name . '.',
            'type' => 'success',
        ]);
    }

    public function toutRetirer($idUser): void
    {
        $utilisateur = User::findOrFail($idUser);

        if ($utilisateur->role === 'admin') {
            return;
        }

        MenuPermission::where('user_id', $utilisateur->id)->delete();

        $this->dispatch('notify', [
            'message' => 'Tous les droits de ' . $utilisateur->name . ' sont retirés.',
            'type' => 'success',
        ]);
    }
};
?>

<x-layouts.app title="Droits d'accès">
    @volt
        <div>
            <x-ui.page-header title="Droits d'accès"
                subtitle="Quels menus du tableau de bord chaque employé peut ouvrir" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Employés AFC" :value="$this->stats['employes']" tone="brand"
                    icon="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />

                <x-ui.stat label="Administrateurs" :value="$this->stats['admins']" tone="accent"
                    hint="accès à tout, par définition"
                    icon="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                <x-ui.stat label="Menus réglables" :value="$this->stats['menus']" tone="info"
                    icon="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />

                <x-ui.stat label="Sans aucun droit" :value="$this->stats['sans_droit']"
                    :tone="$this->stats['sans_droit'] > 0 ? 'warning' : 'success'"
                    hint="ne voient que l'accueil"
                    icon="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-ui.search model="search" placeholder="Nom, e-mail ou téléphone…" />
            </div>

            {{-- L'équipe --}}
            <div class="mt-4">
                <h2 class="text-sm font-bold text-gray-900">L'équipe</h2>

                <div class="mt-3">
                    <x-ui.table :headers="['Personne', 'Rôle', 'Menus accordés', '']">
                        @forelse ($this->employes as $employe)
                            @php $accordes = $this->droits[$employe->id] ?? []; @endphp

                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ $employe->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $employe->email }}{{ $employe->phone ? ' · ' . $employe->phone : '' }}
                                    </p>
                                </td>

                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-ui.badge :tone="$employe->role === 'admin' ? 'accent' : 'brand'">
                                        {{ $employe->role === 'admin' ? 'Administrateur' : 'Employé AFC' }}
                                    </x-ui.badge>
                                </td>

                                <td class="px-4 py-3 text-sm">
                                    @if ($employe->role === 'admin')
                                        <span class="text-xs text-gray-500">Tous les menus</span>
                                    @elseif (count($accordes) === 0)
                                        <span class="text-xs text-amber-700">Aucun — ne voit que l'accueil</span>
                                    @else
                                        <span class="text-gray-700">{{ count($accordes) }} sur {{ $this->stats['menus'] }}</span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    @unless ($employe->role === 'admin')
                                        <button type="button" wire:click="ouvrir({{ $employe->id }})"
                                                class="text-xs font-semibold text-brand-600 hover:underline">
                                            {{ $employeOuvert === $employe->id ? 'Fermer' : 'Régler les droits' }}
                                        </button>

                                        <button type="button" wire:click="retirerEmploye({{ $employe->id }})"
                                                wire:confirm="Retirer le rôle d'employé et tous ses droits ?"
                                                class="ml-3 text-xs font-semibold text-red-600 hover:underline">
                                            Retirer de l'équipe
                                        </button>
                                    @endunless
                                </td>
                            </tr>

                            @if ($employeOuvert === $employe->id)
                                <tr wire:key="droits-{{ $employe->id }}">
                                    <td colspan="4" class="bg-gray-50 px-4 py-4">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-xs font-bold uppercase tracking-wider text-gray-600">
                                                Menus de {{ $employe->name }}
                                            </p>
                                            <button type="button" wire:click="toutRetirer({{ $employe->id }})"
                                                    class="text-xs font-semibold text-gray-500 hover:underline">
                                                Tout retirer
                                            </button>
                                        </div>

                                        <div class="mt-3 space-y-4">
                                            @foreach (\App\Support\MenuTableauDeBord::sections() as [$titre, $liens])
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <p class="text-xs font-bold text-gray-700">{{ $titre }}</p>
                                                        <button type="button"
                                                                wire:click="accorderSection({{ $employe->id }}, '{{ $titre }}')"
                                                                class="text-[11px] font-semibold text-brand-600 hover:underline">
                                                            tout accorder
                                                        </button>
                                                    </div>

                                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                                        @foreach ($liens as $lien)
                                                            @php $ouvert = in_array($lien[0], $accordes, true); @endphp
                                                            <button type="button"
                                                                    wire:click="basculerMenu({{ $employe->id }}, '{{ $lien[0] }}')"
                                                                    class="rounded-lg border px-2.5 py-1 text-xs font-semibold transition-colors
                                                                        {{ $ouvert
                                                                            ? 'border-brand-500 bg-brand-600 text-white hover:bg-brand-700'
                                                                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100' }}">
                                                                {{ $ouvert ? '✓ ' : '+ ' }}{{ $lien[1] }}
                                                            </button>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <p class="mt-4 text-xs text-gray-500">
                                            L'accueil reste toujours ouvert : un employé sans aucun droit doit
                                            pouvoir se connecter et atterrir quelque part, sinon il tourne en
                                            boucle sur un refus.
                                        </p>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <x-ui.empty :colspan="4" title="Aucun membre de l'équipe"
                                message="Nommez un compte employé AFC dans la liste ci-dessous." />
                        @endforelse
                    </x-ui.table>
                </div>
            </div>

            {{-- Nommer un employé --}}
            <div class="mt-8">
                <h2 class="text-sm font-bold text-gray-900">Nommer un employé AFC</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Un compte nommé employé entre dans le tableau de bord, mais ne voit
                    que les menus qu'on lui accorde ensuite.
                </p>

                <div class="mt-3">
                    <x-ui.table :headers="['Personne', 'Rôle actuel', '']">
                        @forelse ($this->candidats as $candidat)
                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-900">{{ $candidat->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $candidat->email }}{{ $candidat->phone ? ' · ' . $candidat->phone : '' }}
                                    </p>
                                </td>

                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-ui.badge tone="gray">{{ $candidat->role ?: 'client' }}</x-ui.badge>
                                </td>

                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button type="button" wire:click="nommerEmploye({{ $candidat->id }})"
                                            class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-700">
                                        Nommer employé AFC
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty :colspan="3" title="Aucun compte à nommer"
                                message="Affinez la recherche pour retrouver un compte." />
                        @endforelse
                    </x-ui.table>
                </div>
            </div>
        </div>
    @endvolt
</x-layouts.app>
