{{--
    Champ "mot de passe" avec bouton œil pour afficher/masquer la saisie —
    demande explicite du client, sur tous les mots de passe de la plateforme
    (connexion, "mot de passe oublié", création/modification d'un compte,
    "Mon profil", configuration mail SMTP...).

    Le bouton lui-même (.toggle-mot-de-passe) ne fait rien en JS ici : le
    comportement est délégué sur `document` dans layouts/gestion.blade.php
    ET layouts/invite.blade.php (ce composant est utilisé par des pages des
    deux layouts) — un seul script partagé, qui retrouve l'input à partir du
    `.input-group` englobant, plutôt qu'un script par écran.

    Premier composant Blade de ce projet (jusqu'ici en Blade "plat", sans
    composants) : justifié ici par la répétition EXACTE du même balisage
    (input-group + bouton + icône) sur 8 champs à travers 5 écrans
    différents — DRY, pas juste par goût du composant pour lui-même.

    Props :
    - name (obligatoire) : attribut name de l'input.
    - id : id de l'input, pour un <label for="..."> externe à ce composant
      si besoin (par défaut, identique à $name).

    Tous les autres attributs passés au composant (class, required,
    minlength, maxlength, autocomplete, placeholder...) sont relayés tels
    quels à l'input — $attributes->merge(['class' => 'form-control']) fusionne
    juste la classe Bootstrap par défaut avec une éventuelle classe
    supplémentaire, sans jamais écraser les autres attributs (voir la
    documentation Laravel sur les "Component Attributes" pour le
    comportement de :required="..." avec une valeur booléenne : l'attribut
    n'est tout simplement pas rendu si la valeur est `false`, ex.
    gestion/utilisateurs/form.blade.php).

    AUCUN `value="..."` ici, volontairement : un mot de passe ne doit JAMAIS
    être réaffiché via old() après une erreur de validation (ni ici, ni
    ailleurs sur la plateforme) — l'utilisateur le ressaisit, point.
--}}
@props(['name', 'id' => null])

<div class="input-group">
    <input type="password" name="{{ $name }}" id="{{ $id ?? $name }}"
           {{ $attributes->merge(['class' => 'form-control']) }}>
    <button type="button" class="btn btn-outline-secondary toggle-mot-de-passe" tabindex="-1"
            aria-label="Afficher le mot de passe">
        <i class="bi bi-eye"></i>
    </button>
</div>
