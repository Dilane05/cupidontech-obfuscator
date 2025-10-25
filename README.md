# cupidontech/obfuscator

Obfuscator simple pour projets Laravel — backup portable, obfuscation AST via nikic/php-parser, restore cross-platform.

## Installation
- Copier les fichiers à la racine de votre projet Laravel.
- Installer la dépendance `nikic/php-parser` via composer :
  ```
  composer require nikic/php-parser
  ```

## Utilisation
- Obfuscation : `php obfuscator.php [config_file]`
- Restauration : `php restore_from_backup.php <backup_folder>`

## Précautions
- Testez sur une copie.
- Ce système utilise `eval()` et place une clé dans chaque loader — sécurité limitée.
- Sauvegardez ailleurs les backups.
"# cupidontech-obfuscator" 
