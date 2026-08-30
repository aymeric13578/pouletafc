<?php

use function Laravel\Folio\{name};
use Livewire\Volt\Component;
use App\Models\Parameter;
use App\Models\Tarif;
use App\Models\TarifPlage;

name('dashboard.configuration');

/*
| Grille tarifaire : prix au kilomètre, courses minimales et pourcentages de
| commission appliqués aux clandos et aux commandes.
|
| Une seule ligne s'applique à la fois, celle au statut 'Success' : c'est ce que
| lisent ClandoController, OrderController et l'endpoint getParameters. Les
| autres lignes restent en base comme historique des grilles précédentes, prêtes
| à être réactivées.
|
| L'ancien écran Bootstrap ne savait qu'empiler des lignes : pas de modification,
| et « Supprimer » restait proposé sur la ligne active — l'effacer laissait
| l'application sans grille, toutes les commissions retombant à 0 sans le moindre
| signal.
*/
new class extends Component {
    public $showModal = false;
    public $parameterId = null;

    public $clando_kilometer = '';
    public $command_kilometer = '';
    public $min_price_clando = '';
    public $min_price_command = '';
    // Tarif des courses de coursier, jusqu'ici écrit en dur dans
    // l'application cliente (500 F + 200 F/km).
    public $coursier_kilometer = '';
    public $min_price_coursier = '';
    public $clando_agent_commission = '';
    public $clando_agent_command = '';
    public $delivery_agent_commission = '';
    public $vip_percentage = '';
    public $price_per_kg = '';

    /*
    | Barème de notation : ce que vaut chaque appréciation laissée par un client.
    | Ces points étaient écrits en dur dans le calcul du score des agents ; les
    | changer demandait de toucher au code.
    */
    public $note_points_verybad = '';
    public $note_points_bad = '';
    public $note_points_average = '';
    public $note_points_good = '';
    public $note_points_excellent = '';

    /*
    |---------------------------------------------------------------------------
    | Grilles tarifaires par service
    |---------------------------------------------------------------------------
    |
    | Un formulaire par service — clando, livraison, course coursier — plutôt
    | qu'une seule ligne mélangeant les trois. Chaque grille porte 2 à 5 plages
    | horaires qui s'appliquent automatiquement selon l'heure : un tarif de nuit
    | n'a pas de raison d'être celui de midi, et rien ne permettait de les
    | distinguer.
    */
    public $showTarifModal = false;
    public $tarifId = null;
    public $tarifService = Tarif::CLANDO;
    public $tarifLibelle = '';
    /** @var array<int, array<string, mixed>> Plages en cours de saisie. */
    public $plages = [];

    public function getGrillesProperty()
    {
        return collect(Tarif::SERVICES)->map(fn ($libelle, $service) => [
            'service' => $service,
            'libelle' => $libelle,
            'actif' => Tarif::actif($service),
            'historique' => Tarif::where('service', $service)
                ->where('status', Tarif::INACTIF)
                ->with('plages')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    /** Une plage vierge, pré-remplie sur la journée entière. */
    private function plageVierge(int $ordre = 0): array
    {
        return [
            'id' => null,
            'debut' => $ordre === 0 ? '06:00' : '',
            'fin' => $ordre === 0 ? '18:00' : '',
            'prix_min' => '',
            'prix_max' => '',
            'prix_km' => '',
            'commission' => '',
            'commission_vip' => '',
            'majoration_vip' => '',
        ];
    }

    public function openTarifModal(string $service, $id = null)
    {
        $this->resetValidation();
        $this->tarifService = $service;
        $this->tarifId = $id;

        if ($id === null) {
            // Pré-remplir depuis la grille en vigueur : une nouvelle grille
            // ajuste presque toujours une plage, pas toutes.
            $reference = Tarif::actif($service);

            $this->tarifLibelle = '';
            $this->plages = $reference && $reference->plages->isNotEmpty()
                ? $reference->plages->values()->map(fn (TarifPlage $p) => [
                    'id' => null,
                    'debut' => $p->debutCourt(),
                    'fin' => $p->finCourte(),
                    'prix_min' => $p->prix_min,
                    'prix_max' => $p->prix_max,
                    'prix_km' => $p->prix_km,
                    'commission' => $p->commission,
                    'commission_vip' => $p->commission_vip,
                    'majoration_vip' => $p->majoration_vip,
                ])->toArray()
                : [$this->plageVierge()];

            $this->showTarifModal = true;

            return;
        }

        $tarif = Tarif::with('plages')->findOrFail($id);

        $this->tarifService = $tarif->service;
        $this->tarifLibelle = $tarif->libelle ?? '';
        $this->plages = $tarif->plages->values()->map(fn (TarifPlage $p) => [
            'id' => $p->id,
            'debut' => $p->debutCourt(),
            'fin' => $p->finCourte(),
            'prix_min' => $p->prix_min,
            'prix_max' => $p->prix_max,
            'prix_km' => $p->prix_km,
            'commission' => $p->commission,
            'commission_vip' => $p->commission_vip,
            'majoration_vip' => $p->majoration_vip,
        ])->toArray();

        if (empty($this->plages)) {
            $this->plages = [$this->plageVierge()];
        }

        $this->showTarifModal = true;
    }

    public function closeTarifModal()
    {
        $this->showTarifModal = false;
        $this->tarifId = null;
        $this->plages = [];
        $this->resetValidation();
    }

    /** Cinq plages au maximum : au-delà, la grille devient illisible à relire. */
    public function ajouterPlage()
    {
        if (count($this->plages) >= 5) {
            $this->dispatch('notify', [
                'message' => 'Cinq plages horaires au maximum par grille.',
                'type' => 'error',
            ]);

            return;
        }

        $this->plages[] = $this->plageVierge(count($this->plages));
    }

    public function retirerPlage(int $index)
    {
        // Une grille sans plage ne saurait facturer quoi que ce soit.
        if (count($this->plages) <= 1) {
            $this->dispatch('notify', [
                'message' => 'Une grille doit garder au moins une plage horaire.',
                'type' => 'error',
            ]);

            return;
        }

        unset($this->plages[$index]);
        $this->plages = array_values($this->plages);
    }

    public function saveTarif()
    {
        $regles = ['tarifLibelle' => 'nullable|string|max:120'];

        foreach (array_keys($this->plages) as $i) {
            foreach (TarifPlage::regles("plages.{$i}.") as $champ => $regle) {
                $regles[$champ] = $regle;
            }
        }

        $this->validate($regles, [
            'required' => 'Cette valeur est obligatoire',
            'date_format' => 'Format attendu : HH:MM',
            'integer' => 'Entrez un nombre entier',
            'numeric' => 'Entrez un nombre',
            'min' => 'La valeur ne peut pas être négative',
            'max' => 'Un pourcentage ne peut pas dépasser 100',
        ]);

        // Un plafond sous le plancher facturerait toutes les courses au même
        // prix sans que rien ne le signale à la saisie.
        foreach ($this->plages as $i => $plage) {
            if ($plage['prix_max'] !== '' && $plage['prix_max'] !== null
                && (int) $plage['prix_max'] > 0
                && (int) $plage['prix_max'] < (int) $plage['prix_min']) {
                $this->addError("plages.{$i}.prix_max", 'Le plafond doit être au moins égal au plancher.');

                return;
            }
        }

        \DB::transaction(function () {
            $tarif = $this->tarifId
                ? Tarif::findOrFail($this->tarifId)
                : Tarif::create([
                    'service' => $this->tarifService,
                    'libelle' => $this->tarifLibelle ?: null,
                    // Créée inactive : on ne bascule jamais la tarification
                    // d'une application en production par effet de bord.
                    'status' => Tarif::INACTIF,
                ]);

            if ($this->tarifId) {
                $tarif->update(['libelle' => $this->tarifLibelle ?: null]);
                // Les plages sont réécrites en bloc : suivre les ajouts,
                // retraits et réordonnancements ligne à ligne coûterait plus
                // qu'il ne rapporte pour cinq lignes au plus.
                $tarif->plages()->delete();
            }

            $avecVip = in_array($tarif->service, Tarif::SERVICES_AVEC_VIP, true);

            foreach (array_values($this->plages) as $ordre => $plage) {
                $tarif->plages()->create([
                    'debut' => $plage['debut'],
                    'fin' => $plage['fin'],
                    'prix_min' => (int) $plage['prix_min'],
                    'prix_max' => $plage['prix_max'] === '' ? null : (int) $plage['prix_max'],
                    'prix_km' => (int) $plage['prix_km'],
                    'commission' => (float) $plage['commission'],
                    'commission_vip' => $avecVip && $plage['commission_vip'] !== ''
                        ? (float) $plage['commission_vip'] : null,
                    'majoration_vip' => $avecVip && $plage['majoration_vip'] !== ''
                        ? (float) $plage['majoration_vip'] : null,
                    'ordre' => $ordre,
                ]);
            }
        });

        $this->dispatch('notify', [
            'message' => $this->tarifId
                ? 'Grille mise à jour.'
                : 'Grille créée. Activez-la pour l\'appliquer.',
            'type' => 'success',
        ]);

        $this->closeTarifModal();
    }

    public function activerTarif($id)
    {
        Tarif::findOrFail($id)->activer();

        $this->dispatch('notify', [
            'message' => 'Grille activée : c\'est désormais la seule appliquée pour ce service.',
            'type' => 'success',
        ]);
    }

    public function supprimerTarif($id)
    {
        $tarif = Tarif::findOrFail($id);

        if (! $tarif->estSupprimable()) {
            $this->dispatch('notify', [
                'message' => 'Impossible de supprimer la grille active. Activez-en une autre d\'abord.',
                'type' => 'error',
            ]);

            return;
        }

        $tarif->delete();

        $this->dispatch('notify', ['message' => 'Grille supprimée.', 'type' => 'success']);
    }

    public function getConfigurationsProperty()
    {
        return Parameter::orderByRaw("status = '" . Parameter::ACTIF . "' DESC")
            ->orderByDesc('id')
            ->get();
    }

    public function getActiveProperty()
    {
        return Parameter::active();
    }

    public function openModal($id = null)
    {
        $this->resetValidation();

        if ($id === null) {
            $this->parameterId = null;

            // Pré-remplir depuis la grille en vigueur : une nouvelle grille
            // ajuste presque toujours une ou deux valeurs, pas les huit.
            $reference = $this->active;

            $this->clando_kilometer = $reference->clando_kilometer ?? '';
            $this->command_kilometer = $reference->command_kilometer ?? '';
            $this->min_price_clando = $reference->min_price_clando ?? '';
            $this->min_price_command = $reference->min_price_command ?? '';
            $this->coursier_kilometer = $reference->coursier_kilometer ?? '';
            $this->min_price_coursier = $reference->min_price_coursier ?? '';
            $this->clando_agent_commission = $reference->clando_agent_commission ?? '';
            $this->clando_agent_command = $reference->clando_agent_command ?? '';
            $this->delivery_agent_commission = $reference->delivery_agent_commission ?? '';
            $this->vip_percentage = $reference->vip_percentage ?? '';
            $this->price_per_kg = $reference->price_per_kg ?? '';

            foreach (\App\Models\Parameter::APPRECIATIONS as $appreciation) {
                $champ = 'note_points_' . $appreciation;
                // Une grille de référence antérieure au barème n'en porte pas :
                // on retombe sur les points historiques plutôt que sur zéro.
                $this->$champ = $reference->$champ
                    ?? \App\Models\Parameter::POINTS_PAR_DEFAUT[$appreciation];
            }

            $this->showModal = true;

            return;
        }

        $configuration = Parameter::findOrFail($id);

        $this->parameterId = $configuration->id;
        $this->clando_kilometer = $configuration->clando_kilometer;
        $this->command_kilometer = $configuration->command_kilometer;
        $this->min_price_clando = $configuration->min_price_clando;
        $this->min_price_command = $configuration->min_price_command;
        $this->coursier_kilometer = $configuration->coursier_kilometer;
        $this->min_price_coursier = $configuration->min_price_coursier;
        $this->clando_agent_commission = $configuration->clando_agent_commission;
        $this->clando_agent_command = $configuration->clando_agent_command;
        $this->delivery_agent_commission = $configuration->delivery_agent_commission;
        $this->vip_percentage = $configuration->vip_percentage;
        $this->price_per_kg = $configuration->price_per_kg;

        foreach (\App\Models\Parameter::APPRECIATIONS as $appreciation) {
            $champ = 'note_points_' . $appreciation;
            $this->$champ = $configuration->$champ
                ?? \App\Models\Parameter::POINTS_PAR_DEFAUT[$appreciation];
        }

        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->parameterId = null;
        $this->resetValidation();
    }

    public function save()
    {
        $valide = $this->validate(Parameter::regles(), Parameter::messagesValidation());

        if ($this->parameterId) {
            $configuration = Parameter::findOrFail($this->parameterId);
            $configuration->update($valide);

            $message = $configuration->estActive()
                ? 'Configuration active mise à jour, les nouveaux tarifs s\'appliquent immédiatement.'
                : 'Configuration mise à jour.';
        } else {
            // Créée inactive : on ne bascule jamais la tarification d'une
            // application en production par simple effet de bord d'une saisie.
            Parameter::create($valide + ['status' => Parameter::INACTIF]);

            $message = 'Configuration créée. Activez-la pour l\'appliquer.';
        }

        $this->dispatch('notify', ['message' => $message, 'type' => 'success']);
        $this->closeModal();
    }

    /*
    | Essai d'envoi de courriel.
    |
    | Les messages qui comptent — code de confirmation, accusé de commande —
    | partent par courriel depuis que les SMS d'Orange sont acceptés, facturés,
    | et jamais remis. Cette dépendance mérite d'être vérifiable sans ouvrir un
    | terminal : une adresse, un bouton, et le message d'erreur exact si ça
    | coince, plutôt qu'un silence à interpréter.
    */
    public $courrielTest = '';

    public function envoyerCourrielTest()
    {
        $destinataire = trim($this->courrielTest) ?: (string) auth()->user()?->email;

        if (! filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            $this->dispatch('notify', [
                'message' => 'Renseignez une adresse valide pour l\'essai.',
                'type' => 'error',
            ]);

            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($destinataire)->send(
                new \App\Mail\NotificationMail(
                    'POULET AFC - essai d\'envoi',
                    'Ce message confirme que le serveur poste bien les courriels. '
                        . 'Expédié depuis ' . config('mail.from.address')
                        . ' via « ' . config('mail.default') . ' ».',
                    'Essai d\'envoi'
                )
            );

            $this->dispatch('notify', [
                'message' => 'Message remis au serveur pour ' . $destinataire . '. Vérifiez la boîte de réception.',
                'type' => 'success',
            ]);
        } catch (\Throwable $e) {
            // Le message brut, sans reformulation : c'est lui qui dit si la
            // boîte est refusée, le port fermé ou sendmail absent.
            $this->dispatch('notify', [
                'message' => 'Échec de l\'envoi : ' . $e->getMessage(),
                'type' => 'error',
            ]);
        }
    }

    public function activer($id)
    {
        Parameter::findOrFail($id)->activer();

        $this->dispatch('notify', [
            'message' => 'Configuration activée. C\'est désormais la seule grille appliquée.',
            'type' => 'success',
        ]);
    }

    public function supprimer($id)
    {
        $configuration = Parameter::findOrFail($id);

        // Sans grille active, les commissions calculées valent 0 et les prix
        // au kilomètre deviennent introuvables côté application mobile.
        if (! $configuration->estSupprimable()) {
            $this->dispatch('notify', [
                'message' => 'Impossible de supprimer la configuration active. Activez-en une autre d\'abord.',
                'type' => 'error',
            ]);

            return;
        }

        $configuration->delete();

        $this->dispatch('notify', ['message' => 'Configuration supprimée.', 'type' => 'success']);
    }
};
?>

<x-layouts.app title="Configuration">
    @volt
        <div>
            <x-ui.page-header
                title="Configuration"
                subtitle="Tarifs au kilomètre, courses minimales et pourcentages de commission">
                <x-slot:actions>
                    <x-ui.button wire:click="openModal">Nouvelle configuration</x-ui.button>
                </x-slot:actions>
            </x-ui.page-header>

            @if ($this->active)
                {{-- Ce qui s'applique réellement, en tête : c'est la seule ligne
                     que lisent l'application mobile et le calcul des commissions. --}}
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <x-ui.stat
                        label="Commission agent clando"
                        :value="$this->active->clando_agent_commission . ' %'"
                        hint="prélevé sur chaque clando"
                        tone="brand"
                        icon="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />

                    <x-ui.stat
                        label="Commission agent commande"
                        :value="$this->active->clando_agent_command . ' %'"
                        hint="prélevé sur chaque commande"
                        tone="accent"
                        icon="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12A1.125 1.125 0 0119.75 22H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />

                    <x-ui.stat
                        label="Commission livreur"
                        :value="$this->active->delivery_agent_commission . ' %'"
                        hint="part reversée au livreur"
                        tone="success"
                        icon="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-8.25m0-11.25h6.75" />

                    <x-ui.stat
                        label="Pourcentage VIP"
                        :value="$this->active->vip_percentage . ' %'"
                        hint="majoration clando VIP"
                        tone="warning"
                        icon="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z" />
                </div>
            @else
                <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4">
                    <p class="text-sm font-bold text-red-800">Aucune configuration active</p>
                    <p class="mt-1 text-xs text-red-700">
                        Tant qu'aucune grille n'est activée, les commissions calculées valent 0
                        et l'application mobile ne reçoit aucun tarif. Activez une ligne ci-dessous.
                    </p>
                </div>
            @endif

            {{-- Grilles tarifaires, un bloc par service.

                 Les trois services partageaient une seule ligne de réglages où
                 « prix/km clando » côtoyait « min. commande » : on ne pouvait
                 ni les régler séparément, ni faire varier un tarif selon
                 l'heure. Chaque service a désormais sa grille, découpée en
                 plages horaires qui s'appliquent toutes seules. --}}
            <div class="mt-6 space-y-4">
                <div class="flex items-baseline justify-between">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-gray-500">Grilles tarifaires par service</h2>
                    <p class="text-xs text-gray-400">La plage dont l'heure correspond s'applique automatiquement</p>
                </div>

                <div class="grid gap-4 xl:grid-cols-3">
                    @foreach ($this->grilles as $grille)
                        @php
                            $actif = $grille['actif'];
                            $courante = $actif?->plageCourante();
                            $avecVip = in_array($grille['service'], \App\Models\Tarif::SERVICES_AVEC_VIP, true);
                        @endphp

                        <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-base font-bold text-gray-900">{{ $grille['libelle'] }}</p>
                                    @if ($actif?->libelle)
                                        <p class="mt-0.5 text-xs text-gray-500">{{ $actif->libelle }}</p>
                                    @endif
                                </div>
                                <x-ui.badge :tone="$actif ? 'success' : 'gray'">
                                    {{ $actif ? 'Active' : 'Aucune grille' }}
                                </x-ui.badge>
                            </div>

                            @if ($actif && $actif->plages->isNotEmpty())
                                <div class="mt-4 space-y-2">
                                    @foreach ($actif->plages as $plage)
                                        @php $estCourante = $courante && $courante->id === $plage->id; @endphp
                                        <div @class([
                                            'rounded-xl border px-3 py-2.5',
                                            'border-emerald-300 bg-emerald-50' => $estCourante,
                                            'border-gray-200 bg-gray-50' => ! $estCourante,
                                        ])>
                                            <div class="flex items-center justify-between">
                                                <p class="font-mono text-sm font-bold text-gray-900">
                                                    {{ $plage->debutCourt() }} – {{ $plage->finCourte() }}
                                                </p>
                                                {{-- Repère de ce qui est facturé à l'instant présent :
                                                     avec cinq plages, savoir laquelle s'applique
                                                     demandait sinon de lire l'heure et comparer. --}}
                                                @if ($estCourante)
                                                    <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-white">
                                                        En cours
                                                    </span>
                                                @endif
                                            </div>

                                            <dl class="mt-1.5 grid grid-cols-3 gap-x-3 gap-y-1 text-xs">
                                                <div>
                                                    <dt class="text-gray-500">Prix/km</dt>
                                                    <dd class="font-semibold tabular-nums text-gray-900">{{ $plage->prix_km }} F</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-gray-500">Plancher</dt>
                                                    <dd class="font-semibold tabular-nums text-gray-900">{{ $plage->prix_min }} F</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-gray-500">Plafond</dt>
                                                    <dd class="font-semibold tabular-nums text-gray-900">
                                                        {{ $plage->prix_max ? $plage->prix_max . ' F' : '—' }}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt class="text-gray-500">Commission</dt>
                                                    <dd class="font-semibold tabular-nums text-gray-900">{{ rtrim(rtrim(number_format($plage->commission, 2, ',', ''), '0'), ',') }} %</dd>
                                                </div>
                                                @if ($avecVip)
                                                    <div>
                                                        <dt class="text-gray-500">Comm. VIP</dt>
                                                        <dd class="font-semibold tabular-nums text-gray-900">
                                                            {{ $plage->commission_vip !== null ? rtrim(rtrim(number_format($plage->commission_vip, 2, ',', ''), '0'), ',') . ' %' : '—' }}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-gray-500">Major. VIP</dt>
                                                        <dd class="font-semibold tabular-nums text-gray-900">
                                                            {{ $plage->majoration_vip !== null ? rtrim(rtrim(number_format($plage->majoration_vip, 2, ',', ''), '0'), ',') . ' %' : '—' }}
                                                        </dd>
                                                    </div>
                                                @endif
                                            </dl>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-4 flex-1 rounded-xl border border-dashed border-gray-300 px-3 py-6 text-center text-xs text-gray-500">
                                    Aucune grille active pour ce service.<br>
                                    Les tarifs de l'ancienne configuration s'appliquent.
                                </p>
                            @endif

                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <x-ui.button size="sm" wire:click="openTarifModal('{{ $grille['service'] }}')">
                                    Nouvelle grille
                                </x-ui.button>

                                @if ($actif)
                                    <x-ui.button size="sm" variant="secondary" wire:click="openTarifModal('{{ $grille['service'] }}', {{ $actif->id }})">
                                        Modifier
                                    </x-ui.button>
                                @endif
                            </div>

                            @if ($grille['historique']->isNotEmpty())
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-xs font-semibold text-gray-500 hover:text-gray-700">
                                        {{ $grille['historique']->count() }} grille(s) inactive(s)
                                    </summary>
                                    <div class="mt-2 space-y-1.5">
                                        @foreach ($grille['historique'] as $inactive)
                                            <div class="flex items-center justify-between gap-2 rounded-lg bg-gray-50 px-2.5 py-1.5">
                                                <span class="truncate text-xs text-gray-600">
                                                    {{ $inactive->libelle ?: $inactive->created_at?->format('d/m/Y') }}
                                                    <span class="text-gray-400">· {{ $inactive->plages->count() }} plage(s)</span>
                                                </span>
                                                <span class="flex shrink-0 gap-1">
                                                    <button type="button" wire:click="activerTarif({{ $inactive->id }})"
                                                            wire:confirm="Appliquer cette grille ? Celle en cours sera désactivée."
                                                            class="rounded px-2 py-0.5 text-xs font-bold text-emerald-700 hover:bg-emerald-50">
                                                        Activer
                                                    </button>
                                                    <button type="button" wire:click="openTarifModal('{{ $grille['service'] }}', {{ $inactive->id }})"
                                                            class="rounded px-2 py-0.5 text-xs font-bold text-gray-600 hover:bg-gray-100">
                                                        Modifier
                                                    </button>
                                                    <button type="button" wire:click="supprimerTarif({{ $inactive->id }})"
                                                            wire:confirm="Supprimer définitivement cette grille ?"
                                                            class="rounded px-2 py-0.5 text-xs font-bold text-red-600 hover:bg-red-50">
                                                        Supprimer
                                                    </button>
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Envoi des courriels : ce qui est configuré, et de quoi l'essayer. --}}
            <div class="mt-6 rounded-2xl border border-gray-200 bg-white px-5 py-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-bold text-gray-900">Envoi des courriels</p>
                        <p class="mt-1 text-xs text-gray-500">
                            Expéditeur
                            <span class="font-semibold text-gray-700">{{ config('mail.from.address') }}</span>,
                            par
                            <span class="font-semibold text-gray-700">{{ config('mail.default') }}</span>.
                            Les codes de confirmation et les accusés de commande passent par là.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            type="email"
                            wire:model="courrielTest"
                            placeholder="{{ auth()->user()?->email ?: 'adresse de destination' }}"
                            class="w-64 rounded-xl border-gray-300 text-sm shadow-sm focus:border-gray-400 focus:ring-0" />

                        <x-ui.button wire:click="envoyerCourrielTest" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="envoyerCourrielTest">Envoyer un essai</span>
                            <span wire:loading wire:target="envoyerCourrielTest">Envoi…</span>
                        </x-ui.button>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <x-ui.table
                    target="activer,supprimer,save"
                    :headers="[
                        'Statut',
                        'Prix/km clando',
                        'Prix/km commande',
                        'Prix/km coursier',
                        'Min. clando',
                        'Min. commande',
                        'Min. coursier',
                        'Comm. clando',
                        'Comm. commande',
                        'Comm. livreur',
                        'VIP',
                        'Prix/kg',
                        'Actions',
                    ]">
                    @forelse ($this->configurations as $configuration)
                        @php $estActive = $configuration->estActive(); @endphp

                        <tr @class([
                            'transition-colors hover:bg-gray-50',
                            'bg-emerald-50/40' => $estActive,
                        ])>
                            <td class="whitespace-nowrap px-4 py-3">
                                <x-ui.badge :tone="$estActive ? 'success' : 'gray'">
                                    {{ $estActive ? 'Active' : 'Inactive' }}
                                </x-ui.badge>
                                <p class="mt-1 text-xs text-gray-400">
                                    {{ $configuration->created_at?->format('d/m/Y') }}
                                </p>
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-900">
                                {{ number_format($configuration->clando_kilometer, 0, ',', ' ') }} F
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-900">
                                {{ number_format($configuration->command_kilometer, 0, ',', ' ') }} F
                            </td>
                            {{-- Tiret plutôt que zéro quand le tarif coursier n'est pas
                                 renseigné : la grille est alors celle de l'application. --}}
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-900">
                                {{ $configuration->coursier_kilometer ? number_format($configuration->coursier_kilometer, 0, ',', ' ') . ' F' : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-900">
                                {{ number_format($configuration->min_price_clando, 0, ',', ' ') }} F
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-900">
                                {{ number_format($configuration->min_price_command, 0, ',', ' ') }} F
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-900">
                                {{ $configuration->min_price_coursier ? number_format($configuration->min_price_coursier, 0, ',', ' ') . ' F' : '—' }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-gray-900">
                                {{ $configuration->clando_agent_commission }} %
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-gray-900">
                                {{ $configuration->clando_agent_command }} %
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-gray-900">
                                {{ $configuration->delivery_agent_commission }} %
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-gray-900">
                                {{ $configuration->vip_percentage }} %
                            </td>
                            {{-- Tiret plutôt que zéro quand le tarif n'est pas renseigné :
                                 « 0 F » se lirait comme un poulet gratuit. --}}
                            <td class="whitespace-nowrap px-4 py-3 tabular-nums font-semibold text-gray-900">
                                {{ $configuration->price_per_kg ? number_format($configuration->price_per_kg, 0, ',', ' ') . ' F' : '—' }}
                            </td>

                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @unless ($estActive)
                                        <x-ui.button size="sm" variant="success"
                                                     wire:click="activer({{ $configuration->id }})"
                                                     wire:confirm="Appliquer cette grille tarifaire ? La configuration active sera désactivée.">
                                            Activer
                                        </x-ui.button>
                                    @endunless

                                    <x-ui.button size="sm" variant="secondary"
                                                 wire:click="openModal({{ $configuration->id }})">
                                        Modifier
                                    </x-ui.button>

                                    {{-- La ligne active n'offre pas de suppression : la
                                         proposer pour la refuser ensuite ne rendrait service
                                         à personne. --}}
                                    @unless ($estActive)
                                        <x-ui.button size="sm" variant="danger"
                                                     wire:click="supprimer({{ $configuration->id }})"
                                                     wire:confirm="Supprimer définitivement cette configuration ?">
                                            Supprimer
                                        </x-ui.button>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty
                            :colspan="13"
                            title="Aucune configuration"
                            message="Créez une grille tarifaire puis activez-la pour qu'elle s'applique." />
                    @endforelse
                </x-ui.table>
            </div>

            <x-ui.modal
                :show="$showModal"
                :title="$parameterId ? 'Modifier la configuration' : 'Nouvelle configuration'"
                :subtitle="$parameterId ? null : 'Elle sera créée inactive : à vous de l\'activer ensuite.'"
                width="max-w-3xl">
                <form id="configurationForm" wire:submit.prevent="save" class="space-y-6">
                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-500">Tarification</h3>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Prix par kilomètre — clando" for="clando_kilometer" :required="true"
                                        hint="en F CFA" :error="$errors->first('clando_kilometer')">
                                <x-ui.input id="clando_kilometer" type="number" min="0"
                                            wire:model="clando_kilometer"
                                            :error="$errors->has('clando_kilometer')" />
                            </x-ui.field>

                            <x-ui.field label="Prix par kilomètre — commande" for="command_kilometer" :required="true"
                                        hint="en F CFA" :error="$errors->first('command_kilometer')">
                                <x-ui.input id="command_kilometer" type="number" min="0"
                                            wire:model="command_kilometer"
                                            :error="$errors->has('command_kilometer')" />
                            </x-ui.field>

                            <x-ui.field label="Prix minimal — clando" for="min_price_clando" :required="true"
                                        hint="course la moins chère facturable" :error="$errors->first('min_price_clando')">
                                <x-ui.input id="min_price_clando" type="number" min="0"
                                            wire:model="min_price_clando"
                                            :error="$errors->has('min_price_clando')" />
                            </x-ui.field>

                            <x-ui.field label="Prix minimal — commande" for="min_price_command" :required="true"
                                        hint="livraison la moins chère facturable" :error="$errors->first('min_price_command')">
                                <x-ui.input id="min_price_command" type="number" min="0"
                                            wire:model="min_price_command"
                                            :error="$errors->has('min_price_command')" />
                            </x-ui.field>

                            {{-- Tarif des courses de coursier (envoi de colis entre
                                 particuliers). Facultatif : laissé vide, l'application
                                 cliente applique son tarif historique 500 F + 200 F/km. --}}
                            <x-ui.field label="Prix par kilomètre — coursier" for="coursier_kilometer"
                                        hint="en F CFA — vide : tarif historique de l'app"
                                        :error="$errors->first('coursier_kilometer')">
                                <x-ui.input id="coursier_kilometer" type="number" min="0"
                                            wire:model="coursier_kilometer"
                                            :error="$errors->has('coursier_kilometer')" />
                            </x-ui.field>

                            <x-ui.field label="Prix minimal — coursier" for="min_price_coursier"
                                        hint="prise en charge, course la moins chère facturable"
                                        :error="$errors->first('min_price_coursier')">
                                <x-ui.input id="min_price_coursier" type="number" min="0"
                                            wire:model="min_price_coursier"
                                            :error="$errors->has('min_price_coursier')" />
                            </x-ui.field>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-500">Pourcentages</h3>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Commission agent — clando" for="clando_agent_commission" :required="true"
                                        hint="% du prix de la course" :error="$errors->first('clando_agent_commission')">
                                <x-ui.input id="clando_agent_commission" type="number" min="0" max="100"
                                            wire:model="clando_agent_commission"
                                            :error="$errors->has('clando_agent_commission')" />
                            </x-ui.field>

                            <x-ui.field label="Commission agent — commande" for="clando_agent_command" :required="true"
                                        hint="% du prix de la commande" :error="$errors->first('clando_agent_command')">
                                <x-ui.input id="clando_agent_command" type="number" min="0" max="100"
                                            wire:model="clando_agent_command"
                                            :error="$errors->has('clando_agent_command')" />
                            </x-ui.field>

                            <x-ui.field label="Commission livreur" for="delivery_agent_commission" :required="true"
                                        hint="% reversé au livreur" :error="$errors->first('delivery_agent_commission')">
                                <x-ui.input id="delivery_agent_commission" type="number" min="0" max="100"
                                            wire:model="delivery_agent_commission"
                                            :error="$errors->has('delivery_agent_commission')" />
                            </x-ui.field>

                            <x-ui.field label="Pourcentage VIP" for="vip_percentage" :required="true"
                                        hint="majoration appliquée aux clandos VIP" :error="$errors->first('vip_percentage')">
                                <x-ui.input id="vip_percentage" type="number" min="0" max="100"
                                            wire:model="vip_percentage"
                                            :error="$errors->has('vip_percentage')" />
                            </x-ui.field>

                            {{-- Facultatif, à la différence des autres : les grilles déjà
                                 enregistrées n'en portent pas, et l'exiger empêcherait de
                                 rouvrir puis d'enregistrer une grille existante. Sans
                                 tarif, le mur des commandes n'ouvre pas la correction
                                 au poids. --}}
                            <x-ui.field label="Prix du kilo" for="price_per_kg"
                                        hint="sert au comptoir pour corriger un panier au poids réel"
                                        :error="$errors->first('price_per_kg')">
                                <x-ui.input id="price_per_kg" type="number" min="0"
                                            wire:model="price_per_kg"
                                            :error="$errors->has('price_per_kg')" />
                            </x-ui.field>
                        </div>
                    </div>

                    {{-- Barème de notation --}}
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Barème de notation</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            Points attribués à chaque appréciation laissée par un client.
                            Le score d'un agent est la somme de ses points ; sa note sur 5
                            en est la moyenne, ramenée sur cette échelle. Les valeurs
                            peuvent être négatives et décimales.
                        </p>

                        <div class="mt-4 grid gap-4 sm:grid-cols-3 xl:grid-cols-5">
                            @foreach (\App\Models\Parameter::APPRECIATIONS as $appreciation)
                                @php $champ = 'note_points_' . $appreciation; @endphp
                                <x-ui.field
                                    :label="\App\Support\NotationAgent::EMOJIS[$appreciation] . ' ' . \App\Support\NotationAgent::LIBELLES[$appreciation]"
                                    :for="$champ" :error="$errors->first($champ)">
                                    <x-ui.input :id="$champ" type="number" step="0.5" min="-10" max="10"
                                                wire:model="{{ $champ }}"
                                                :error="$errors->has($champ)" />
                                </x-ui.field>
                            @endforeach
                        </div>
                    </div>

                    @if ($parameterId && $this->active?->id === $parameterId)
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Cette configuration est active : vos modifications s'appliquent
                            immédiatement aux nouvelles courses et commandes.
                        </p>
                    @endif
                </form>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="closeModal">Annuler</x-ui.button>
                    <x-ui.button type="submit" form="configurationForm" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save">Enregistrer</span>
                        <span wire:loading wire:target="save">Enregistrement…</span>
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.modal>

            {{-- Édition d'une grille : ses plages horaires, empilées.

                 Les champs varient selon le service : seul le clando distingue
                 une course VIP d'une course classique, et proposer ces réglages
                 sur une livraison suggérerait un effet qu'ils n'ont pas. --}}
            @php $tarifAvecVip = in_array($tarifService, \App\Models\Tarif::SERVICES_AVEC_VIP, true); @endphp

            <x-ui.modal
                :show="$showTarifModal"
                :title="($tarifId ? 'Modifier la grille — ' : 'Nouvelle grille — ') . (\App\Models\Tarif::SERVICES[$tarifService] ?? $tarifService)"
                :subtitle="$tarifId ? null : 'Elle sera créée inactive : à vous de l\'activer ensuite.'"
                close="closeTarifModal"
                width="max-w-4xl">
                <form id="tarifForm" wire:submit.prevent="saveTarif" class="space-y-5">
                    <x-ui.field label="Nom de la grille" for="tarifLibelle"
                                hint="facultatif — ex. « Tarifs saison des pluies »"
                                :error="$errors->first('tarifLibelle')">
                        <x-ui.input id="tarifLibelle" wire:model="tarifLibelle"
                                    :error="$errors->has('tarifLibelle')" />
                    </x-ui.field>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">
                                Plages horaires ({{ count($plages) }}/5)
                            </h3>
                            <x-ui.button size="sm" variant="secondary" type="button" wire:click="ajouterPlage">
                                Ajouter une plage
                            </x-ui.button>
                        </div>

                        @foreach ($plages as $i => $plage)
                            <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4" wire:key="plage-{{ $i }}">
                                <div class="mb-3 flex items-center justify-between">
                                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">
                                        Plage {{ $i + 1 }}
                                    </p>
                                    @if (count($plages) > 1)
                                        <button type="button" wire:click="retirerPlage({{ $i }})"
                                                class="text-xs font-bold text-red-600 hover:underline">
                                            Retirer
                                        </button>
                                    @endif
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    {{-- Une plage peut franchir minuit (18:00 → 06:00) :
                                         c'est le cas d'usage principal d'une majoration
                                         de nuit, et il est traité par TarifPlage::couvre(). --}}
                                    <x-ui.field label="De" :for="'plages-'.$i.'-debut'" :required="true"
                                                :error="$errors->first('plages.'.$i.'.debut')">
                                        <x-ui.input :id="'plages-'.$i.'-debut'" type="time"
                                                    wire:model="plages.{{ $i }}.debut"
                                                    :error="$errors->has('plages.'.$i.'.debut')" />
                                    </x-ui.field>

                                    <x-ui.field label="À" :for="'plages-'.$i.'-fin'" :required="true"
                                                :error="$errors->first('plages.'.$i.'.fin')">
                                        <x-ui.input :id="'plages-'.$i.'-fin'" type="time"
                                                    wire:model="plages.{{ $i }}.fin"
                                                    :error="$errors->has('plages.'.$i.'.fin')" />
                                    </x-ui.field>

                                    <x-ui.field label="Prix par km" :for="'plages-'.$i.'-km'" :required="true"
                                                hint="en F CFA" :error="$errors->first('plages.'.$i.'.prix_km')">
                                        <x-ui.input :id="'plages-'.$i.'-km'" type="number" min="0"
                                                    wire:model="plages.{{ $i }}.prix_km"
                                                    :error="$errors->has('plages.'.$i.'.prix_km')" />
                                    </x-ui.field>

                                    <x-ui.field label="Commission" :for="'plages-'.$i.'-comm'" :required="true"
                                                :hint="$tarifAvecVip ? '% retenu, course classique' : '% retenu sur la livraison seule'"
                                                :error="$errors->first('plages.'.$i.'.commission')">
                                        <x-ui.input :id="'plages-'.$i.'-comm'" type="number" min="0" max="100" step="0.01"
                                                    wire:model="plages.{{ $i }}.commission"
                                                    :error="$errors->has('plages.'.$i.'.commission')" />
                                    </x-ui.field>

                                    <x-ui.field label="Prix minimum" :for="'plages-'.$i.'-min'" :required="true"
                                                hint="plancher facturé" :error="$errors->first('plages.'.$i.'.prix_min')">
                                        <x-ui.input :id="'plages-'.$i.'-min'" type="number" min="0"
                                                    wire:model="plages.{{ $i }}.prix_min"
                                                    :error="$errors->has('plages.'.$i.'.prix_min')" />
                                    </x-ui.field>

                                    <x-ui.field label="Prix maximum" :for="'plages-'.$i.'-max'"
                                                hint="plafond — vide : aucun"
                                                :error="$errors->first('plages.'.$i.'.prix_max')">
                                        <x-ui.input :id="'plages-'.$i.'-max'" type="number" min="0"
                                                    wire:model="plages.{{ $i }}.prix_max"
                                                    :error="$errors->has('plages.'.$i.'.prix_max')" />
                                    </x-ui.field>

                                    @if ($tarifAvecVip)
                                        <x-ui.field label="Commission VIP" :for="'plages-'.$i.'-commvip'"
                                                    hint="% retenu sur une course VIP"
                                                    :error="$errors->first('plages.'.$i.'.commission_vip')">
                                            <x-ui.input :id="'plages-'.$i.'-commvip'" type="number" min="0" max="100" step="0.01"
                                                        wire:model="plages.{{ $i }}.commission_vip"
                                                        :error="$errors->has('plages.'.$i.'.commission_vip')" />
                                        </x-ui.field>

                                        <x-ui.field label="Majoration VIP" :for="'plages-'.$i.'-majvip'"
                                                    hint="% ajouté au prix d'une course VIP"
                                                    :error="$errors->first('plages.'.$i.'.majoration_vip')">
                                            <x-ui.input :id="'plages-'.$i.'-majvip'" type="number" min="0" max="100" step="0.01"
                                                        wire:model="plages.{{ $i }}.majoration_vip"
                                                        :error="$errors->has('plages.'.$i.'.majoration_vip')" />
                                        </x-ui.field>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </form>

                <x-slot:footer>
                    <x-ui.button variant="secondary" wire:click="closeTarifModal">Annuler</x-ui.button>
                    <x-ui.button type="submit" form="tarifForm" wire:loading.attr="disabled" wire:target="saveTarif">
                        <span wire:loading.remove wire:target="saveTarif">Enregistrer</span>
                        <span wire:loading wire:target="saveTarif">Enregistrement…</span>
                    </x-ui.button>
                </x-slot:footer>
            </x-ui.modal>
        </div>
    @endvolt
</x-layouts.app>
