<?php

namespace App\Console\Commands;

use App\Models\CentralAdmin;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateCentralAdmin extends Command
{
    protected $signature = 'pos:create-admin {email}';

    protected $description = 'Create a central administrator using a hidden password prompt';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command in an interactive terminal.');

            return self::FAILURE;
        }

        $email = Str::lower(trim($this->argument('email')));
        $password = $this->secret('Password (at least 15 characters)', false);
        $confirmation = $this->secret('Confirm password', false);
        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:15', 'regex:/\A[^\x00]{1,72}\z/s', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            CentralAdmin::create(['email' => $email, 'password' => Hash::make($password)]);
        } catch (UniqueConstraintViolationException) {
            $this->error('An administrator with that email already exists.');

            return self::FAILURE;
        }

        $this->info('Central administrator created.');

        return self::SUCCESS;
    }
}
