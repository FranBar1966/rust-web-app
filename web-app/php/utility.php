<?php


// simulate erros
function simulate_errors(array &$schema): void {
    $simulate = $_GET['simulate'] ?? "";

    // Simulate errors and redirect
    switch ($simulate) {
        case 404:
            $schema["data"]["CONTEXT"]["ROUTE"] = "simulate-404";
            break;
        case 403:
            $schema["data"]["CONTEXT"]["ROUTE"] = "simulate-403";
            break;
        case 503:
            $schema["data"]["CONTEXT"]["ROUTE"] = "simulate-503";
            break;
        case 302:
            $schema["data"]["CONTEXT"]["ROUTE"] = "simulate-302";
            break;
    }
}

// check login
function check_login(array &$schema): void {
    $session = simulate_create_session($_POST['user'] ?? "", $_POST['passwd'] ?? "");
    $schema["data"]["send_form_login"] = true;

    if (!empty($session)) {
        $schema["data"]["CONTEXT"]["SESSION"] = $session;
    } else {
        $schema["data"]["CONTEXT"]["SESSION"] = null;
        $schema["data"]["send_form_login_fails"] = true;
    }
}

// create session SIMULATED
function simulate_create_session($user, $passwd) {
    // It goes without saying that this is an example and that something
    // like this does not have to be done in production.
    if (strlen($user) > 0 && $passwd == "1234") {
        return SIMULATE_SECRET_KEY;
    }

    return "";
}

// check session SIMULATED
function simulate_check_session() {
    $session_id = $_COOKIE['SESSION'] ?? "";
    if ($session_id == SIMULATE_SECRET_KEY) {
        return SIMULATE_SECRET_KEY;
    }

    return "";
}

function negotiate_language($available_languages = ['en']) {
    $accept_language = getenv('HTTP_ACCEPT_LANGUAGE');

    if (isset($_GET['lang']) && in_array($_GET['lang'], $available_languages)) {
        return $_GET['lang'];
    }

    if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], $available_languages)) {
        return $_COOKIE['lang'];
    }

    if (empty($accept_language)) {
        return reset($available_languages);
    }

    preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(0(\.\d{1,3})?|1(\.0{1,3})?))?\s*/i', $accept_language, $matches);

    $languages = [];
    foreach ($matches[1] as $index => $language) {
        $quality = isset($matches[4][$index]) ? floatval($matches[4][$index]) : 1.0;
        if (!isset($languages[$language])) {
            $languages[$language] = $quality;
        } else {
            $languages[$language] = max($languages[$language], $quality);
        }
    }

    uksort($languages, function ($lang1, $lang2) use ($languages) {
        return $languages[$lang2] <=> $languages[$lang1];
    });

    $preferred_languages = array_intersect_key($languages, array_flip($available_languages));

    reset($preferred_languages);
    return key($preferred_languages);
}
