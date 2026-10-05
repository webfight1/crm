#!/usr/bin/env python3
"""
One-time setup for /opt/messenger. Generates every secret itself and never
prints one; the CRM .env lines are written to ./crm.env (mode 600).

  1. python3 setup.py config     → edits meta/config.yaml (after the image wrote the default)
  2. docker run … meta           → bridge writes meta/registration.yaml
  3. python3 setup.py homeserver → writes tuwunel.toml with both appservices
"""
import os, secrets, sys, yaml

BASE = os.path.dirname(os.path.abspath(__file__))
SERVER = "wf.local"
USER = f"@veiko:{SERVER}"
CRM_URL = "https://crm.webfight.shop/api/chats/matrix"
STATE = os.path.join(BASE, ".secrets.yaml")


def load_state():
    if os.path.exists(STATE):
        return yaml.safe_load(open(STATE)) or {}
    state = {k: secrets.token_hex(24) for k in ("provision_secret", "crm_as_token", "crm_hs_token", "registration_token")}
    with os.fdopen(os.open(STATE, os.O_WRONLY | os.O_CREAT, 0o600), "w") as f:
        yaml.safe_dump(state, f)
    return state


def write_private(path, text):
    with os.fdopen(os.open(path, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600), "w") as f:
        f.write(text)


def config():
    s = load_state()
    path = os.path.join(BASE, "meta", "config.yaml")
    c = yaml.safe_load(open(path))
    c["homeserver"].update(address="http://tuwunel:8008", domain=SERVER)
    c["appservice"].update(address="http://meta:29319", hostname="0.0.0.0", port=29319, public_address=None)
    c["database"].update(type="sqlite3-fk-wal", uri="file:/data/meta.db?_txlock=immediate")
    c["bridge"]["permissions"] = {"*": "relay", SERVER: "user", USER: "admin"}
    c["bridge"]["personal_filtering_spaces"] = False
    c["provisioning"]["shared_secret"] = s["provision_secret"]
    c["backfill"]["enabled"] = False
    c["encryption"].update(allow=False, default=False, require=False)
    c["matrix"]["federate_rooms"] = False
    c["network"].setdefault("thread_backfill", {})["batch_count"] = 0
    c["logging"] = {"min_level": "info", "writers": [{"type": "stdout", "format": "pretty"}]}
    write_private(path, yaml.safe_dump(c, sort_keys=False))
    print("meta/config.yaml updated")


def homeserver():
    s = load_state()
    reg = yaml.safe_load(open(os.path.join(BASE, "meta", "registration.yaml")))

    def ns(kind, items):
        return "".join(
            f'\n[[global.appservice.{kind}]]\nexclusive = {str(i.get("exclusive", False)).lower()}\nregex = {toml_str(i["regex"])}\n'
            for i in items or []
        )

    toml = f"""[global]
server_name = "{SERVER}"
database_path = "/var/lib/tuwunel"
address = ["0.0.0.0"]
port = 8008
allow_federation = false
allow_registration = true
registration_token = "{s['registration_token']}"
trusted_servers = []
allow_check_for_updates = false
log = "warn"

[global.appservice.{reg['id']}]
url = {toml_str(reg['url'])}
as_token = "{reg['as_token']}"
hs_token = "{reg['hs_token']}"
sender_localpart = "{reg['sender_localpart']}"
rate_limited = false
receive_ephemeral = {str(reg.get('de.sorunome.msc2409.push_ephemeral', False)).lower()}
""" + ns(f"{reg['id']}.users", reg["namespaces"].get("users")) + ns(f"{reg['id']}.aliases", reg["namespaces"].get("aliases")) + f"""
# The CRM: read-only listener for every room on this private server.
[global.appservice.crm]
url = "{CRM_URL}"
as_token = "{s['crm_as_token']}"
hs_token = "{s['crm_hs_token']}"
sender_localpart = "crm"
rate_limited = false

[[global.appservice.crm.rooms]]
exclusive = false
regex = ".*"

# Lets the CRM read room members/name as the bridge bot (it is in every portal).
[[global.appservice.crm.users]]
exclusive = false
regex = {toml_str('^@' + reg['sender_localpart'] + ':' + SERVER.replace('.', chr(92) + '.') + '$')}
"""
    write_private(os.path.join(BASE, "tuwunel.toml"), toml)
    write_private(os.path.join(BASE, "crm.env"), f"""
MESSENGER_PROVISION_URL=http://127.0.0.1:29319/_matrix/provision
MESSENGER_PROVISION_SECRET={s['provision_secret']}
MESSENGER_MATRIX_USER={USER}
MESSENGER_HS_TOKEN={s['crm_hs_token']}
MESSENGER_AS_TOKEN={s['crm_as_token']}
MESSENGER_BOT={'@' + reg['sender_localpart'] + ':' + SERVER}
""")
    print("tuwunel.toml + crm.env written")


def toml_str(v):
    return '"' + str(v).replace("\\", "\\\\").replace('"', '\\"') + '"'


{"config": config, "homeserver": homeserver}[sys.argv[1]]()
