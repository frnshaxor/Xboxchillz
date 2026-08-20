<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSandboxAdmin extends Command
{
    protected $signature = 'sandbox:create-admin {email : Email admin sandbox} {--name= : Nama admin}';

    protected $description = 'Membuat atau memperbarui admin khusus database sandbox';

    public function handle(): int
    {
        $email = strtolower((string) $this->argument('email'));
        $name = (string) ($this->option('name') ?: $this->ask('Nama admin', 'Sandbox Admin'));
        $password = (string) $this->secret('Kata sandi admin (minimal 12 karakter)');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($name) < 2 || strlen($password) < 12) {
            $this->error('Email, nama, atau kata sandi tidak valid. Kata sandi minimal 12 karakter.');

            return self::FAILURE;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($password), 'is_admin' => true, 'active' => true],
        );
        $this->info('Admin sandbox siap. Kredensial produksi tidak digunakan.');

        return self::SUCCESS;
    }
}
