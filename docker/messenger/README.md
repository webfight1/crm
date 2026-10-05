# Messenger → CRM (Vestlused)

Isikliku Facebook Messengeri sõnumid CRM-i, **ainult lugemiseks** (vastad telefonist).

* `tuwunel` — minimaalne privaatne Matrixi server (`wf.local`, föderatsioon väljas, ainult `127.0.0.1:8008`).
* `meta` — mautrix-meta sild (ka krüpteeritud Messengeri vestlused), sisselogimise API `127.0.0.1:29319`.
* CRM on Matrixis teine appservice: tuwunel saadab iga ruumi sündmuse aadressile
  `https://crm.webfight.shop/api/chats/matrix` (auth = `hs_token`).

Kokku umbes 100–150 MB RAM-i.

## Paigaldus (VPS, /opt/messenger) — tehtud 2026-10-05

```bash
mkdir -p /opt/messenger/{meta,tuwunel} && cd /opt/messenger   # + docker-compose.yml, setup.py
docker run --rm -v $PWD/meta:/data dock.mau.dev/mautrix/meta:latest   # vaikimisi config.yaml
python3 setup.py config                                                # sätted + saladused (.secrets.yaml)
docker run --rm -v $PWD/meta:/data dock.mau.dev/mautrix/meta:latest   # registration.yaml
python3 setup.py homeserver                                            # tuwunel.toml + crm.env
chmod 644 tuwunel.toml && docker compose up -d
```

Silla omanik `@veiko:wf.local` registreeritakse üks kord `registration_token`-iga
(`.secrets.yaml`). `crm.env` read lisatakse `/opt/crm/.env`-i.

## Sisselogimine

CRM → **Vestlused → ⚙ Ühendus → Messenger**: kleebi messenger.com-i päringu „Copy as cURL“
(vajalikud küpsised `c_user`, `xs`, `datr`). CRM annab küpsised ainult sillale edasi.
Meta võib harva küsida kontol turvakontrolli (captcha / parooli vahetus) — siis logi uuesti sisse.

## Mis salvestub

Sama mis WhatsAppis: kontakt/klient, kelle **täisnimi** on täpselt sama, mis Messengeris → jälgitakse
automaatselt; muud vestlused — ainult nimi ja viimase sõnumi aeg, „Jälgi“ nupuga saab lisada.
