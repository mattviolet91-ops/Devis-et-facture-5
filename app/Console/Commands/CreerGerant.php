<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Journal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreerGerant extends Command
{
    protected $signature = 'app:creer-gerant {--email= : Email de connexion du gérant}';

    protected $description = 'Crée le compte gérant (le mot de passe est demandé sans être affiché)';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('Email de connexion du gérant');

        $erreurEmail = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'unique:users,email'],
        ], [
            'email.unique' => 'Un compte existe déjà avec cet email.',
        ])->errors()->first('email');

        if ($erreurEmail) {
            $this->error($erreurEmail);

            return self::FAILURE;
        }

        $motDePasse = (string) $this->secret('Mot de passe (10 caractères minimum, il ne s\'affiche pas)');
        $confirmation = (string) $this->secret('Retapez le mot de passe');

        $validation = Validator::make(
            ['password' => $motDePasse, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::min(10)]],
        );

        if ($validation->fails()) {
            $this->error($validation->errors()->first('password'));

            return self::FAILURE;
        }

        $user = User::create([
            'email' => $email,
            'password' => $motDePasse,
            'role' => User::ROLE_GERANT,
            'is_active' => true,
        ]);

        Journal::ecrire('compte.creation', 'Compte gérant créé en ligne de commande : '.$user->email, $user);

        $this->info('Compte gérant créé. Vous pouvez vous connecter avec '.$user->email.'.');

        return self::SUCCESS;
    }
}
