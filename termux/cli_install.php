<?php
/**
 * Deterministic command-line installer for TravianZ / TravaZ.
 *
 * Provisions a playable server without the web installer (and without the PHP
 * built-in server router), which is useful on environments where the web
 * wizard misbehaves (e.g. some Termux / PHP 8.5 builds). It:
 *   1. writes GameEngine/config.php from the install template,
 *   2. creates the database structure,
 *   3. generates the world map + oasis units,
 *   4. creates the admin, Multihunter and Support accounts (with villages),
 *   5. writes the var/installed marker.
 *
 * Run from the project root:
 *   php termux/cli_install.php
 *
 * Configuration via environment variables (all optional):
 *   DB_HOST DB_PORT DB_USER DB_PASS DB_NAME  (default 127.0.0.1/3306/travianz/travianzpass/travian)
 *   SERVER_NAME LANG SPEED
 *   ADMIN_NAME ADMIN_PASS ADMIN_EMAIL ADMIN_TRIBE  (game admin login)
 *   MH_PASS SUPPORT_PASS                            (Multihunter / Support logins)
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

$root = dirname(__DIR__);
chdir($root);

function envv($k, $default) { $v = getenv($k); return ($v === false || $v === '') ? $default : $v; }

// --- Settings -----------------------------------------------------------------
$cfg = [
    'DB_HOST'      => envv('DB_HOST', '127.0.0.1'),
    'DB_PORT'      => envv('DB_PORT', '3306'),
    'DB_USER'      => envv('DB_USER', 'travianz'),
    'DB_PASS'      => envv('DB_PASS', 'travianzpass'),
    'DB_NAME'      => envv('DB_NAME', 'travian'),
    'SERVER_NAME'  => envv('SERVER_NAME', 'TravaZ'),
    'LANG'         => envv('LANG_CODE', 'ru'),
    'SPEED'        => envv('SPEED', '1'),
    'ADMIN_NAME'   => envv('ADMIN_NAME', 'admin'),
    'ADMIN_PASS'   => envv('ADMIN_PASS', 'admin12345'),
    'ADMIN_EMAIL'  => envv('ADMIN_EMAIL', 'admin@travaz.local'),
    'ADMIN_TRIBE'  => (int) envv('ADMIN_TRIBE', '1'),
    'MH_PASS'      => envv('MH_PASS', envv('ADMIN_PASS', 'admin12345')),
    'SUPPORT_PASS' => envv('SUPPORT_PASS', envv('ADMIN_PASS', 'admin12345')),
];

echo "==> Generating GameEngine/config.php ...\n";

// The web installer renames install/ -> installed_<timestamp>/ after a
// successful run, so fall back to any installed_* copy of the template.
$tplPath = $root . '/install/data/constant_format.tpl';
if (!is_file($tplPath)) {
    $alt = glob($root . '/installed_*/data/constant_format.tpl');
    if ($alt) { $tplPath = $alt[0]; }
}
$tpl = @file_get_contents($tplPath);
if ($tpl === false) {
    fwrite(STDERR, "Cannot read the config template (looked for install/data/constant_format.tpl and installed_*/data/constant_format.tpl).\n");
    exit(1);
}

$now = time();
$map = [
    'ERRORREPORT' => '0', 'ERROR' => '',
    'SERVERNAME' => $cfg['SERVER_NAME'], 'STIMEZONE' => envv('TIMEZONE', 'Europe/Moscow'),
    'STARTTIME' => $now, 'SSTARTDATE' => date('Y-m-d'), 'SSTARTTIME' => date('H:i:s'),
    'LANG' => $cfg['LANG'], 'SPEED' => $cfg['SPEED'], 'MAX' => envv('WORLD_MAX', '100'),
    'INCSPEED' => '1', 'EVASIONSPEED' => '1', 'TRADERCAP' => '1', 'CRANNYCAP' => '1',
    'TRAPPERCAP' => '1', 'VILLAGE_EXPAND' => '1', 'DEMOLISH' => '10', 'STORAGE_MULTIPLIER' => '1',
    'QUEST' => 'true', 'QTYPE' => '25', 'BEGINNER' => '3600', 'WW' => 'true', 'SHOW_NATARS' => 'true',
    'NATARS_UNITS' => '1', 'NATARS_SPAWN_TIME' => '90', 'NATARS_WW_SPAWN_TIME' => '90',
    'NATARS_WW_BUILDING_PLAN_SPAWN_TIME' => '90', 'NATURE_REGTIME' => '3600',
    'OASIS_WOOD_MULTIPLIER' => '1', 'OASIS_CLAY_MULTIPLIER' => '1', 'OASIS_IRON_MULTIPLIER' => '1',
    'OASIS_CROP_MULTIPLIER' => '1', 'ACTIVATE' => 'false', 'PAYPAL_EMAIL' => '', 'PAYPAL_CURRENCY' => 'USD',
    'PLUS_PACKAGE_A_PRICE' => '1', 'PLUS_PACKAGE_A_GOLD' => '100', 'PLUS_PACKAGE_B_PRICE' => '2', 'PLUS_PACKAGE_B_GOLD' => '200',
    'PLUS_PACKAGE_C_PRICE' => '3', 'PLUS_PACKAGE_C_GOLD' => '300', 'PLUS_PACKAGE_D_PRICE' => '4', 'PLUS_PACKAGE_D_GOLD' => '400',
    'PLUS_PACKAGE_E_PRICE' => '5', 'PLUS_PACKAGE_E_GOLD' => '500', 'PLUS_TIME' => '86400', 'PLUS_PRODUCTION' => '86400',
    'MEDALINTERVAL' => '604800', 'GREAT_WKS' => 'true', 'TS_THRESHOLD' => '20', 'REG_OPEN' => 'true', 'PEACE' => 'false',
    'LOGBUILD' => 'true', 'LOGTECH' => 'true', 'LOGLOGIN' => 'true', 'LOGGOLDFIN' => 'true', 'LOGADMIN' => 'true',
    'LOGWAR' => 'true', 'LOGMARKET' => 'true', 'LOGILLEGAL' => 'true',
    'SSERVER' => $cfg['DB_HOST'], 'SPORT' => $cfg['DB_PORT'], 'SUSER' => $cfg['DB_USER'],
    'SPASS' => $cfg['DB_PASS'], 'SDB' => $cfg['DB_NAME'], 'PREFIX' => '', 'CONNECTT' => '1',
    'ACTCEN' => '', 'CENWORDS' => '', 'LIMIT_MAILBOX' => 'false', 'MAX_MAILS' => '100',
    'ARANK' => 'false', 'AEMAIL' => $cfg['ADMIN_EMAIL'], 'ANAME' => $cfg['ADMIN_NAME'],
    'ASUPPMSGS' => 'true', 'ARAIDS' => 'false',
    'NEW_FUNCTIONS_OASIS' => 'true', 'NEW_FUNCTIONS_ALLIANCE_INVITATION' => 'true', 'NEW_FUNCTIONS_EMBASSY_MECHANICS' => 'true',
    'NEW_FUNCTIONS_FORUM_POST_MESSAGE' => 'true', 'NEW_FUNCTIONS_TRIBE_IMAGES' => 'true', 'NEW_FUNCTIONS_MHS_IMAGES' => 'true',
    'NEW_FUNCTIONS_DISPLAY_ARTIFACT' => 'true', 'NEW_FUNCTIONS_DISPLAY_WONDER' => 'true', 'NEW_FUNCTIONS_VACATION' => 'true',
    'NEW_FUNCTIONS_DISPLAY_CATAPULT_TARGET' => 'true', 'NEW_FUNCTIONS_MANUAL_NATURENATARS' => 'true', 'NEW_FUNCTIONS_DISPLAY_LINKS' => 'true',
    'NEW_FUNCTIONS_SPECIAL_MEDALS_SYSTEM' => 'true', 'NEW_FUNCTIONS_MILESTONES' => 'true', 'NEW_FUNCTIONS_MEDAL_RESET' => 'true',
    'NEW_FUNCTIONS_MEDAL_3YEAR' => 'true', 'NEW_FUNCTIONS_MEDAL_5YEAR' => 'true', 'NEW_FUNCTIONS_MEDAL_10YEAR' => 'true',
    'T4_COMING' => 'false', 'BOX1' => 'false', 'BOX2' => 'false', 'BOX3' => 'false',
    'UTRACK' => 'false', 'UTOUT' => '600', 'DOMAIN' => 'localhost', 'HOMEPAGE' => 'http://localhost:8080', 'SERVER' => 'http://localhost:8080/',
];
foreach ($map as $k => $v) { $tpl = str_replace('%' . $k . '%', (string) $v, $tpl); }
if (preg_match_all('/%[A-Z_0-9]+%/', $tpl, $mm)) {
    fwrite(STDERR, "Unresolved placeholders: " . implode(',', array_unique($mm[0])) . "\n");
    exit(1);
}
file_put_contents($root . '/GameEngine/config.php', $tpl);

// --- Boot the game DB layer ---------------------------------------------------
require $root . '/GameEngine/config.php';
require $root . '/GameEngine/Database.php';
require $root . '/GameEngine/Admin/database.php';
/** @var MYSQLi_DB $database */
/** @var adm_DB $admin */

if (!($database->dblink instanceof mysqli)) {
    fwrite(STDERR, "Database connection failed. Is MariaDB running and the DB/user created?\n");
    exit(1);
}

// --- Structure ----------------------------------------------------------------
echo "==> Creating database structure ...\n";
$res = $database->createDbStructure();
if ($res === false) {
    fwrite(STDERR, "Structure already present (users exist). Run termux/reset.sh first to start clean.\n");
    exit(1);
}

// The web installer runs each step as a separate request (fresh DB connection).
// Here everything shares one connection, so reconnect between multi_query()
// batches to clear any leftover result state that would break the next one.
$database->reconnect();

// --- World --------------------------------------------------------------------
echo "==> Generating world map (this can take a few seconds) ...\n";
$database->populateWorldData();
$database->reconnect();

// --- Accounts + villages (mirrors install/include/accounts.php) ---------------
echo "==> Creating admin / Multihunter / Support accounts ...\n";
// reconnect() replaced $database->dblink; adm_DB cached the old handle at
// construction, so refresh it or getWref() hits a closed connection.
$admin->connection = $database->return_link();
$db = $database->dblink;
$center = (int) round(WORLD_MAX / 2);

// Admin (access 9) with a village near the map centre.
$aname = $cfg['ADMIN_NAME'];
if (strtolower($aname) !== 'multihunter' && strtolower($aname) !== 'support' && strtolower($aname) !== 'natars') {
    $hash = password_hash($cfg['ADMIN_PASS'], PASSWORD_BCRYPT, ['cost' => 12]);
    mysqli_query($db, "INSERT INTO " . TB_PREFIX . "users SET username = '" . $database->escape($aname) . "', password = '" . $hash . "', email = '" . $database->escape($cfg['ADMIN_EMAIL']) . "', tribe = " . $cfg['ADMIN_TRIBE'] . ", access = 9, is_bcrypt = 1, desc1 = '[#MH]\n[#TEAM]', desc2 = '[#MULTIHUNTER]\n[#roman]'")
        or (fwrite(STDERR, "admin insert: " . mysqli_error($db) . "\n") && exit(1));
    $uid = mysqli_insert_id($db);
    $x = $center;
    while (true) {
        $wid = $admin->getWref($x++, $center);
        if ($database->getVillageState($wid) == 0) {
            $database->setFieldTaken($wid);
            $database->addVillage($wid, $uid, $aname, 1);
            $database->addResourceFields($wid, $database->getVillageType($wid, false));
            $database->addUnits($wid);
            $database->addTech($wid);
            $database->addABTech($wid);
            break;
        }
    }
}

// Multihunter (id 5) at (0|0).
mysqli_query($db, "UPDATE " . TB_PREFIX . "users SET password = '" . password_hash($cfg['MH_PASS'], PASSWORD_BCRYPT, ['cost' => 12]) . "', desc1 = '[#MH]', desc2 = '[#MULTIHUNTER]' WHERE username = 'Multihunter'");
$wid = $admin->getWref(0, 0);
if ($database->getVillageState($wid) == 0) {
    $database->setFieldTaken($wid);
    $database->addVillage($wid, 5, 'Multihunter', 1);
    $database->addResourceFields($wid, $database->getVillageType($wid, false));
    $database->addUnits($wid);
    $database->addTech($wid);
    $database->addABTech($wid);
}

// Support (id 1).
mysqli_query($db, "UPDATE " . TB_PREFIX . "users SET password = '" . password_hash($cfg['SUPPORT_PASS'], PASSWORD_BCRYPT, ['cost' => 12]) . "' WHERE username = 'Support'");

// --- Mark installed -----------------------------------------------------------
@mkdir($root . '/var', 0775, true);
file_put_contents($root . '/var/installed', '');

echo "\n==> Installation complete!\n\n";
echo "  Game admin login:\n";
echo "     username: " . $cfg['ADMIN_NAME'] . "\n";
echo "     password: " . $cfg['ADMIN_PASS'] . "\n";
echo "     (access level 9 - full Admin Panel)\n\n";
echo "  Multihunter login:  username 'Multihunter', password: " . $cfg['MH_PASS'] . "\n";
echo "  Support login:      username 'Support', password: " . $cfg['SUPPORT_PASS'] . "\n\n";
echo "  Start the server and open http://localhost:8080/\n";
