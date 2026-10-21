<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PermissionSyncer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('app:create-admin {email?} {--name=} {--password=} {--role=admin}')]
#[Description('Create a staff user (or give an existing user a role)')]
class CreateAdmin extends Command
{
    public function handle(PermissionSyncer $syncer): int
    {
        $syncer->sync();

        $email = $this->argument('email') ?? $this->ask('Email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $name = $this->option('name') ?? $this->ask('Name', 'Administrator');
            $password = $this->option('password') ?? $this->secret('Password (min 8 characters)');

            $validator = Validator::make(compact('email', 'password'), ['email' => 'required|email', 'password' => 'required|min:8']);
            if ($validator->fails()) {
                $this->error($validator->errors()->first());

                return self::FAILURE;
            }

            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->assignRole($this->option('role'));
        $this->info("{$user->email} now has the role [{$this->option('role')}].");

        return self::SUCCESS;
    }
}
