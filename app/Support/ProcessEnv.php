<?php

namespace App\Support;

/**
 * Variables à donner aux programmes lancés depuis le serveur web.
 * Sous Windows, le serveur (Apache, `artisan serve`) les lance sans SystemRoot : sans elle, le réseau de
 * Windows ne démarre pas (mariadb-dump : « Can't create TCP/IP socket (10106) ») et PowerShell échoue.
 */
final class ProcessEnv
{
    /**
     * @return array<string, string>
     */
    public static function system(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $root = getenv('SystemRoot') ?: getenv('SYSTEMROOT') ?: 'C:\\Windows';

        return ['SystemRoot' => $root, 'SYSTEMROOT' => $root, 'WINDIR' => getenv('WINDIR') ?: $root];
    }
}
