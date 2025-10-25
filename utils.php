<?php
// utils.php - fonctions utilitaires partagées

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $path = $file->getRealPath();
        if ($file->isDir()) rmdir($path);
        else unlink($path);
    }
    if (is_dir($dir)) rmdir($dir);
}

function safeMkdir(string $dir, int $mode = 0777): bool {
    if (is_dir($dir)) return true;
    return mkdir($dir, $mode, true);
}

function copyDir(string $src, string $dst): bool {
    if (!file_exists($src)) return false;
    $src = rtrim($src, DIRECTORY_SEPARATOR);
    $dst = rtrim($dst, DIRECTORY_SEPARATOR);

    if (!is_dir($src)) return false;
    if (!is_dir($dst) && !safeMkdir($dst)) return false;

    $it = new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::SELF_FIRST);
    foreach ($files as $file) {
        $destPath = $dst . DIRECTORY_SEPARATOR . $files->getSubPathName();
        if ($file->isDir()) {
            if (!is_dir($destPath) && !safeMkdir($destPath)) return false;
        } else {
            if (!copy($file->getRealPath(), $destPath)) return false;
        }
    }
    return true;
}

function rrmdir_keep_hidden_env(string $dir, array $keep = []): void {
    // supprimer récursivement mais garder certains paths
    if (!is_dir($dir)) return;
    $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $path = $file->getRealPath();
        $rel = substr($path, strlen($dir)+1);
        foreach ($keep as $k) {
            if ($rel === $k || str_starts_with($rel, rtrim($k, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
                continue 2;
            }
        }
        if ($file->isDir()) rmdir($path); else unlink($path);
    }
}
