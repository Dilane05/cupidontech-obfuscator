<?php
// restore_from_backup.php
// Usage: php restore_from_backup.php <backup_folder> [--keep-env]

require __DIR__.'/utils.php';

if ($argc < 2) { echo "Usage: php restore_from_backup.php <backup_folder> [--keep-env]\n"; exit(1); }
$backup = $argv[1];
$keepEnv = in_array('--keep-env', $argv);

if (!is_dir($backup)) { echo "Erreur: dossier de backup introuvable: {$backup}\n"; exit(1); }

date_default_timezone_set('UTC');
$ts = date('YmdHis');
$preBackup = "PRE_RESTORE_BACKUP_{$ts}";
$itemsToRestore = ['app','database','routes','resources'];

function safeCopyList(array $items, string $targetBackup) {
    foreach ($items as $item) {
        if (is_dir($item) || file_exists($item)) {
            echo "  - Copie actuelle {$item} -> {$targetBackup}/{$item}\n";
            if (!copyDir($item, "{$targetBackup}/{$item}")) { echo "Erreur: copie {$item}\n"; return false; }
        } else {
            echo "  - Ignoré (n'existe pas) : {$item}\n";
        }
    }
    return true;
}

echo "🔄 Création d'une sauvegarde pré-restauration : {$preBackup}\n";
if (!safeMkdir($preBackup)) { echo "Échec création dossier\n"; exit(1); }
if (!safeCopyList($itemsToRestore, $preBackup)) { echo "Erreur lors de la sauvegarde pré-restauration\n"; exit(1); }

echo "✅ Sauvegarde pré-restauration créée.\n\n";

echo "📥 Début de la restauration depuis : {$backup}\n";
foreach ($itemsToRestore as $item) {
    $src = rtrim($backup, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
    if (!is_dir($src) && !file_exists($src)) { echo "  - Avertissement : {$src} absent dans le backup, ignoré.\n"; continue; }

    // supprimer l'existant (pré-backup) sauf .env si demandé
    if (is_dir($item) || file_exists($item)) {
        echo "  - Suppression de l'existant : {$item}\n";
        rrmdir($item);
    }

    echo "  - Restauration {$src} -> {$item}\n";
    if (!copyDir($src, $item)) { echo "Erreur lors de la restauration de {$item}. Abandon.\n"; exit(1); }
}

echo "\n✅ Restauration terminée.\n";
echo "  - Sauvegarde pré-restauration : {$preBackup}/\n";
echo "  - Backup source utilisé : {$backup}/\n";
echo "Vérifie les fichiers sensibles (.env, storage) et les permissions.\n";
