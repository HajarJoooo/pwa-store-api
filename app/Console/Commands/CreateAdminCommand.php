<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-admin 
                            {--role=admin : The administrative role to assign (admin or moderator)}
                            {--email= : The email address for the account}
                            {--username= : The username for the account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely create an administrative user (admin or moderator) with high-entropy secret password';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('--- PWA Store Secure Admin Creation ---');

        $role = strtolower((string) ($this->option('role') ?: $this->choice('Select role to assign', ['admin', 'moderator'], 0)));
        if (! in_array($role, ['admin', 'moderator'], true)) {
            $this->error("Invalid role '{$role}'. Permitted roles are 'admin' or 'moderator'.");

            return self::FAILURE;
        }

        // 1. Email input and validation
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Enter email address'))));
        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
        ]);
        if ($emailValidator->fails()) {
            $this->error($emailValidator->errors()->first('email'));

            return self::FAILURE;
        }

        // 2. Username input and validation
        $username = strtolower(trim((string) ($this->option('username') ?: $this->ask('Enter username'))));
        $userValidator = Validator::make(['username' => $username], [
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:users,username'],
        ]);
        if ($userValidator->fails()) {
            $this->error($userValidator->errors()->first('username'));

            return self::FAILURE;
        }

        $firstName = (string) $this->ask('Enter first name (optional)', 'Admin');
        $lastName = (string) $this->ask('Enter last name (optional)', 'User');

        // 3. Secret Password input
        $password = (string) $this->secret('Enter secure password (min 12 chars with upper, lower, digit, symbol)');
        if (! $this->validatePasswordStrength($password)) {
            $this->error('Password does not meet security requirements: must be >= 12 chars and include uppercase, lowercase, numbers, and special symbols.');

            return self::FAILURE;
        }

        $passwordConfirmation = (string) $this->secret('Confirm secure password');
        if ($password !== $passwordConfirmation) {
            $this->error('Password confirmation does not match.');

            return self::FAILURE;
        }

        // Ensure role exists
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        $user = new User([
            'first_name' => $firstName ?: 'Admin',
            'last_name' => $lastName ?: 'User',
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->is_active = true;
        $user->is_verified = true;
        $user->save();

        $user->assignRole($role);

        $this->info("Successfully created {$role} account for '{$user->username}' ({$user->email}).");

        return self::SUCCESS;
    }

    /**
     * Validate password complexity: >= 12 chars, upper, lower, numbers, symbols.
     */
    protected function validatePasswordStrength(string $password): bool
    {
        if (mb_strlen($password) < 12) {
            return false;
        }

        $hasUpper = (bool) preg_match('/[A-Z]/', $password);
        $hasLower = (bool) preg_match('/[a-z]/', $password);
        $hasDigit = (bool) preg_match('/[0-9]/', $password);
        $hasSymbol = (bool) preg_match('/[^a-zA-Z0-9]/', $password);

        return $hasUpper && $hasLower && $hasDigit && $hasSymbol;
    }
}
