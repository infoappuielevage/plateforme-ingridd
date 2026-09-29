<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
        Chaque sauvegarde contient la base de données et tous les documents archivés, dans un seul fichier .zip.
        Les plus anciennes sont supprimées automatiquement au-delà du nombre à conserver (réglable dans
        <span class="font-medium">Administration &gt; Paramètres</span>).
    </p>

    <div class="mb-6">
        <x-filament::button wire:click="creer" wire:loading.attr="disabled" icon="heroicon-o-plus">
            Créer une sauvegarde maintenant
        </x-filament::button>
    </div>

    @if (empty($sauvegardes))
        <p class="text-sm text-gray-500 dark:text-gray-400">Aucune sauvegarde pour le moment.</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr class="text-left">
                        <th class="py-2 px-4 font-medium">Fichier</th>
                        <th class="py-2 px-4 font-medium">Taille</th>
                        <th class="py-2 px-4 font-medium">Créée le</th>
                        <th class="py-2 px-4"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sauvegardes as $sauvegarde)
                        <tr class="border-t border-gray-100 dark:border-white/5">
                            <td class="py-2 px-4 font-mono text-xs">{{ $sauvegarde['nom'] }}</td>
                            <td class="py-2 px-4">{{ $sauvegarde['taille'] }}</td>
                            <td class="py-2 px-4">{{ $sauvegarde['date'] }}</td>
                            <td class="py-2 px-4 text-right whitespace-nowrap">
                                <a
                                    href="{{ route('sauvegardes.telecharger', $sauvegarde['nom']) }}"
                                    class="text-primary-600 hover:underline"
                                    target="_blank"
                                >
                                    Télécharger
                                </a>
                                <button
                                    type="button"
                                    wire:click="supprimer('{{ $sauvegarde['nom'] }}')"
                                    wire:confirm="Supprimer définitivement cette sauvegarde ?"
                                    class="ml-3 text-danger-600 hover:underline"
                                >
                                    Supprimer
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
