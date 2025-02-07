Neutral build form
==================

Allows you to create forms from a JSON

JSON Example
------------

```
{
    "data": {
        "form-example-login": {
            "form": {
                "attributes": {
                    "id": "login",
                    "name": "login",
                    "method": "POST",
                    "action": ""
                }
            },
            "fields": {
                "user": [
                    {
                        "element": "input",
                        "value": "",
                        "wrap": {
                            "attributes": {
                                "class": "my-3"
                            }
                        },
                        "attributes": {
                            "type": "text",
                            "name": "user",
                            "class": "w-100",
                            "placeholder": "User or email"
                        }
                    }
                ],
                "passwd": [
                    {
                        "element": "input",
                        "value": "",
                        "wrap": {
                            "attributes": {
                                "class": "my-3"
                            }
                        },
                        "attributes": {
                            "type": "text",
                            "name": "passwd",
                            "class": "w-100",
                            "placeholder": "Password"
                        }
                    }
                ],
                "example-checkbox-switch": [
                    {
                        "element": "wrap-start",
                        "tag": "div",
                        "attributes": {
                            "class": "form-check"
                        }
                    },
                    {
                        "element": "input",
                        "value": "1",
                        "attributes": {
                            "type": "checkbox",
                            "id": "remember",
                            "name": "remember",
                            "class": "form-check-input"
                        }
                    },
                    {
                        "element": "label",
                        "text": "Remember",
                        "attributes": {
                            "class": "form-check-label",
                            "for": "remember"
                        }
                    },
                    {
                        "element": "wrap-end",
                        "tag": "div"
                    }
                ],
                "example-button": [
                    {
                        "element": "button",
                        "value": "1",
                        "text": "Submit",
                        "attributes": {
                            "type": "submit",
                            "class": "mt-2 w-100 btn btn-primary"
                        }
                    }
                ]
            }
        }
    }
}
```

Then:

```
    {:^include; plugins/build-form/snippets.ntpl :}

    {:code;
        {:param; form-name >> form-example-login :} {:* The key of the JSON containing the form *:}
        {:param; post-data >> POST-DATA :}          {:* The variable in which your app has stored the post data *:}

        {:snippet; neutral-build-form-parse :}      {:* Render form *:}
    :}
```