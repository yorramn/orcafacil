<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Rules\Password;

final class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', new Password, 'confirmed'],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => strtolower($input['email']),
                'password' => $input['password'],
            ]);

            $tenant = Tenant::create([
                'name' => $user->name,
                'slug' => Str::slug($user->name).'-'.Str::lower(Str::random(8)),
            ]);

            $user->tenants()->attach($tenant, ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]);
            $tenant->subscriptions()->create(['plan' => 'basic', 'status' => 'trialing', 'provider' => 'fake', 'trial_ends_at' => now()->addDays(14), 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

            return $user;
        });
    }
}
