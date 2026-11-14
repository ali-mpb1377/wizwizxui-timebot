<?php
/**
 * panelapi.php
 *
 * Single API layer between the bot and the x-ui family of panels.
 * Every call from the bot to a panel goes through panelApi(), so routes,
 * authentication, timeouts and error handling live in one place.
 *
 * Panel types (server_config.type):
 *   sanaei  -> 3x-ui           API base: /panel/api/inbounds
 *   alireza -> alireza x-ui    API base: /xui/API/inbounds
 *   normal  -> vaxilu / niduka x-ui. These panels ship no public API, so the
 *              panel's own /xui/inbound/* routes are used (same as before).
 *   marzban -> already REST + Bearer token (see the *Marzban* functions)
 */

if(!defined('PANEL_API_LOGIN_TIMEOUT')) define('PANEL_API_LOGIN_TIMEOUT', 3);
if(!defined('PANEL_API_TIMEOUT')) define('PANEL_API_TIMEOUT', 15);
// A server saved with this username uses a 3x-ui API token (Settings -> Security -> API Token) stored in `password`.
// Bearer-token requests need no login and no csrf token.
if(!defined('PANEL_API_TOKEN_USER')) define('PANEL_API_TOKEN_USER', 'api-token');

function panelApiServer($server_id){
    global $connection;
    static $cache = [];
    if(isset($cache[$server_id])) return $cache[$server_id];

    $stmt = $connection->prepare("SELECT * FROM `server_config` WHERE `id` = ?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if($info) $cache[$server_id] = $info;
    return $info;
}

function panelApiPrefix($type){
    if($type == "sanaei") return "/panel/api/inbounds";
    if($type == "alireza") return "/xui/API/inbounds";
    return "/xui/inbound";
}

/**
 * Low level HTTP call. Returns ['body' => string|false, 'header' => string, 'error' => string].
 * $fields: array (multipart), string (raw body) or null.
 */
function panelApiHttp($method, $url, $fields = null, $cookie = null, $timeout = PANEL_API_TIMEOUT, $connectTimeout = 15, $needHeader = false, $csrf = null, $extraHeaders = []){
    $curl = curl_init();
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:108.0) Gecko/20100101 Firefox/108.0',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.5',
        'X-Requested-With: XMLHttpRequest',
    ];
    if($cookie) $headers[] = 'Cookie: ' . $cookie;
    if($csrf) $headers[] = 'X-CSRF-Token: ' . $csrf;
    foreach($extraHeaders as $h) $headers[] = $h;

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HEADER => $needHeader,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    if($method == 'POST'){
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        if($fields !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
    }else{
        curl_setopt($curl, CURLOPT_HTTPGET, true);
    }

    $response = curl_exec($curl);
    $error = curl_error($curl);
    $header = '';
    $body = $response;
    if($needHeader && $response !== false){
        $size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $size);
        $body = substr($response, $size);
    }
    curl_close($curl);

    return ['body' => $body, 'header' => $header, 'error' => $error];
}

/**
 * Merges the Set-Cookie headers of a response into a cookie string
 * (same cookie name = newest value wins).
 */
function panelApiMergeCookies($cookie, $header){
    $jar = [];
    if($cookie){
        foreach(explode(';', $cookie) as $item){
            $item = trim($item);
            if($item === '' || strpos($item, '=') === false) continue;
            list($k, $v) = explode('=', $item, 2);
            $jar[$k] = $v;
        }
    }
    preg_match_all('/^Set-Cookie:\s*([^;\r\n]*)/mi', (string)$header, $matches);
    foreach($matches[1] as $item){
        $item = trim($item);
        if($item === '' || strpos($item, '=') === false) continue;
        list($k, $v) = explode('=', $item, 2);
        $jar[$k] = $v;
    }
    $out = [];
    foreach($jar as $k => $v) $out[] = $k . '=' . $v;
    return implode('; ', $out);
}

/**
 * Newer 3x-ui versions protect every POST (login included) with a CSRF token:
 * GET <panel>/csrf-token -> {"success":true,"obj":"<token>"}; the token must come
 * back in the X-CSRF-Token header together with the session cookie.
 * Older panels have no such route: this returns [null, $cookie] and nothing changes for them.
 */
function panelApiFetchCsrf($panel_url, $cookie, $timeout){
    $res = panelApiHttp('GET', rtrim($panel_url, '/') . '/csrf-token', null, $cookie, $timeout, $timeout, true);
    $json = is_string($res['body']) ? json_decode($res['body'], true) : null;
    if(is_array($json) && !empty($json['success']) && isset($json['obj']) && is_string($json['obj']) && $json['obj'] !== ''){
        return [$json['obj'], panelApiMergeCookies($cookie, $res['header'])];
    }
    return [null, $cookie];
}

/**
 * Logs in to an x-ui panel and returns
 * ['ok' => bool, 'cookie' => string|null, 'csrf' => string|null, 'response' => array|null]
 * 'response' is the decoded login reply (null when the panel is unreachable).
 * 'csrf' is only set for panels that require a CSRF token (3x-ui v3+).
 */
function panelApiLogin($panel_url, $username, $password, $timeout = PANEL_API_LOGIN_TIMEOUT){
    $base = rtrim($panel_url, '/');

    // API token mode: nothing to log in to
    if($username === PANEL_API_TOKEN_USER){
        return ['ok' => true, 'cookie' => null, 'csrf' => null, 'token' => $password, 'response' => null];
    }

    // 1) anonymous session + csrf token (needed by POST /login on new panels)
    list($csrf, $cookie) = panelApiFetchCsrf($base, null, $timeout);

    // 2) login
    $res = panelApiHttp(
        'POST',
        $base . '/login',
        http_build_query(['username' => $username, 'password' => $password]),
        $cookie,
        $timeout,
        $timeout,
        true,
        $csrf
    );

    $loginResponse = is_string($res['body']) ? json_decode($res['body'], true) : null;
    if(!is_array($loginResponse) || empty($loginResponse['success'])){
        return ['ok' => false, 'cookie' => null, 'csrf' => null, 'response' => is_array($loginResponse) ? $loginResponse : null];
    }

    // the panel starts a fresh session on login: take the new cookie
    $cookie = panelApiMergeCookies($cookie, $res['header']);
    if($cookie === '') return ['ok' => false, 'cookie' => null, 'csrf' => null, 'response' => $loginResponse];

    // 3) the old csrf token died with the anonymous session: ask for the one of the logged in session
    if($csrf !== null){
        list($csrf, $cookie) = panelApiFetchCsrf($base, $cookie, $timeout);
    }

    return ['ok' => true, 'cookie' => $cookie, 'csrf' => $csrf, 'response' => $loginResponse];
}

/**
 * Checks panel credentials before they are stored (used when an admin adds or
 * edits a server). Works for Marzban (token API) and the x-ui panels.
 * Returns an array that always has the key 'success'.
 */
function panelApiCheckLogin($panel_url, $username, $password, $type = null){
    if($type == "marzban"){
        $res = panelApiHttp(
            'POST',
            rtrim($panel_url, '/') . '/api/admin/token',
            http_build_query(['username' => $username, 'password' => $password]),
            null,
            PANEL_API_LOGIN_TIMEOUT,
            PANEL_API_LOGIN_TIMEOUT
        );
        if($res['error']) return ['success' => false, 'error' => $res['error']];
        $decoded = json_decode($res['body'], true);
        return ['success' => isset($decoded['access_token'])];
    }

    if($username === PANEL_API_TOKEN_USER){
        // verify the token with a cheap authenticated call
        $res = panelApiHttp('GET', rtrim($panel_url, '/') . '/panel/api/server/status', null, null, PANEL_API_TIMEOUT, PANEL_API_LOGIN_TIMEOUT, false, null, ['Authorization: Bearer ' . $password]);
        if($res['error']) return ['success' => false, 'error' => $res['error']];
        $decoded = json_decode((string)$res['body'], true);
        return ['success' => is_array($decoded) && !empty($decoded['success'])];
    }

    $login = panelApiLogin($panel_url, $username, $password, PANEL_API_TIMEOUT);
    return ['success' => $login['ok']];
}

/** Login once per request and reuse the session for every following call. */
function panelApiSession($server_info){
    static $sessions = [];
    $key = $server_info['id'];
    if(!isset($sessions[$key])){
        $sessions[$key] = panelApiLogin($server_info['panel_url'], $server_info['username'], $server_info['password']);
    }
    return $sessions[$key];
}

/**
 * Maps an operation to [HTTP method, path].
 * Returns null when the panel type does not support the operation.
 */
function panelApiRoute($type, $op, $p){
    $base = panelApiPrefix($type);
    $api = ($type == "sanaei" || $type == "alireza");
    $enc = function($v){ return rawurlencode((string)$v); };

    switch($op){
        case 'list':
            if($type == "sanaei") return ['GET', $base . '/list'];
            if($type == "alireza") return ['GET', $base . '/list'];
            return ['POST', $base . '/list'];
        case 'addInbound':
            return ['POST', $base . '/add'];
        case 'updateInbound':
            return ['POST', $base . '/update/' . $enc($p['id'])];
        case 'delInbound':
            return ['POST', $base . '/del/' . $enc($p['id'])];
        case 'addClient':
            if(!$api) return null;
            return ['POST', $base . ($type == "alireza" ? '/addClient/' : '/addClient')];
        case 'updateClient':
            if(!$api) return null;
            return ['POST', $base . '/updateClient/' . $enc($p['clientId'])];
        case 'delClient':
            if(!$api) return null;
            return ['POST', $base . '/' . $enc($p['id']) . '/delClient/' . $enc($p['clientId'])];
        case 'resetClientTraffic':
            if($type != "sanaei" && (!isset($p['id']) || $p['id'] === '')){
                return ['POST', $base . '/resetClientTraffic/' . $enc($p['email'])];
            }
            return ['POST', $base . '/' . $enc($p['id']) . '/resetClientTraffic/' . $enc($p['email'])];
        case 'clearClientIps':
            return ['POST', $base . '/clearClientIps/' . $enc($p['email'])];
        case 'newX25519Cert':
            return ['POST', '/server/getNewX25519Cert'];
        case 'clientLinks':
            if($type != "sanaei" || empty($p['email'])) return null;
            return ['GET', '/panel/api/clients/links/' . $enc($p['email'])];
        case 'defaultSettings':
            // 3x-ui only: returns subEnable / subURI (the panel's own subscription base url)
            if($type != "sanaei") return null;
            return ['POST', '/panel/api/setting/defaultSettings'];   // 3x-ui v3+
        case 'defaultSettingsLegacy':
            if($type != "sanaei") return null;
            return ['POST', '/panel/setting/defaultSettings'];       // older 3x-ui
    }
    return null;
}

/**
 * 3x-ui v3+ moved the per-client calls from /panel/api/inbounds/* to /panel/api/clients/*
 * (JSON body, clients addressed by email). This maps the calls the bot needs for
 * selling accounts (add / update / delete / reset traffic / clear ips). Returns [method, path, jsonBody|null] or null when there is no mapping.
 */
/** Email of the client that owns this uuid / trojan password (new panels address clients by email). */
function panelApiEmailByUuid($server_id, $uuid){
    $list = panelApi($server_id, 'list');
    if(!is_object($list) || empty($list->obj)) return null;
    foreach($list->obj as $row){
        $settings = json_decode($row->settings, true);
        foreach(($settings['clients'] ?? []) as $c){
            if((isset($c['id']) && $c['id'] == $uuid) || (isset($c['password']) && $c['password'] == $uuid)) return $c['email'] ?? null;
        }
    }
    return null;
}

function panelApiClientsRoute($op, $p, $data, $server_id = null){
    $enc = function($v){ return rawurlencode((string)$v); };
    switch($op){
        case 'updateClient':
            $settings = is_array($data) && isset($data['settings']) ? json_decode($data['settings'], true) : null;
            $client = $settings['clients'][0] ?? null;
            if(!$client || empty($p['clientId'])) return null;
            $email = !empty($p['email']) ? $p['email'] : panelApiEmailByUuid($server_id, $p['clientId']);
            if(!$email) return null;
            return ['POST', '/panel/api/clients/update/' . $enc($email), json_encode($client)];
        case 'delClient':
            if(empty($p['clientId'])) return null;
            $email = !empty($p['email']) ? $p['email'] : panelApiEmailByUuid($server_id, $p['clientId']);
            if(!$email) return null;
            return ['POST', '/panel/api/clients/del/' . $enc($email), null];
        case 'addClient':
            $settings = is_array($data) && isset($data['settings']) ? json_decode($data['settings'], true) : null;
            $client = $settings['clients'][0] ?? null;
            if(!$client || !isset($data['id'])) return null;
            return ['POST', '/panel/api/clients/add', json_encode(['client' => $client, 'inboundIds' => [intval($data['id'])]])];
        case 'resetClientTraffic':
            if(empty($p['email'])) return null;
            return ['POST', '/panel/api/clients/resetTraffic/' . $enc($p['email']), null];
        case 'clearClientIps':
            if(empty($p['email'])) return null;
            return ['POST', '/panel/api/clients/clearIps/' . $enc($p['email']), null];
    }
    return null;
}

/**
 * The one entry point for every bot -> panel call.
 *
 * $op     list | addInbound | updateInbound | delInbound | addClient |
 *         updateClient | delClient | resetClientTraffic | clearClientIps | newX25519Cert |
 *         defaultSettings | defaultSettingsLegacy
 * $params values used in the route (id, clientId, email)
 * $data   form fields sent to the panel
 *
 * Returns the decoded JSON reply (object), exactly like json_decode() did in
 * the old code. When login fails it returns null (panel unreachable) or
 * (object)['success' => false, ...] (credentials refused).
 */
function panelApi($server_id, $op, $params = [], $data = null){
    $server_info = panelApiServer($server_id);
    if(!$server_info) return null;

    $session = panelApiSession($server_info);
    if(!$session['ok']){
        return is_array($session['response']) ? (object) $session['response'] : null;
    }

    $type = $server_info['type'];
    $route = panelApiRoute($type, $op, $params);
    if(!$route) return (object) ['success' => false, 'msg' => "operation $op is not supported by panel type $type"];

    $panel_url = rtrim($server_info['panel_url'], '/');

    $auth = !empty($session['token']) ? ['Authorization: Bearer ' . $session['token']] : [];
    $isNewPanel = !empty($session['csrf']) || !empty($session['token']);

    // New 3x-ui (the one that asks for a csrf token, or any API token) only has the /panel/api/clients/* routes.
    // Try them first; when the panel answers with something that is not JSON, fall back to the old route below.
    if($type == "sanaei" && $isNewPanel){
        $new = panelApiClientsRoute($op, $params, $data, $server_id);
        if($new){
            $res = panelApiHttp($new[0], $panel_url . $new[1], $new[2], $session['cookie'], PANEL_API_TIMEOUT, 15, false, $session['csrf'] ?? null,
                array_merge($new[2] !== null ? ['Content-Type: application/json'] : [], $auth));
            $decoded = json_decode((string)$res['body']);
            if(is_object($decoded)) return $decoded;
        }
    }

    $res = panelApiHttp($route[0], $panel_url . $route[1], $data, $session['cookie'], PANEL_API_TIMEOUT, 15, false, $session['csrf'] ?? null, $auth);

    // Some alireza builds expose the inbound list on the root of the API base.
    if($op == 'list' && $type == "alireza" && !is_object(json_decode((string)$res['body']))){
        $res = panelApiHttp('GET', $panel_url . panelApiPrefix($type) . '/', null, $session['cookie'], PANEL_API_TIMEOUT, 15, false, $session['csrf'] ?? null, $auth);
    }

    $decoded = json_decode((string)$res['body']);
    if($op == 'list') panelApiNormalizeInbounds($decoded);
    return $decoded;
}

/**
 * New 3x-ui returns settings / streamSettings / sniffing of every inbound as nested JSON objects,
 * old panels (and all the bot code) expect JSON encoded strings. Convert them back so
 * json_decode($row->settings) keeps working everywhere.
 */
function panelApiNormalizeInbounds($decoded){
    if(!is_object($decoded) || !isset($decoded->obj) || !is_array($decoded->obj)) return;
    foreach($decoded->obj as $row){
        if(!is_object($row)) continue;
        foreach(['settings', 'streamSettings', 'sniffing'] as $field){
            if(isset($row->$field) && (is_object($row->$field) || is_array($row->$field))){
                $row->$field = json_encode($row->$field, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
    }
}
?>