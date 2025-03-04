<?php

/*
    Neutral Web APP example without framework
*/

include 'NeutralIpcTemplate.php';
include 'utility.php';

define("TEMPLATE_ROUTER", realpath("neutral/tpl/cache.ntpl"));
define("TEMPLATE_ERROR", realpath("neutral/tpl/cache_error.ntpl"));
define("DEFAULT_SCHEMA", realpath("neutral/data/schema.json"));
define("SIMULATE_SECRET_KEY", "69bdd1e4b4047d8f4e3");

function set_schema(string $route): array {
    // A "schema" is needed for the configuration and for the data to be presented.
    $schema_json = file_get_contents(DEFAULT_SCHEMA);
    $schema = json_decode($schema_json, true);

    // Neutral determines the language with the value set in inherit.locale.current.
    $accepted = $schema["data"]["site"]["languages"];
    $schema["inherit"]["locale"]["current"] = negotiate_language($accepted);

    // The theme is set. Any value coming from the context (env, cookies, ...)
    // should be considered unsafe.
    $schema["data"]["site"]["theme"] = $_GET['theme'] ?? $_COOKIE['theme'] ?? "flatly";

    // Navbar color
    $schema["data"]["site"]["navbar"] = $_GET['navbar'] ?? $_COOKIE['navbar'] ?? "primary";

    // In the templates we use the route variable to display the content according to the route.
    $schema["data"]["CONTEXT"]["ROUTE"] = $route;

    // Any value coming from the context should be considered unsafe.
    $schema["data"]["CONTEXT"]["GET"] = $_GET;
    $schema["data"]["CONTEXT"]["POST"] = $_POST;
    $schema["data"]["CONTEXT"]["COOKIES"] = $_COOKIE;
    $schema["data"]["CONTEXT"]["HEADERS"] = $_SERVER;
    $schema["data"]["requested-with-ajax"] = $_SERVER["HTTP_REQUESTED_WITH_AJAX"] ?? null;

    // Check session. Since the session is stored in a cookie, it should be considered unsafe.
    $session_id = simulate_check_session();
    if (!empty($session_id)) {
        $schema["data"]["CONTEXT"]["SESSION"] = $session_id;
    }

    // login
    if ($route == "form-login" && !empty($_POST)) {
        check_login($schema);
    }

    // Simulate errors
    if (!empty($_GET)) {
        simulate_errors($schema);
    }

    return $schema;
}

function render_template(string $file, array $schema): string {
    // Create the template
    $template = new NeutralIpcTemplate($file, $schema);

    // Rendered content
    $contents = $template->render();

    // If “exit” or “redirect” is used, the status codes must be managed.
    $status_code = $template->get_status_code();
    $status_text = $template->get_status_text();

    // Only in certain cases, e.g., redirect.
    $status_param = $template->get_status_param();

    // header protocol
    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? "HTTP/1.0";

    // Redirects do not usually have a body, we simply set the header.
    if ($status_code == 301 || $status_code == 302 || $status_code == 307 || $status_code == 308) {
        header("$protocol $status_code $status_text");
        header('Location: '. $status_param);

        return "";
    }

    if ($status_code >= 400) {
        $error = [
            "data" => [
                "CONTEXT" => [
                    "ROUTE" => "error"
                ],
                "error" => [
                    "code" => $status_code,
                    "text" => $status_text,
                    "param" => $status_param
                ]
            ]
        ];

        $template->set_path(TEMPLATE_ERROR);

        // The error variables are added to the schema, just because this
        // is how we set up our custom error page.
        $template->merge_schema($error);

        // Rendered content for error custom page.
        // Be careful not to re-render the content that causes the error,
        // for example if the error occurs in a snippet that shares the error page.
        $contents = $template->render();
    }

    header("$protocol $status_code $status_text");

    return $contents;
}

// Get route
$route = $_SERVER["REQUEST_URI"] ?? "home";
$route = preg_replace('/\?.*/', '', $route);
$route = trim($route, '/\\') ?: "home";

// schema
$schema = set_schema($route);

// contents
$contents = render_template(TEMPLATE_ROUTER, $schema);

echo $contents;
