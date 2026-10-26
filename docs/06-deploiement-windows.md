# Déploiement chez le client (Windows, sans internet)

> Le garagiste ne voit jamais WAMP, XAMPP ni une ligne de commande : il double-clique sur une icône.

## 1. Cible

- PC principal Windows 10/11 64 bits, 8 Go RAM conseillés, SSD ; second disque ou clé USB pour les sauvegardes ; onduleur.
- Optionnel : autres postes ou smartphones du réseau local (navigateur seulement).

## 2. Arborescence installée

```
C:\GarageApp\
  apache\      Apache 2.4 (build Apache Lounge, VS17)
  php\         PHP 8.3+ x64 Thread Safe (mod_php), extensions : pdo_mysql, mbstring, intl, gd, zip, fileinfo, openssl, bcmath
  mariadb\     MariaDB 11 (my.ini : bind-address=127.0.0.1, innodb_flush_log_at_trx_commit=1)
  app\         Laravel (vendor/ et public/build/ déjà générés, sans node_modules ni tests)
  backups\     Sauvegardes locales (copie aussi vers D:\ ou clé USB si configuré)
  tools\       Scripts .bat / .ps1 (backup, restore, update, diagnostics)
```

Prérequis embarqué : Microsoft Visual C++ Redistributable (Apache et PHP).

## 3. Services Windows

```bat
C:\GarageApp\mariadb\bin\mysqld.exe --install GarageDB --defaults-file=C:\GarageApp\mariadb\my.ini
sc config GarageDB start= auto
net start GarageDB

C:\GarageApp\apache\bin\httpd.exe -k install -n GarageWeb
sc config GarageWeb start= auto
net start GarageWeb
```

`httpd.conf` : `LoadModule php_module "C:/GarageApp/php/php8apache2_4.dll"`, `PHPIniDir "C:/GarageApp/php"`,
`DocumentRoot "C:/GarageApp/app/public"` avec `AllowOverride All`, `Listen 80`.

## 4. Laravel en production

`.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=http://garage.local`, `APP_LOCALE=fr`, `APP_TIMEZONE=Africa/Algiers`,
`DB_CONNECTION=mariadb`, `DB_HOST=127.0.0.1`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=database`, `CACHE_STORE=database`.

```bat
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --class=ReferenceDataSeeder --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Tâches planifiées : Planificateur de tâches Windows → `php artisan schedule:run` chaque minute (sauvegarde quotidienne).

## 5. Accès « comme un logiciel »

- `C:\Windows\System32\drivers\etc\hosts` : `127.0.0.1 garage.local`.
- Raccourci bureau avec l'icône du garage, cible :
  `"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe" --app=http://garage.local`
- Autres postes : IP fixe du PC principal (réservation DHCP), règle de pare-feu port 80 (réseau privé), raccourci vers `http://IP`.

## 6. Sauvegardes

- `mariadb-dump --single-transaction` + zip : `database.sql` + `files/` (= `storage/app/private` : logo, factures fournisseurs,
  preuves de paiement), nommé `garage-AAAA-MM-JJ-HHmm.zip`. Identifiants passés par un fichier temporaire, jamais en ligne de commande.
- `.env` : `BACKUP_DUMP_BINARY` et `BACKUP_MYSQL_BINARY` (ex. `C:\GarageApp\mariadb\bin\mariadb-dump.exe`), `BACKUP_PATH`
  (défaut `storage/app/backups`), `BACKUP_KEEP_DAYS=30`. Le dossier de copie (D:, clé USB) se règle dans Paramètres > Sauvegardes.
- Planificateur de tâches Windows → `php artisan schedule:run` chaque minute ; `backup:run --if-missing` toutes les heures
  (sauvegarde dès que la dernière a plus de 24 h : le PC est souvent éteint la nuit). Bouton « Sauvegarder maintenant » ; rotation 30 jours dans les deux dossiers.
- Restauration guidée depuis l'application (gérant, deux confirmations dont le mot RESTAURER, sauvegarde de sécurité juste avant)
  et script `tools\restore.bat` en secours.
- Les programmes lancés depuis Apache reçoivent `SystemRoot` explicitement (`App\Support\ProcessEnv`) : sans elle, le réseau
  de Windows ne démarre pas dans le sous-processus (erreur 10106).

## 7. Installateur (Inno Setup)

`Setup-GarageApp.exe` exécute dans l'ordre : copie des fichiers, VC++ Redist, services, création base + utilisateur MariaDB
(mot de passe aléatoire écrit dans `.env`), migrations + seeders, `hosts`, raccourci, pare-feu, tâche planifiée, clé de licence.
Désinstallateur : arrête et supprime les services, **conserve** `backups\`.

## 8. Mises à jour

`Update-GarageApp-x.y.z.exe` (clé USB) : sauvegarde de la base → arrêt GarageWeb → remplacement de `app\` (sauf `.env`, `storage\`)
→ `php artisan migrate --force` → caches → redémarrage → vérification ; retour arrière automatique si la migration échoue.

## 9. Licence

Clé liée à un identifiant machine (hash du numéro de série disque + nom machine), vérifiée par un middleware ;
écran d'activation hors-ligne (le développeur génère la clé à partir de l'identifiant affiché).

- Identifiant `GA-XXXX-XXXX-XXXX` ; clé de 20 caractères `XXXXX-XXXXX-XXXXX-XXXXX` (sans 0/O ni 1/I), facile à dicter.
- Clé stockée dans `storage/app/license.key` (hors base : une restauration ne la perd pas, une copie de la base ne l'active pas).
- `LICENSE_ENABLED` : actif par défaut en production, `false` en développement.
- Développeur : `php artisan license:generate GA-XXXX-XXXX-XXXX`. **Ne pas livrer** `app/Console/Commands/Dev` (exclu par l'installateur).
- Limite assumée : le code PHP est livré en clair ; la licence empêche la copie simple vers un autre PC, pas un contournement technique.
