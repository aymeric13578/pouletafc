<?php

namespace App\Support;

use App\Models\Location;
use App\Models\Quarter;
use Illuminate\Database\Eloquent\Model;

/**
 * Changer le lieu de livraison d'une commande ou d'une course.
 *
 * L'adresse était figée à la création et plus personne ne pouvait la corriger :
 * un client qui se trompe de quartier, une adresse illisible au téléphone, une
 * livraison qu'on déplace — il fallait annuler et ressaisir, en perdant
 * l'historique et l'agent déjà attribué.
 *
 * Trois écrans en ont besoin — le mur des commandes, la carte des clandos et le
 * tableau de bord — et ils passent tous par ici : chacun aurait sinon sa propre
 * idée de ce qu'est un changement de lieu, et de ce qu'il faut écrire.
 *
 * Ce qui est écrit, justement : le nom du lieu ET ses coordonnées. Ne changer
 * que le libellé enverrait le livreur à l'ancien point avec la nouvelle adresse
 * sous les yeux — la panne exacte qu'on vient de corriger ailleurs.
 */
class LieuDeLivraison
{
    /**
     * Applique un lieu enregistré à une commande ou une course.
     */
    public function appliquer(Model $ligne, Location $lieu): bool
    {
        $latitude = $this->nombre($lieu->latitude);
        $longitude = $this->nombre($lieu->longitude);

        /*
        | Une commande et une course ne nomment pas leurs colonnes pareil : la
        | première porte « address » et « latitude », la seconde « destinationName »
        | et « latDestination ». On écrit ce qui existe, plutôt que d'imposer un
        | modèle commun à deux tables qui n'en ont jamais eu.
        */
        $champs = array_filter([
            'address' => $this->libelle($lieu),
            'destinationName' => $this->libelle($lieu),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'latDestination' => $latitude,
            'lonDestination' => $longitude,
        ], fn ($valeur, $colonne) => $valeur !== null
            && ColonnesDisponibles::existe($ligne->getTable(), $colonne),
            ARRAY_FILTER_USE_BOTH);

        if ($champs === []) {
            return false;
        }

        foreach ($champs as $colonne => $valeur) {
            $ligne->{$colonne} = $valeur;
        }

        return $ligne->save();
    }

    /**
     * Enregistre un lieu qui n'existait pas encore.
     *
     * Le comptoir a souvent la bonne adresse au téléphone alors qu'elle n'est
     * pas encore dans la liste : l'obliger à passer par l'écran des lieux, puis
     * à revenir, c'est l'assurance qu'il ne le fera pas et livrera au jugé.
     *
     * Le lieu créé rejoint la liste commune : il servira aux commandes
     * suivantes, et aux agents sur le terrain.
     */
    public function creer(array $donnees, ?int $idUtilisateur = null): Location
    {
        $nom = trim((string) ($donnees['name'] ?? ''));
        $quartier = $this->quartier($donnees['quarter_id'] ?? null, $donnees['quarter_name'] ?? null, $idUtilisateur);

        // Un lieu du même nom dans le même quartier existe peut-être déjà : on
        // le réutilise plutôt que de peupler la liste de doublons, ce qui est
        // exactement ce qui est arrivé côté agents.
        $existant = Location::where('id_quarter', $quartier?->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($nom)])
            ->first();

        if ($existant) {
            return $existant;
        }

        return Location::create([
            'name' => $nom,
            'id_quarter' => $quartier?->id,
            'id_user' => $idUtilisateur,
            // Même valeur que les lieux saisis par les agents : la colonne est
            // un enum sans défaut, l'omettre fait échouer l'insertion.
            'status' => 'pending',
            'latitude' => isset($donnees['latitude']) ? (string) $donnees['latitude'] : null,
            'longitude' => isset($donnees['longitude']) ? (string) $donnees['longitude'] : null,
        ]);
    }

    /**
     * Le quartier désigné, ou créé s'il est nommé sans exister.
     */
    private function quartier($id, ?string $nom, ?int $idUtilisateur): ?Quarter
    {
        if ($id && $quartier = Quarter::find($id)) {
            return $quartier;
        }

        $nom = trim((string) $nom);

        if ($nom === '') {
            return null;
        }

        $existant = Quarter::whereRaw('LOWER(name) = ?', [mb_strtolower($nom)])->first();

        return $existant ?? Quarter::create([
            'name' => $nom,
            'status' => 'pending',
            'id_user' => $idUtilisateur,
        ]);
    }

    /**
     * Le nom d'un lieu, avec son quartier.
     *
     * « Marché central » seul ne situe pas une livraison quand plusieurs
     * quartiers en ont un.
     */
    public function libelle(Location $lieu): string
    {
        $quartier = $lieu->quarter?->name;

        return $quartier ? $lieu->name . ' — ' . $quartier : (string) $lieu->name;
    }

    /**
     * La liste servie aux écrans, prête à remplir un menu déroulant.
     */
    public function listePourLesEcrans(): array
    {
        return Location::with('quarter:id,name')
            ->orderBy('name')
            ->get(['id', 'id_quarter', 'name', 'latitude', 'longitude'])
            ->map(fn (Location $lieu) => [
                'id' => $lieu->id,
                'name' => $lieu->name,
                'quarter' => $lieu->quarter?->name,
                'libelle' => $this->libelle($lieu),
                // Un lieu sans coordonnées se choisit quand même — il nomme la
                // livraison — mais l'écran doit pouvoir le signaler : il ne
                // placera aucun point sur la carte.
                'localise' => $this->nombre($lieu->latitude) !== null
                    && $this->nombre($lieu->longitude) !== null,
            ])
            ->all();
    }

    private function nombre($valeur): ?float
    {
        if ($valeur === null || $valeur === '' || ! is_numeric($valeur)) {
            return null;
        }

        $nombre = (float) $valeur;

        // Un zéro tombe au large du golfe de Guinée : c'est « rien de relevé »,
        // pas une position.
        return $nombre === 0.0 ? null : $nombre;
    }
}
