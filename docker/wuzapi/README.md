# WuzAPI — WhatsApp → CRM (Vestlused)

Loeb isikliku WhatsAppi sõnumeid ja saadab need webhookiga CRM-i
(`/api/chats/whatsapp/{secret}`). CRM ainult loeb — vastad telefonist.

* Üks konteiner, SQLite, meediat ei laeta (`-skipmedia`), ~30 MB RAM.
* API kuulab ainult `127.0.0.1:8091`.
* Kohalolek `unavailable` — kontaktid ei näe sind „online“ ja telefoni teavitused töötavad edasi.

## Paigaldus (VPS, /opt/wuzapi)

```bash
mkdir -p /opt/wuzapi && cd /opt/wuzapi
# kopeeri siia docker-compose.yml ja .env.example → .env, täida tokenid
docker compose up -d --build
```

Loo üks WuzAPI kasutaja (token = CRM-i `WUZAPI_USER_TOKEN`):

```bash
USER_TOKEN=$(openssl rand -hex 24)
curl -s -X POST -H "Authorization: $WUZAPI_ADMIN_TOKEN" -H 'Content-Type: application/json' \
  --data "{\"name\":\"crm\",\"token\":\"$USER_TOKEN\",\"events\":\"Message\"}" \
  http://127.0.0.1:8091/admin/users
```

CRM-i `.env`:

```
WUZAPI_URL=http://127.0.0.1:8091
WUZAPI_USER_TOKEN=<USER_TOKEN>
WUZAPI_WEBHOOK_SECRET=<openssl rand -hex 24>
```

Seejärel CRM → **Vestlused → ⚙ Ühendus → „Ühenda“** ja skaneeri QR-kood
(WhatsApp → Lingitud seadmed). „Ühenda“ seab ühtlasi WuzAPI webhooki CRM-i
avalikule HTTPS-aadressile (WuzAPI ei luba webhooki privaatsele IP-le).

## Mis salvestub

* Vestlus, mille telefoninumber on CRM-is kontaktil/kliendil → jälgitakse automaatselt, sõnumid salvestatakse.
* Muud vestlused → ainult nimi, number ja viimase sõnumi aeg. „Jälgi“ nupuga saab lisada.
* „AI automaatselt“ (vestluse lehel) → iga 5 min analüüsib uued kliendisõnumid; uue soovi korral ülesanne + Telegram.
