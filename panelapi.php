<?php
/**
 * panelapi.php
 *
 * Single API layer between the bot and the panels. Every call from the bot to
 * a panel goes through panelApi().
 *
 * Panel types (server_config.type):
 *   sanaei  -> 3x-ui v3 (tested against the 3.11.0 source).
 *              Auth: API token sent as "Authorization: Bearer <token>".
 *              The token is stored in server_config.password; username is ignored
 *              (put anything, e.g. "token"). Bearer requests skip CSRF.
 *              Inbounds: /panel/api/inbounds/*   Clients: /panel/api/clients/* (by email, JSON)
 *   alireza -> alireza x-ui  (/xui/API/inbounds, cookie login)
 *   normal  -> vaxilu / niduka x-ui (/xui/inbound, cookie login, no public API)
 *   marzban -> REST + Bearer token (see the *Marzban* functions)
 */

if(!defined('PANEL_API_LOGIN_TIMEOUT')) define('PANEL_API_LOGIN_TIMEOUT', 3);
if(!defined('PANEL_API_TIMEOUT')) define('PANEL_API_TIMEOUT', 15);

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
 * $fields: array (multipart), string (raw body, e.g. JSON) or null.
 * $extraHeaders: list of 'Name: value' strings (Cookie, Authorization, Content-Type).
 */
function panelApiHttp($method, $url, $fields = null, $extraHeaders = [], $timeout = PANEL_API_TIMEOUT, $connectTimeout = 15, $needHeader = false){
    $curl = curl_init();
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:108.0) Gecko/20100101 Firefox/108.0',
        'Accept: application/json, text/plain, */*',
        'Accept-Language: en-US,en;q=0.5',
        'X-Requested-With: XMLHttpRequest',
    ];
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
 * Logs in to an x-ui panel and returns
 * ['ok' => bool, 'cookie' => string|null, 'response' => array|null]
 * 'response' is the decoded login reply (null when the panel is unreachable).
 */
function panelApiLogin($panel_url, $username, $password, $timeout = PANEL_API_LOGIN_TIMEOUT){
    $res = panelApiHttp(
        'POST',
        rtrim($panel_url, '/') . '/login',
        http_build_query(['username' => $username, 'password' => $password]),
        [],
        $timeout,
        $timeout,
        true
    );

    $loginResponse = is_string($res['body']) ? json_decode($res['body'], true) : null;
    if(!is_array($loginResponse) || empty($loginResponse['success'])){
        return ['ok' => false, 'cookie' => null, 'response' => is_array($loginResponse) ? $loginResponse : null];
    }

    preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $res['header'], $matches);
    $cookies = [];
    foreach($matches[1] as $item){
        $cookies[] = trim($item);
    }
    if(!$cookies) return ['ok' => false, 'cookie' => null, 'response' => $loginResponse];

    return ['ok' => true, 'cookie' => implode('; ', $cookies), 'response' => $loginResponse];
}

/**
 * 3x-ui v3 token check: the token is sent as a Bearer header, no login and no CSRF.
 * Returns true when the panel accepts the token on the inbound list.
 */
function panelApiBearerOk($panel_url, $token){
    $res = panelApiHttp(
        'GET',
        rtrim($panel_url, '/') . '/panel/api/inbounds/list',
        null,
        ['Authorization: Bearer ' . $token],
        PANEL_API_TIMEOUT,
        PANEL_API_LOGIN_TIMEOUT
    );
    $decoded = is_string($res['body']) ? json_decode($res['body'], true) : null;
    return is_array($decoded) && !empty($decoded['success']);
}

/**
 * Checks panel credentials before they are stored (used when an admin adds or
 * edits a server). For 3x-ui v3 the "password" is the API token.
 * $type null = unknown yet: try the token first, then the legacy cookie login.
 * Returns an array that always has the key 'success'.
 */
function panelApiCheckLogin($panel_url, $username, $password, $type = null){
    if($type == "marzban"){
        $res = panelApiHttp(
            'POST',
            rtrim($panel_url, '/') . '/api/admin/token',
            http_build_query(['username' => $username, 'password' => $password]),
            [],
            PANEL_API_LOGIN_TIMEOUT,
            PANEL_API_LOGIN_TIMEOUT
        );
        if($res['error']) return ['success' => false, 'error' => $res['error']];
        $decoded = json_decode($res['body'], true);
        return ['success' => isset($decoded['access_token'])];
    }

    if($type == null || $type == "sanaei"){
        if(panelApiBearerOk($panel_url, $password)) return ['success' => true];
        if($type == "sanaei") return ['success' => false];
    }

    $login = panelApiLogin($panel_url, $username, $password, PANEL_API_TIMEOUT);
    return ['success' => $login['ok']];
}

/**
 * Auth once per request. Returns ['ok', 'headers' => [...], 'response' => array|null].
 * sanaei: Bearer token (no network call). Others: cookie login.
 */
function panelApiSession($server_info){
    static $sessions = [];
    $key = $server_info['id'];
    if(isset($sessions[$key])) return $sessions[$key];

    if($server_info['type'] == "sanaei"){
        $sessions[$key] = ['ok' => true, 'headers' => ['Authorization: Bearer ' . $server_info['password']], 'response' => null];
    }else{
        $login = panelApiLogin($server_info['panel_url'], $server_info['username'], $server_info['password']);
        $sessions[$key] = [
            'ok' => $login['ok'],
            'headers' => $login['ok'] ? ['Cookie: ' . $login['cookie']] : [],
            'response' => $login['response'],
        ];
    }
    return $sessions[$key];
}

/** Casts the numeric/boolean client fields so Go's strict JSON decoder accepts them. */
function panelApiNormalizeClient($client){
    foreach(['totalGB', 'expiryTime', 'limitIp', 'tgId', 'reset'] as $k){
        if(isset($client[$k])) $client[$k] = (int) $client[$k];
    }
    if(isset($client['enable'])) $client['enable'] = filter_var($client['enable'], FILTER_VALIDATE_BOOLEAN);
    return $client;
}

/** First client of a form field "settings" ({"clients":[{...}]}) as an array. */
function panelApiClientFromSettings($data){
    $settings = isset($data['settings']) ? json_decode($data['settings'], true) : null;
    return isset($settings['clients'][0]) ? panelApiNormalizeClient($settings['clients'][0]) : null;
}

/** 3x-ui v3 addresses clients by email: find it from the client uuid/password. */
function panelApiResolveEmail($server_id, $inboundId, $clientId){
    $list = panelApi($server_id, 'list');
    if(!is_object($list) || !isset($list->obj) || !is_array($list->obj)) return null;
    foreach($list->obj as $row){
        if($inboundId !== null && $inboundId !== '' && $row->id != $inboundId) continue;
        $settings = json_decode($row->settings, true);
        if(!isset($settings['clients'])) continue;
        foreach($settings['clients'] as $client){
            if((isset($client['id']) && $client['id'] == $clientId) || (isset($client['password']) && $client['password'] == $clientId)){
                return $client['email'];
            }
        }
    }
    return null;
}

/**
 * Maps an operation to [HTTP method, path, body, extra headers].
 * Returns null when the panel type does not support the operation.
 */
function panelApiRoute($type, $op, $p, $data, $server_id = null){
    $enc = function($v){ return rawurlencode((string)$v); };
    $json = ['Content-Type: application/json'];

    if($type == "sanaei"){
        $base = '/panel/api/inbounds';
        $clients = '/panel/api/clients';
        switch($op){
            case 'list':          return ['GET', $base . '/list', null, []];
            case 'addInbound':    return ['POST', $base . '/add', $data, []];
            case 'updateInbound': return ['POST', $base . '/update/' . $enc($p['id']), $data, []];
            case 'delInbound':    return ['POST', $base . '/del/' . $enc($p['id']), null, []];
            case 'addClient':
                $client = panelApiClientFromSettings($data);
                if(!$client) return null;
                return ['POST', $clients . '/add', json_encode(['client' => $client, 'inboundIds' => [(int)$data['id']]]), $json];
            case 'updateClient':
                $client = panelApiClientFromSettings($data);
                if(!$client) return null;
                // An empty flow would wipe the per-inbound flow; omitted keys are kept by the panel.
                if(isset($client['flow']) && $client['flow'] === '') unset($client['flow']);
                $email = !empty($p['email']) ? $p['email'] : panelApiResolveEmail($server_id, isset($data['id']) ? $data['id'] : null, $p['clientId']);
                if(!$email) return null;
                return ['POST', $clients . '/update/' . $enc($email), json_encode($client), $json];
            case 'delClient':
                $email = !empty($p['email']) ? $p['email'] : panelApiResolveEmail($server_id, isset($p['id']) ? $p['id'] : null, $p['clientId']);
                if(!$email) return null;
                return ['POST', $clients . '/del/' . $enc($email), null, []];
            case 'resetClientTraffic': return ['POST', $clients . '/resetTraffic/' . $enc($p['email']), null, []];
            case 'clearClientIps':     return ['POST', $clients . '/clearIps/' . $enc($p['email']), null, []];
            case 'newX25519Cert':      return ['GET', '/panel/api/server/getNewX25519Cert', null, []];
            case 'defaultSettings':    return ['POST', '/panel/api/setting/defaultSettings', null, []];
        }
        return null;
    }

    $base = ($type == "alireza") ? "/xui/API/inbounds" : "/xui/inbound";
    $api = ($type == "alireza");
    switch($op){
        case 'list':
            return [$api ? 'GET' : 'POST', $base . '/list', null, []];
        case 'addInbound':
            return ['POST', $base . '/add', $data, []];
        case 'updateInbound':
            return ['POST', $base . '/update/' . $enc($p['id']), $data, []];
        case 'delInbound':
            return ['POST', $base . '/del/' . $enc($p['id']), null, []];
        case 'addClient':
            if(!$api) return null;
            return ['POST', $base . '/addClient/', $data, []];
        case 'updateClient':
            if(!$api) return null;
            return ['POST', $base . '/updateClient/' . $enc($p['clientId']), $data, []];
        case 'delClient':
            if(!$api) return null;
            return ['POST', $base . '/' . $enc($p['id']) . '/delClient/' . $enc($p['clientId']), null, []];
        case 'resetClientTraffic':
            if(!$api && (!isset($p['id']) || $p['id'] === '')) return ['POST', $base . '/resetClientTraffic/' . $enc($p['email']), null, []];
            return ['POST', $base . '/' . $enc($p['id']) . '/resetClientTraffic/' . $enc($p['email']), null, []];
        case 'clearClientIps':
            return ['POST', $base . '/clearClientIps/' . $enc($p['email']), null, []];
        case 'newX25519Cert':
            return ['POST', '/server/getNewX25519Cert', null, []];
    }
    return null;
}

/**
 * The one entry point for every bot -> panel call.
 *
 * $op     list | addInbound | updateInbound | delInbound | addClient |
 *         updateClient | delClient | resetClientTraffic | clearClientIps | newX25519Cert
 * $params values used in the route (id, clientId, email)
 * $data   form fields sent to the panel (client calls carry {"settings": json, "id": inboundId})
 *
 * Returns the decoded JSON reply (object), like json_decode() did in the old
 * code. When auth fails it returns null (panel unreachable) or
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

    // 3x-ui v3 silently re-attaches an existing client when email and subId match,
    // which would hand a new buyer somebody else's account. Refuse duplicates here.
    if($type == "sanaei" && ($op == 'addClient' || $op == 'addInbound')){
        $newClients = [];
        if($op == 'addClient'){
            $c = panelApiClientFromSettings($data);
            if($c) $newClients[] = $c;
        }else{
            $st = isset($data['settings']) ? json_decode($data['settings'], true) : null;
            if(isset($st['clients']) && is_array($st['clients'])) $newClients = $st['clients'];
        }
        $dup = $newClients ? panelApiFindDuplicate($server_id, $newClients) : null;
        if($dup !== null) return (object) ['success' => false, 'msg' => "Duplicate email: " . $dup];
    }

    $route = panelApiRoute($type, $op, $params, $data, $server_id);
    if(!$route) return (object) ['success' => false, 'msg' => "operation $op is not supported or its data is incomplete (panel type $type)"];

    $panel_url = rtrim($server_info['panel_url'], '/');
    $headers = array_merge($session['headers'], $route[3]);
    $res = panelApiHttp($route[0], $panel_url . $route[1], $route[2], $headers);

    // Some alireza builds expose the inbound list on the root of the API base.
    if($op == 'list' && $type == "alireza" && !is_object(json_decode((string)$res['body']))){
        $res = panelApiHttp('GET', $panel_url . '/xui/API/inbounds/', null, $session['headers']);
    }

    return json_decode((string)$res['body']);
}

/** 3x-ui v3 rejects whitespace, "/", "\" and control characters in email and subId. */
function panelApiSafeName($name){
    return preg_replace('/[\s\/\\\\\x00-\x1f\x7f]+/u', '-', (string) $name);
}

/** Returns the first email/subId of $clients that already exists on the panel, else null. */
function panelApiFindDuplicate($server_id, $clients){
    $list = panelApi($server_id, 'list');
    if(!is_object($list) || !isset($list->obj) || !is_array($list->obj)) return null;

    $emails = [];
    $subs = [];
    foreach($list->obj as $row){
        $settings = json_decode($row->settings, true);
        if(!isset($settings['clients']) || !is_array($settings['clients'])) continue;
        foreach($settings['clients'] as $c){
            if(!empty($c['email'])) $emails[strtolower($c['email'])] = true;
            if(!empty($c['subId'])) $subs[$c['subId']] = true;
        }
    }
    foreach($clients as $c){
        if(!empty($c['email']) && isset($emails[strtolower($c['email'])])) return $c['email'];
        if(!empty($c['subId']) && isset($subs[$c['subId']])) return $c['subId'];
    }
    return null;
}

/** Subscription base URL configured in the panel (ends with the sub path), "" when subs are disabled. */
function panelApiSubBase($server_id){
    static $cache = [];
    if(isset($cache[$server_id])) return $cache[$server_id];

    $uri = '';
    $res = panelApi($server_id, 'defaultSettings');
    if(is_object($res) && !empty($res->success) && isset($res->obj) && is_object($res->obj)){
        if(!empty($res->obj->subEnable) && !empty($res->obj->subURI)) $uri = (string) $res->obj->subURI;
    }
    return $cache[$server_id] = $uri;
}

/** The user's own subscription link, built by the panel's settings: <subURI><subId>. */
function getPanelSubLink($server_id, $subId){
    $base = panelApiSubBase($server_id);
    if($base === '' || $subId === '' || $subId === null) return '';
    return rtrim($base, '/') . '/' . rawurlencode((string) $subId);
}
?>
