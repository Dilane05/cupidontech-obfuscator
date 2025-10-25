<?php
return [
    // répertoires à traiter
    'paths' => ['app','routes','database'],

    // dossiers de ressources à sauvegarder
    'resources' => ['resources'],

    // exclusions basiques (fichiers contenant ces noms sont ignorés)
    'exclusions' => ['Kernel.php', 'Handler.php', 'Helpers.php', 'ServiceProvider.php'],

    // fichiers / dossiers à ignorer dans la sauvegarde/restauration
    'ignore_files' => ['.env', 'vendor', 'storage'],

    'backup_prefix' => 'PRODUCTION_BACKUP_',
    'preserve_env' => true,
];
