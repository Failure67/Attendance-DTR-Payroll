<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin
                        {--name= : The name of the admin user}
                        {--email= : The email of the admin user}
                        {--password= : The password for the admin user (min: 8 characters)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user with the given credentials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->option('name') ?: $this->ask('Enter admin name');
        $email = $this->option('email') ?: $this->ask('Enter admin email');
        $password = $this->option('password') ?: $this->secret('Enter admin password (min: 8 characters)');

        // Validate input
        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ],
            [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:8',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        // Create admin user
        $user = User::create([
            'username' => strtolower(str_replace(' ', '.', $name)),
            'full_name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);

        $this->info('Admin user created successfully!');
        $this->line('Name: ' . $user->full_name);
        $this->line('Email: ' . $user->email);
        $this->line('Role: ' . $user->role);

        return 0;
    }
}
