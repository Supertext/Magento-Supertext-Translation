<?php
// Railway allows only a few volumes per project, so the demo keeps no files between
// deploys: Magento's app/etc/env.php (database access and the encryption key) is
// stored in the database next to the shop.
//   php state.php db           creates the database if missing; prints "host port user name"
//   php state.php get          prints the saved env.php (exit 1 if none)
//   php state.php put < file   saves it
$url = parse_url((string) getenv('DATABASE_URL'));
if (!$url || !in_array($url['scheme'] ?? '', ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "DATABASE_URL must be a mysql:// URL\n");
    exit(1);
}
$host = $url['host'];
$port = (int) ($url['port'] ?? 3306);
$user = rawurldecode($url['user'] ?? '');
$pass = rawurldecode($url['pass'] ?? '');
$name = getenv('MAGENTO_DB_NAME') ?: 'magento';
if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
    fwrite(STDERR, "MAGENTO_DB_NAME may only contain letters, digits and _\n");
    exit(1);
}

for ($i = 0; ; $i++) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (PDOException $e) {
        if ($i >= 20) {
            fwrite(STDERR, "Cannot reach MySQL: {$e->getMessage()}\n");
            exit(1);
        }
        sleep(3);
    }
}
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");
$pdo->exec('CREATE TABLE IF NOT EXISTS supertext_demo_state (name VARCHAR(64) PRIMARY KEY, value MEDIUMTEXT NOT NULL)');

switch ($argv[1] ?? '') {
    case 'db':
        echo "$host $port $user $name\n";
        break;
    case 'get':
        $value = $pdo->query("SELECT value FROM supertext_demo_state WHERE name = 'env.php'")->fetchColumn();
        if ($value === false) {
            exit(1);
        }
        echo $value;
        break;
    case 'put':
        $value = stream_get_contents(STDIN);
        if (!str_contains($value, "'crypt'")) {
            fwrite(STDERR, "Not a Magento env.php\n");
            exit(1);
        }
        $pdo->prepare("REPLACE INTO supertext_demo_state (name, value) VALUES ('env.php', ?)")->execute([$value]);
        break;
    default:
        fwrite(STDERR, "usage: state.php db|get|put\n");
        exit(2);
}
