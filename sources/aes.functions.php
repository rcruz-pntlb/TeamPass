<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @package   Teampass
 * @author    Nils Laumaillé <nils@teamapss.net>
 * @copyright 2009-2019 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * @version   GIT: <git_id>
 * @link      https://www.teampass.net
 */

require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] === false || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'items', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

/*
 * Define Timezone
**/
if (isset($SETTINGS['timezone']) === true) {
    date_default_timezone_set($SETTINGS['timezone']);
} else {
    date_default_timezone_set('UTC');
}

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
$link->set_charset(DB_ENCODING);

// Protect POST
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);

if (null !== $post_type) {
    switch ($post_type) {
        /*
        * CASE
        * creating a new user's public/private keys
        */
        case 'user_change_pair_keys':
            // Decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );
            $post_user_id = filter_var($dataReceived['user_id'], FILTER_SANITIZE_NUMBER_INT);
            $post_user_pwd = filter_var($dataReceived['user_pwd'], FILTER_SANITIZE_STRING);

            // Get user info
            $userInfo = DB::queryFirstRow(
                'SELECT id, public_key, private_key
                FROM '.prefixTable('users').'
                WHERE id = %i',
                $post_user_id
            );

            // Generate keys
            $userKeys = generateUserKeys($post_user_pwd);

            // Store
            DB::update(
                prefixTable('users'),
                array(
                    'public_key' => $userKeys['public_key'],
                    'private_key' => $userKeys['private_key'],
                ),
                'id = %i',
                $post_user_id
            );
        break;
    }
}

/**
 * Undocumented function.
 *
 * @param [type] $user_id
 * @param [type] $private_key
 * @param [type] $public_key
 * @param [type] $SETTINGS
 *
 * @return void
 */
function changeUserObjectKeyForItems($user_id, $private_key, $public_key, $SETTINGS)
{
    // Its goal is to adapt all user Items object key

    // Remove all existing object keys
    DB::delete(
        prefixTable('sharekeys_items'),
        'user_id = %i',
        $user_id
    );

    // Create objectkey for each item
    $rows = DB::query(
        'SELECT id, pw
        FROM '.prefixTable('items').'
        WHERE perso = 0',
        $post_item_id
    );
    foreach ($rows as $record) {
    }
}

/**
 * Delete all objects keys for one user.
 *
 * @param string $user_id  User id
 * @param string $SETTINGS Teampass settings
 *
 * @return void
 */
function deleteUserObjetsKeys($user_id, $SETTINGS)
{
    // Its goal is to adapt all user Items object key
    include_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

    // Remove all existing object keys
    DB::delete(
        prefixTable('sharekeys_items'),
        'user_id = %i',
        $user_id
    );

    // Remove all existing object keys
    DB::delete(
        prefixTable('sharekeys_logs'),
        'user_id = %i',
        $user_id
    );

    // Remove all existing object keys
    DB::delete(
        prefixTable('sharekeys_fields'),
        'user_id = %i',
        $user_id
    );

    // Remove all existing object keys
    DB::delete(
        prefixTable('sharekeys_suggestions'),
        'user_id = %i',
        $user_id
    );

    // Remove all existing object keys
    DB::delete(
        prefixTable('sharekeys_files'),
        'user_id = %i',
        $user_id
    );
}
