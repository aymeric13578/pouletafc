<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\IncidentSecurite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Boutons "Enregistrer"/"Signaler" de l'écran de course (clando.dart).
 *
 * Cette route v1.0 n'a aucune authentification (CLAUDE.md règle 8) — comme
 * toutes les autres de ce groupe, un id_client/id_agent reçu en paramètre
 * n'est pas vérifié. Cohérent avec l'existant (takeClandoCommand, etc.).
 */
class IncidentSecuriteController extends Controller
{
    private const AUDIO_MAX_KO = 20480; // ~20 Mo, large pour une course longue

    /**
     * Signalement immédiat : pousse une alerte dans le flux que les écrans
     * "mur"/carte du tableau de bord interrogent déjà toutes les quelques
     * secondes (voir ClandoBoardController::feed, OrderMapController::feed)
     * — pas de nouvelle infrastructure temps réel, juste une ligne de plus
     * dans une réponse déjà sondée en continu.
     */
    public function signalerCourse(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'id_clando' => ['nullable', 'integer'],
            'id_client' => ['nullable', 'integer'],
            'id_agent' => ['nullable', 'integer'],
        ]);

        $incident = IncidentSecurite::create([
            'type_course' => 'clando',
            'id_clando' => $valide['id_clando'] ?? null,
            'id_client' => $valide['id_client'] ?? null,
            'id_agent' => $valide['id_agent'] ?? null,
            'type' => IncidentSecurite::SIGNALEMENT,
            'statut' => IncidentSecurite::NOUVEAU,
        ]);

        return response()->json([
            'response' => 200,
            'data' => ['id' => $incident->id],
        ]);
    }

    /**
     * Dépôt de l'enregistrement audio à la fin de la course. Stocké sur le
     * disque privé "incidents-securite" (voir config/filesystems.php) —
     * jamais public_path('upload'), qu'un visiteur peut lister sans le
     * moindre identifiant.
     */
    public function enregistrerAudioCourse(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'id_clando' => ['nullable', 'integer'],
            'id_client' => ['nullable', 'integer'],
            'id_agent' => ['nullable', 'integer'],
            'audio' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp4,audio/aac,audio/wav,audio/x-wav,audio/3gpp,audio/ogg', 'max:' . self::AUDIO_MAX_KO],
        ]);

        $fichier = $request->file('audio');
        // extension() : devinée depuis le contenu réel, jamais le nom fourni
        // par le client (voir CLAUDE.md, correctif du 2026-08-30 appliqué à
        // tous les points d'upload de l'app pour la même raison).
        $nom = uniqid('course_', true) . '.' . $fichier->extension();
        $fichier->storeAs('', $nom, 'incidents-securite');

        $incident = IncidentSecurite::create([
            'type_course' => 'clando',
            'id_clando' => $valide['id_clando'] ?? null,
            'id_client' => $valide['id_client'] ?? null,
            'id_agent' => $valide['id_agent'] ?? null,
            'type' => IncidentSecurite::ENREGISTREMENT,
            'audio_path' => $nom,
            'statut' => IncidentSecurite::NOUVEAU,
        ]);

        return response()->json([
            'response' => 200,
            'data' => ['id' => $incident->id],
        ]);
    }
}
