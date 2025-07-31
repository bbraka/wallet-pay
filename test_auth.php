<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

// Create a test user
$user = User::first();
if (\!$user) {
    echo "No users found\n";
    exit;
}

// Generate token
$token = Str::random(60);
$user->api_token = hash('sha256', $token);
$user->save();

echo "Generated token: $token\n";
echo "Stored hash: {$user->api_token}\n";

// Test authentication manually
$testToken = $token;
$testHash = hash('sha256', $testToken);
echo "Test hash: $testHash\n";
echo "Hashes match: " . ($testHash === $user->api_token ? 'YES' : 'NO') . "\n";
