<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tokens = \Laravel\Sanctum\PersonalAccessToken::orderBy('last_used_at', 'desc')->take(5)->get();
echo "Recent Sanctum Tokens:\n";
foreach ($tokens as $t) {
    $u = \App\Models\User::find($t->tokenable_id);
    echo "  - Token ID {$t->id} | User #{$t->tokenable_id} ({$u?->name} / {$u?->email}) | Last Used: {$t->last_used_at}\n";
}
