from flask import Flask, request, jsonify, redirect, send_from_directory, make_response
import sys
import os
import json
from http.cookies import SimpleCookie

#
# Neutral IPC example Flask
#

app = Flask(__name__, static_folder='neutral')

base_dir = os.path.dirname(os.path.abspath(__file__))

TEMPLATE_ROUTER = base_dir + "/neutral/tpl/cache.ntpl"
TEMPLATE_ERROR = base_dir + "/neutral/tpl/cache_error.ntpl"
DEFAULT_SCHEMA = os.path.join(base_dir, "neutral/data/schema.json")
SIMULATE_SECRET_KEY = "69bdd1e4b4047d8f4e3"

# NeutralIpcTemplate directory location
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), './')))
from NeutralIpcTemplate import NeutralIpcTemplate

@app.route('/service-worker.js')
def service_worker():
    return send_from_directory(base_dir + '/neutral/', 'service-worker.js')

@app.route('/<path:route>', methods=['GET', 'POST'])
@app.route('/', defaults={'route': 'home'}, methods=['GET', 'POST'])
def handler(route):
    route = route.strip('/\\')
    schema = set_schema(request, route)
    result = render_template(TEMPLATE_ROUTER, schema)
    contents = result[0]
    status = result[1]
    status_param = result[2]

    # Redirects do not usually have a body, we simply set the header.
    if status in [301, 302, 307, 308]:
        response = make_response(redirect(status_param, code=status))
        return response

    response = make_response(contents, status)
    response.headers['Content-Type'] = 'text/html'
    return response

def set_schema(req, route):
    # A "schema" is needed for the configuration and for the data to be presented.
    with open(DEFAULT_SCHEMA, "r", encoding="utf-8") as file:
        schema_json = file.read()
    schema = json.loads(schema_json)

    # Populate context. Any value coming from the context should be considered unsafe.
    populate_context(req, schema)

    # Neutral determines the language with the value set in inherit.locale.current.
    schema["inherit"]["locale"]["current"] = (
        schema["data"]["CONTEXT"]["GET"].get("lang") or
        schema["data"]["CONTEXT"]["COOKIES"].get("lang") or
        "en"
    )

    schema["data"]["requested-with-ajax"] = request.headers.get("Requested-With-Ajax", None)

    # The theme is set. Any value coming from the context (env, cookies, ...)
    # should be considered unsafe.
    schema["data"]["site"]["theme"] = (
        schema["data"]["CONTEXT"]["GET"].get("theme") or
        schema["data"]["CONTEXT"]["COOKIES"].get("theme") or
        "flatly"
    )

    # Navbar color
    # $schema["data"]["site"]["navbar"] = $_GET['navbar'] ?? $_COOKIE['navbar'] ?? "primary";
    schema["data"]["site"]["navbar"] = (
        schema["data"]["CONTEXT"]["GET"].get("navbar") or
        schema["data"]["CONTEXT"]["COOKIES"].get("navbar") or
        "primary"
    )

    # In the templates we use the route variable to display the content according to the route.
    schema["data"]["CONTEXT"]["ROUTE"] = route

    # Check session. Since the session is stored in a cookie, it should be considered unsafe.
    session_id = simulate_check_session(schema)
    if session_id:
        schema["data"]["CONTEXT"]["SESSION"] = session_id

    # Login
    if route == "form-login":
        check_login(schema)

    # Simulate errors
    if schema["data"]["CONTEXT"].get("GET"):
        simulate_errors(schema)

    return schema

def render_template(file, schema):
    # Create the template
    template = NeutralIpcTemplate(file, schema)

    # Rendered content
    contents = template.render()

    # If “exit” or “redirect” is used, the status codes must be managed.
    status_code = int(template.get_status_code())
    status_text = template.get_status_text()
    status_param = template.get_status_param()

    if status_code >= 400:
        error = {
            "data": {
                "CONTEXT": {
                    "ROUTE": "error"
                },
                "error": {
                    "code": status_code,
                    "text": status_text,
                    "param": status_param
                }
            }
        }

        template.set_path(TEMPLATE_ERROR)

        # The error variables are added to the schema, just because this
        # is how we set up our custom error page.
        template.merge_schema(error)

        # Rendered content for error custom page.
        # Be careful not to re-render the content that causes the error,
        # for example if the error occurs in a snippet that shares the error page.
        contents = template.render()

    return [contents, status_code, status_param]

def check_login(schema):
    if schema["data"]["CONTEXT"].get("POST"):
        session = simulate_create_session(schema)
        schema["data"]["send_form_login"] = 1
        if session:
            schema["data"]["CONTEXT"]["SESSION"] = session
        else:
            schema["data"]["send_form_login_fails"] = 1

def simulate_create_session(schema):
    if schema["data"]["CONTEXT"].get("POST"):
        user = schema["data"]["CONTEXT"]["POST"].get("user", "")
        passwd = schema["data"]["CONTEXT"]["POST"].get("passwd", "")
        if user and passwd == "1234":
            return SIMULATE_SECRET_KEY
    return ""

def simulate_check_session(schema):
    if schema["data"]["CONTEXT"]["COOKIES"].get("SESSION", "") == SIMULATE_SECRET_KEY:
        return SIMULATE_SECRET_KEY
    return ""

def simulate_errors(schema):
    simulate = schema["data"]["CONTEXT"]["GET"].get("simulate", "")
    if simulate == "404":
        schema["data"]["CONTEXT"]["ROUTE"] = "simulate-404"
    elif simulate == "403":
        schema["data"]["CONTEXT"]["ROUTE"] = "simulate-403"
    elif simulate == "503":
        schema["data"]["CONTEXT"]["ROUTE"] = "simulate-503"
    elif simulate == "302":
        schema["data"]["CONTEXT"]["ROUTE"] = "simulate-302"

def populate_context(req, schema):
    schema["data"]["CONTEXT"] = {}
    context = schema["data"]["CONTEXT"]

    # GET parameters
    context["GET"] = {}
    for key, value in req.args.items():
        context["GET"][key] = value

    # POST data
    context["POST"] = {}
    if req.method == "POST":
        for key, value in req.form.items():
            context["POST"][key] = value

    # Cookies
    context["COOKIES"] = {}
    if req.headers.get('Cookie'):
        cookie = SimpleCookie(req.headers.get('Cookie'))
        for key, morsel in cookie.items():
            context["COOKIES"][key] = morsel.value

if __name__ == '__main__':
    app.run(debug=True)
