<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ExternalUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'netbill:create-super-admin {email} {name}';

    protected $description = 'Buat akun Super Admin baru dan cetak tautan set-password';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $name  = trim($this->argument('name'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Format email tidak valid: {$email}");
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->update([
                'name'      => $name,
                'role'      => Role::SUPER_ADMIN,
                'pop_id'    => null,
                'is_active' => true,
            ]);
            $this->info("Akun yang sudah ada diperbarui menjadi Super Admin: {$user->email}");
        } else {
            $user = User::create([
                'name'      => $name,
                'email'     => $email,
                'password'  => Hash::make(Str::random(32)),
                'role'      => Role::SUPER_ADMIN,
                'pop_id'    => null,
                'is_active' => true,
            ]);
            $this->info("Akun Super Admin baru berhasil dibuat: {$user->email}");
        }

        $token = Password::broker()->createToken($user);
        $setUrl = ExternalUrl::route('password.set', ['token' => $token, 'email' => $user->email]);

        ActivityLogger::log('staff.create_super_admin_cli', "Membuat/memperbarui Super Admin via CLI: {$user->email}", $user);

        $this->newLine();
        $this->line("Tautan set-password (berlaku 24 jam):");
        $this->warn($setUrl);
        $this->newLine();

        return self::SUCCESS;
    }
}

