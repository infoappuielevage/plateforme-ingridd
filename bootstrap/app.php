<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Corrige tout texte mal encodé reçu du navigateur avant qu'il
        // n'atteigne les contrôleurs/composants Livewire — voir
        // App\Http\Middleware\CorrigerEncodageRequete pour le détail
        // (repris tel quel d'ingridd-platform : ce correctif est générique,
        // pas spécifique au domaine des soutenances).
        $middleware->web(append: [
            \App\Http\Middleware\CorrigerEncodageRequete::class,
            // Doit tourner sur CHAQUE requête (pas seulement les routes
            // gestion.* protégées par 'auth') : voir le docblock de
            // RejeterUtilisateurBloque pour pourquoi.
            \App\Http\Middleware\RejeterUtilisateurBloque::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
