<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clando;
use Illuminate\Http\Request;

class ClandoController extends Controller
{
    /**
     * Afficher la liste des courses
     */
    public function clandoList()
    {
        $clandos = Clando::with('users')->latest()->paginate(10);
        return view('admin.clando.clando_list', compact('clandos'));
    }

    /**
     * Changer le statut d'une course
     */
    public function changeStatus($id, $status)
    {
        try {
            $clando = Clando::findOrFail($id);
            $clando->status = $status;
            $clando->save();

            $message = '';
            switch ($status) {
                case 'want':
                    $message = 'Course mise en attente d\'un agent';
                    break;
                case 'take':
                    $message = 'Course prise en charge';
                    break;
                case 'process':
                    $message = 'Course en cours de dépôt';
                    break;
                case 'declin':
                    $message = 'Course annulée';
                    break;
                case 'Success':
                    $message = 'Course terminée avec succès';
                    break;
                default:
                    $message = 'Statut mis à jour';
            }

            return redirect()->route('clandolist')->with('message', $message);
        } catch (\Exception $e) {
            return redirect()->route('clandolist')->with('error', 'Erreur lors de la mise à jour du statut : ' . $e->getMessage());
        }
    }
}