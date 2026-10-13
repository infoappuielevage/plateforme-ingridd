<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CorbeillesGlobales;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Vue d'ensemble de toutes les corbeilles de l'application (Administration >
 * Corbeilles) — demande explicite du client après discussion sur les
 * insuffisances de la corbeille (voir le docblock de App\Support\
 * CorbeillesGlobales pour le détail). Réservé en dur au rôle admin, comme
 * les autres écrans de la section Administration qui ne passent pas par
 * App\Support\ResourceAccess (voir par exemple le docblock de
 * App\Http\Controllers\SauvegardeController) : cette vue rassemble des
 * compteurs venant de TOUTES les ressources, pas d'une seule, donc pas de
 * droit ResourceAccess unique qui lui corresponde — chaque LIGNE affichée
 * reste, elle, filtrée par le droit de sa propre ressource (voir
 * CorbeillesGlobales::elements()).
 */
class CorbeilleGlobaleController extends Controller
{
    public function index(): View
    {
        abort_unless(Auth::user()?->role === User::ROLE_ADMIN, 403);

        return view('gestion.corbeilles.index', [
            'elements' => CorbeillesGlobales::elements(),
            'total' => CorbeillesGlobales::total(),
        ]);
    }
}
