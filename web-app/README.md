![neutral](../doc/top-neutralts.png)

Neutral TS Web APP Example
==========================

Simple example of Neutral TS to create a Progressive Web Application (PWA) or Web APP, in Rust with Actix Web and for HTML design Bootswatch and Bootstrap. This example will show you how to create a template structure and how to do theming among other things.

It is not an example of Rust or Actix, it is not an example of design, it is an example of Neutral TS, so you can use this example as a prototype or as the basis of your own Web APP taking into account that what is really intended to illustrate is the use of templates.

Run
---

Download from [repository](https://gitlab.com/neutralfw/neutralts/), the crate is here [neutralts](https://crates.io/crates/neutralts)

Navigate to the web-app directory and then:

```
cargo run
```

A server will be available on port 9090

```
http://127.0.0.1:9090/
```

### Contents of the directories

```
 web-app ------------------------> cd web-app and `cargo run`
    ├── neutral -----------------> Neutral TS files and templates
    │   ├── css -----------------> Statics CSS files
    │   ├── data ----------------> Neutral TS json data and locale files
    │   ├── img -----------------> Statics image files
    │   ├── js ------------------> Statics js files
    │   ├── plugins -------------> Neutral TS utilities
    │   ├── pwa -----------------> Statics files for PWA
    │   ├── service-worker.js ---> Service Worker for PWA
    │   └── tpl -----------------> Neutral TS templates
    ├── README.md ---------------> You are here
    └── src ---------------------> Rust source
        └── main.rs -------------> Rust source
```

The files and templates have comments explaining the use.
