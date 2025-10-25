#!/usr/bin/env bash
set -e

echo "-> Lancement des tests pour cupidontech/obfuscator"

# Assure que composer dep est installée (php-parser)
php -r "if (!file_exists('vendor/autoload.php')) { echo 'Veuillez installer nikic/php-parser via composer.\n'; exit(1); }" || exit 1

php obfuscator.php

ls -la PRODUCTION_BACKUP_*

# Restaurer (exemple)
BACKUP=$(ls -d PRODUCTION_BACKUP_* | tail -n1)
php restore_from_backup.php "$BACKUP"

echo "Tests terminés. Vérifie manuellement l'app."
