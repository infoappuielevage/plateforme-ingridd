<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['chemin'] = $data['fichier'];
        $data['extension'] = pathinfo($data['nom_original'] ?? '', PATHINFO_EXTENSION);
        $data['taille'] = Storage::disk('documents')->exists($data['chemin'])
            ? Storage::disk('documents')->size($data['chemin'])
            : 0;
        $data['uploaded_by'] = Auth::id();

        // Cas rare (créer directement un diplôme/attestation déjà marqué
        // "Retiré") mais géré pour rester cohérent avec EditDocument — voir
        // ce fichier pour le cas plus courant (marquer un document existant
        // comme retiré).
        if (($data['statut_retrait'] ?? null) === Document::STATUT_RETRAIT_RETIRE) {
            $data['retrait_enregistre_par_id'] = Auth::id();
            $data['date_retrait'] = $data['date_retrait'] ?? now();
        }

        unset($data['fichier']);

        return $data;
    }
}
