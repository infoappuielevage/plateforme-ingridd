{{--
    Partiel d'une ligne d'inscription (voir show.blade.php) — affichage normal
    avec un bouton "Modifier" qui bascule une petite ligne de formulaire en
    place (niveau/filière uniquement : l'année académique ne se change pas
    ici, voir EtudiantController::modifierInscription()). Variables
    attendues : $inscription (App\Models\EtudiantInscription, avec ses
    relations déjà chargées), $etudiant, $niveaux, $filieres, $peutModifier,
    $peutSupprimer.
--}}
<tr id="inscription{{ $inscription->id }}">
    <td>{{ $inscription->anneeAcademique->libelle }}</td>
    <td>{{ $inscription->niveau->libelle }}</td>
    <td>{{ $inscription->filiere->libelle }}</td>
    @if ($peutModifier || $peutSupprimer)
        <td class="text-end">
            <div class="btn-group btn-group-sm">
                @if ($peutModifier)
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#modifierInscription{{ $inscription->id }}" title="Modifier">
                        <i class="bi bi-pencil"></i>
                    </button>
                @endif
                @if ($peutSupprimer)
                    <form method="POST" action="{{ route('gestion.etudiants.inscriptions.destroy', ['etudiant' => $etudiant->id, 'inscription' => $inscription->id]) }}" class="d-inline"
                          onsubmit="return confirm('Retirer cette inscription ? Cette action est définitive.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger" title="Retirer">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </form>
                @endif
            </div>
        </td>
    @endif
</tr>
@if ($peutModifier)
    {{-- La ligne <tr> reste TOUJOURS dans le DOM (un <tr> en "collapse" se
         comporte mal avec le plugin Bootstrap, pensé pour des <div>, voir
         les autres partiels _case.blade.php du projet) : seul le <div>
         intérieur bascule. --}}
    <tr>
        <td colspan="4" class="p-0 border-0">
            <div class="collapse px-3 pb-2" id="modifierInscription{{ $inscription->id }}">
                <form method="POST" action="{{ route('gestion.etudiants.inscriptions.update', ['etudiant' => $etudiant->id, 'inscription' => $inscription->id]) }}" class="row g-2 align-items-end">
                    @csrf
                    @method('PUT')
                    <div class="col-md-4">
                        <select name="niveau_id" class="form-select form-select-sm" required>
                            @foreach ($niveaux as $niveau)
                                <option value="{{ $niveau->id }}" @selected($niveau->id === $inscription->niveau_id)>{{ $niveau->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <select name="filiere_id" class="form-select form-select-sm" required>
                            @foreach ($filieres as $filiere)
                                <option value="{{ $filiere->id }}" @selected($filiere->id === $inscription->filiere_id)>{{ $filiere->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-sm btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </td>
    </tr>
@endif
