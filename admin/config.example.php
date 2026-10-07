<?php
// Copy to config.php and fill the values. Never publish this file with real secrets.
return [
    'github_owner' => 'mio0amd-oss',
    'github_repo' => 'love_kill_me',
    'github_branch' => 'main',
    'github_token' => 'PUT_GITHUB_TOKEN_HERE',
    // Generate with: php -r "echo password_hash('YOUR_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
    'admin_password_hash' => 'PUT_PASSWORD_HASH_HERE',
    'session_name' => 'subkazani_admin',
    'max_audio_bytes' => 30 * 1024 * 1024,
];
