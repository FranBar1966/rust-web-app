use actix_files::Files;
use actix_web::{
    http::StatusCode, middleware::NormalizePath, web, App, HttpRequest, HttpResponse, HttpServer,
    Responder,
};
use neutralts::Template;
use serde_json::{json, Value};
use serde_urlencoded::from_str;
use std::fs;
use url::form_urlencoded;

const TEMPLATE_ROUTER: &str = "neutral/tpl/index.ntpl";
const DEFAULT_SCHEMA: &str = "neutral/data/schema.json";
const SIMULATE_SECRET_KEY: &str = "69bdd1e4b4047d8f4e3";

// Create schema with common data
fn set_schema(req: actix_web::HttpRequest, route: &str) -> Value {
    // A "schema" is needed for the configuration and for the data to be presented.
    let schema_str = &fs::read_to_string(DEFAULT_SCHEMA).expect("schema is required");
    let mut schema: Value = serde_json::from_str(schema_str).unwrap();

    // Neutral determines the language with the value set in inherit.locale.current.
    let accepted = join_array_elements(&schema["data"]["site"]["languages"]);
    schema["inherit"]["locale"]["current"] = json!(negociate_language(&req, &accepted, "en"));

    // The theme is set. Any value coming from the context (env, cookies, ...)
    // should be considered unsafe.
    schema["data"]["site"]["theme"] = get_theme(&req).into();

    // Navbar color
    schema["data"]["site"]["navbar"] = get_navbar(&req).into();

    // In the templates we use the route variable to display the content according to the route.
    schema["data"]["route"] = route.into();

    // Check session. Since the session is stored in a cookie, it should be considered unsafe.
    let session_id = simulate_check_session(&req);
    if !session_id.is_empty() {
        schema["data"]["CONTEXT"]["SESSION"] = session_id.into();
    }

    // Any value coming from the context should be considered unsafe.
    schema["data"]["CONTEXT"]["GET"] = from_str(req.query_string())
        .map(|params: serde_json::Map<String, Value>| Value::Object(params))
        .unwrap_or(Value::Null);

    // Any value coming from the context should be considered unsafe.
    schema["data"]["CONTEXT"]["COOKIES"] = req
        .cookies()
        .map(|cookies| {
            let mut map = serde_json::Map::new();
            for cookie in cookies.iter() {
                map.insert(
                    cookie.name().to_string(),
                    Value::String(cookie.value().to_string()),
                );
            }
            Value::Object(map)
        })
        .unwrap_or(Value::Null);

    // Simulate errors and redirect
    if let Some(simulate) = get_key_from_query("simulate", &req) {
        match simulate.as_str() {
            "404" => schema["data"]["route"] = "simulate-404".into(),
            "403" => schema["data"]["route"] = "simulate-403".into(),
            "503" => schema["data"]["route"] = "simulate-503".into(),
            "302" => schema["data"]["route"] = "simulate-302".into(),
            _ => {}
        }
    }

    schema
}

// Render template, for any route we will at least need to do this.
fn render_template(file: &str, schema: Value) -> impl Responder {
    // Create the template
    let mut template = Template::from_file_value(file, schema).unwrap();

    // Rendered content
    let mut contents = template.render();

    // If “exit” or “redirect” is used, the status codes must be managed.
    let status_code = template.get_status_code().clone();
    let status_text = template.get_status_text().clone();

    // Only in certain cases, e.g., redirect.
    let status_param = template.get_status_param().clone();

    // status_code is a string, HttpResponse::build need StatusCode
    let status: StatusCode = status_code
        .parse::<u16>()
        .map_err(|_| StatusCode::BAD_REQUEST)
        .and_then(|code| StatusCode::from_u16(code).map_err(|_| StatusCode::BAD_REQUEST))
        .unwrap_or(StatusCode::INTERNAL_SERVER_ERROR);

    // Redirects do not usually have a body, we simply set the header.
    if status == 301 || status == 302 || status == 307 || status == 308 {
        return HttpResponse::build(status)
            .append_header(("Location", status_param))
            .finish();
    }

    // If not changed (with "{:exit;:}" for example) the template always returns 200 OK.
    if status_code.as_str() >= "400" {
        let error = json!({
            "data": {
                "route": "error",
                "error": {
                    "code": status_code,
                    "text": status_text,
                    "param": status_param,
                }
            }
        });

        // Again with error page
        template.set_src_path(TEMPLATE_ROUTER).unwrap();

        // The error variables are added to the schema, just because this
        // is how we set up our custom error page.
        template.merge_schema_value(error);

        // Rendered content for error custom page.
        // Be careful not to re-render the content that causes the error,
        // for example if the error occurs in a snippet that shares the error page.
        contents = template.render();
    }

    // Return the headers with the status code and the contents.
    HttpResponse::build(status)
        .content_type("text/html")
        .body(contents)
}

// Home
async fn home(req: actix_web::HttpRequest, data: Option<web::Form<Value>>) -> impl Responder {
    // Create schema with common data
    let mut schema = set_schema(req, "home");

    // Here we could call a function for the data of this particular route.
    // As an example we will define a variable, but it could be data from a DB.
    schema["data"]["example"] = "Example data".into();

    // Get POST data if POST
    if let Some(post_data) = data {
        schema["data"]["CONTEXT"]["POST"] = post_data.into_inner();
    }

    // Render template for this route
    let response = render_template(TEMPLATE_ROUTER, schema);

    response
}

// login
async fn login(req: actix_web::HttpRequest, data: Option<web::Form<Value>>) -> impl Responder {
    // Create schema with common data
    let mut schema = set_schema(req, "login");

    // Get POST data if POST
    if let Some(post_data) = data {
        schema["data"]["CONTEXT"]["POST"] = post_data.into_inner();
        if let Some(id) = simulate_create_session(
            schema["data"]["CONTEXT"]["POST"]["user"]
                .as_str()
                .unwrap_or_else(|| ""),
            schema["data"]["CONTEXT"]["POST"]["passwd"]
                .as_str()
                .unwrap_or_else(|| ""),
        ) {
            schema["data"]["CONTEXT"]["SESSION"] = id.into();
        } else {
            schema["data"]["send_form_login_fails"] = true.into();
        }
        schema["data"]["send_form_login"] = true.into();
    }

    // Render template for this route
    let response = render_template(TEMPLATE_ROUTER, schema);

    response
}

// logout
async fn logout(req: actix_web::HttpRequest) -> impl Responder {
    // Create schema with common data
    let schema = set_schema(req, "logout");

    // Render template for this route
    let response = render_template(TEMPLATE_ROUTER, schema);

    response
}

// help
async fn help(req: actix_web::HttpRequest) -> impl Responder {
    // Create schema with common data
    let schema = set_schema(req, "help");

    // Render template for this route
    let response = render_template(TEMPLATE_ROUTER, schema);

    response
}

// 404
async fn not_found(req: actix_web::HttpRequest) -> impl Responder {
    // Create schema with common data
    let mut schema = set_schema(req, "error");

    // Contents for error
    schema["data"]["route"] = "error".into();

    // The error are added to the schema, just because this is how we set up our custom error page.
    schema["data"]["error"] = json!({
        "code": "404",
        "text": "Not Found",
        "param": "",
    });

    let mut template = Template::from_file_value(TEMPLATE_ROUTER, schema).unwrap();
    let contents = template.render();

    HttpResponse::NotFound().body(contents)
}

#[actix_web::main]
async fn main() -> std::io::Result<()> {
    println!("Example on http://127.0.0.1:9090/");
    HttpServer::new(|| {
        App::new()
            .wrap(NormalizePath::new(
                actix_web::middleware::TrailingSlash::Trim,
            ))
            .route("/", web::get().to(home))
            .route("/", web::post().to(home))
            .route("/help", web::get().to(help))
            .route("/logout", web::get().to(logout))
            .route("/login", web::get().to(login))
            .route("/login", web::post().to(login))
            .service(
                Files::new("/neutral/img", "./neutral/img")
                    .index_file("index.html")
                    .use_last_modified(true),
            )
            .service(
                Files::new("/neutral/css", "./neutral/css")
                    .index_file("index.html")
                    .use_last_modified(true),
            )
            .service(
                Files::new("/neutral/js", "./neutral/js")
                    .index_file("index.html")
                    .use_last_modified(true),
            )
            .service(
                Files::new("/neutral/pwa", "./neutral/pwa")
                    .index_file("index.html")
                    .use_last_modified(true),
            )
            .default_service(web::route().to(not_found))
    })
    .bind(("127.0.0.1", 9090))?
    .run()
    .await
}

// -----------------
// Utility functions
// -----------------

// create session SIMULATED
fn simulate_create_session(user: &str, passwd: &str) -> Option<String> {
    // It goes without saying that this is an example and that something
    // like this does not have to be done in production.
    if user.len() > 0 && passwd == "1234" {
        return Some(SIMULATE_SECRET_KEY.to_string());
    }

    None
}

// check session SIMULATED
fn simulate_check_session(req: &HttpRequest) -> String {
    if let Some(cookie) = req.cookie("SESSION") {
        let session_id = cookie.value().to_string();
        if session_id == SIMULATE_SECRET_KEY {
            return SIMULATE_SECRET_KEY.to_string();
        }
    }

    "".to_string()
}

fn negociate_language(
    req: &HttpRequest,
    accepted_languages_str: &str,
    default_language: &str,
) -> String {
    let accept_language = req
        .headers()
        .get("Accept-Language")
        .map(|v| v.to_str().unwrap())
        .unwrap_or("");
    let accepted_languages: Vec<String> = accepted_languages_str
        .split_whitespace()
        .map(String::from)
        .collect();

    if let Some(lang_from_query) = get_key_from_query("lang", req) {
        return lang_from_query;
    }

    if let Some(cookie) = req.cookie("lang") {
        return cookie.value().to_string();
    }

    if !accept_language.is_empty() {
        let mut languages: Vec<_> = accept_language
            .split(',')
            .map(|lang| {
                let parts: Vec<&str> = lang.trim().split(';').collect();
                let primary_lang = parts[0];
                let quality = parts
                    .get(1)
                    .and_then(|q| q.strip_prefix("q="))
                    .and_then(|v| v.parse::<f32>().ok())
                    .unwrap_or(1.0);
                (primary_lang, quality)
            })
            .collect();
        languages.sort_by(|a, b| b.1.partial_cmp(&a.1).unwrap());
        for &(lang, _) in &languages {
            if let Some((primary_lang, _)) = lang.split_once('-') {
                if accepted_languages.contains(&primary_lang.to_string()) {
                    return primary_lang.to_string();
                }
            } else if accepted_languages.contains(&lang.to_string()) {
                return lang.to_string();
            }
        }
    }

    default_language.to_string()
}

fn get_key_from_query(key: &str, req: &HttpRequest) -> Option<String> {
    let query_string = req.query_string();
    for (k, v) in form_urlencoded::parse(query_string.as_bytes()) {
        if k == key {
            return Some(v.to_string());
        }
    }

    None
}

fn get_key_from_cookies(key: &str, req: &HttpRequest) -> Option<String> {
    if let Some(cookie) = req.cookie(key) {
        return Some(cookie.value().to_string());
    }

    None
}

fn get_theme(req: &HttpRequest) -> String {
    if let Some(theme_from_query) = get_key_from_query("theme", req) {
        return theme_from_query;
    }
    if let Some(theme_from_cookies) = get_key_from_cookies("theme", req) {
        return theme_from_cookies;
    }

    "flatly".to_string()
}

fn get_navbar(req: &HttpRequest) -> String {
    if let Some(theme_from_query) = get_key_from_query("navbar", req) {
        return theme_from_query;
    }
    if let Some(theme_from_cookies) = get_key_from_cookies("navbar", req) {
        return theme_from_cookies;
    }

    "primary".to_string()
}

fn join_array_elements(value: &Value) -> String {
    match value {
        Value::Array(arr) => arr
            .iter()
            .filter_map(|v| v.as_str())
            .collect::<Vec<&str>>()
            .join(" "),
        Value::Object(obj) => obj
            .values()
            .flat_map(|v| join_array_elements(v).into_bytes())
            .map(char::from)
            .collect(),
        _ => "".to_string(),
    }
}
