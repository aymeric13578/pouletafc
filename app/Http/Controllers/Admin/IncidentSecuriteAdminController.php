<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IncidentSecurite;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Lecture des enregistrements audio du bouton "panique" (page Sécurité du
 * tableau de bord). Séparé de IncidentSecuriteController (API v1.0, sans
 * authentification, voir CLAUDE.md règle 8) : ce contrôleur-ci vit derrière
 * 'auth' + EnsureUserIsStaff (voir routes/web.php), seul moyen de lire un
 * fichier stocké sur le disque privé 'incidents-securite'.
 */
class IncidentSecuriteAdminController extends Controller
{
    public function audio(IncidentSecurite $incident): Response
    {
        abort_unless($incident->audio_path, 404);
        abort_unless(Storage::disk('incidents-securite')->exists($incident->audio_path), 404);

        // response()->file() (plutôt que Storage::response()) pour le support
        // des requêtes Range : un lecteur audio doit pouvoir avancer dans le
        // fichier sans le retélécharger en entier depuis le début.
        return response()->file(
            Storage::disk('incidents-securite')->path($incident->audio_path)
        );
    }
}
