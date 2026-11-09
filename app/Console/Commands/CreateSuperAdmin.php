<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Procédure d'installation : création du PREMIER super administrateur.
 * Le mot de passe est saisi de façon masquée (jamais en argument, jamais dans l'historique du shell).
 */
class CreateSuperAdmin extends Command
{
    protected $signature = 'wodi:create-super-admin {email} {--first-name=} {--last-name=}';

    protected $description = 'Crée un super administrateur Maison WODI (installation)';

    public function handle(): int
    {
        $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
        $email = mb_strtolower($this->argument('email'));
        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->error('Un compte existe déjà avec cette adresse.');

            return self::FAILURE;
        }
        $first = $this->option('first-name') ?: $this->ask('Prénom');
        $last = $this->option('last-name') ?: $this->ask('Nom');
        $password = $this->secret('Mot de passe (12 caractères minimum)');
        $confirm = $this->secret('Confirmation');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $confirm],
            ['email' => ['required', 'email'], 'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()]],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create(['first_name' => $first, 'last_name' => $last, 'email' => $email, 'password' => $password, 'email_verified_at' => now()]);
        $user->assignRole(Role::SuperAdmin->value);
        $this->info("Super administrateur créé : {$email}");

        return self::SUCCESS;
    }
}
