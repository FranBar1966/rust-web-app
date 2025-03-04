![neutral](https://gitlab.com/neutralfw/neutralts/-/raw/master/top-neutralts.png)

Neutral TS Web APP Example
==========================

Simple example of Neutral TS to create a Progressive Web Application (PWA) or Web APP, in Rust with Actix Web and for HTML design Bootswatch and Bootstrap. This example will show you how to create a template structure and how to do theming among other things.

It is not an example of Rust or Actix, it is not an example of design, it is an example of Neutral TS, so you can use this example as a prototype or as the basis of your own Web APP taking into account that what is really intended to illustrate is the use of templates.

Download from [repository](https://gitlab.com/neutralfw/neutralts/), the crate is here [neutralts](https://crates.io/crates/neutralts)

### Contents of the directories

```
 web-app
    ├── neutral -----------------> Neutral TS files and templates
    │   ├── css -----------------> Statics CSS files
    │   ├── data ----------------> Neutral TS json data and locale files
    │   ├── img -----------------> Statics image files
    │   ├── js ------------------> Statics js files
    │   ├── plugins -------------> Neutral TS utilities
    │   ├── pwa -----------------> Statics files for PWA
    │   ├── service-worker.js ---> Service Worker for PWA
    │   └── tpl -----------------> Neutral TS templates
    ├── php ---------------------> PHP source
    ├── python ------------------> Python source
    └── rust --------------------> Rust source
```

The files and templates have comments explaining the use.

Rust
----

Navigate to the web-app/rust directory and then:

```
cargo run --release
```

A server will be available on port 9090

```
http://127.0.0.1:9090/
```

PHP
----

Navigate to the web-app/php directory and then:

```
php -S localhost:8000
```

A server will be available on port 8000

```
http://127.0.0.1:8000/
```
(*) Requires an IPC server that you can download from the [repository](https://gitlab.com/neutralfw/neutralts)

Python
----

Navigate to the web-app/python directory and then:

```
export FLASK_APP=index.py && flask run
```

A server will be available on port 5000

```
http://127.0.0.1:5000/
```
(*) Requires an IPC server that you can download from the [repository](https://gitlab.com/neutralfw/neutralts)

IPC Server
----------

Navigate to the ipc directory and then:

```
cargo run --release
```

Performance
-----------

The example has all the elements of a production PWA, so it is a good approximation of real performance with a **4000MHz** CPU.

### Rust (actix-web)

```
wrk http://localhost:9090/
Running 10s test @ http://localhost:9090/
  2 threads and 10 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency   324.25us   66.31us   4.06ms   80.77%
    Req/Sec    15.32k   622.19    16.65k    73.27%
  307975 requests in 10.10s, 9.23GB read
Requests/sec:  30492.77
Transfer/sec:      0.91GB
```

### PHP (apache mod_php)
```
wrk http://localhost.php/
Running 10s test @ http://localhost.php/
  2 threads and 10 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency   740.78us  118.57us   4.45ms   83.68%
    Req/Sec     6.73k   238.20     7.05k    87.13%
  135160 requests in 10.10s, 4.06GB read
Requests/sec:  13382.57
Transfer/sec:    411.76MB
```

### Python (apache mod_python)
```
wrk http://localhost.python/
Running 10s test @ http://localhost.python/
  2 threads and 10 connections
  Thread Stats   Avg      Stdev     Max   +/- Stdev
    Latency     0.88ms    1.15ms  57.11ms   99.78%
    Req/Sec     5.95k   300.26     7.93k    89.55%
  118875 requests in 10.10s, 3.57GB read
Requests/sec:  11769.88
Transfer/sec:    361.97MB
```
