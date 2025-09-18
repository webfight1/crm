# Zone.eu Email Sender API

See kaust sisaldab faile, mis tuleb üles laadida Zone.eu serverile, et võimaldada Laravel CRM-il saata e-maile Zone.eu SMTP serveri kaudu.

## Failid

### `email_sender_api.php`
- Peamine API fail, mis võtab vastu JSON päringuid Laravel CRM-ist
- Saadab e-maile Zone.eu SMTP serveri (`mail.zone.eu`) kaudu
- Logib kõik tegevused `email_api.log` faili

### `test_api.php`
- Testimise skript API funktsionaalsuse kontrollimiseks
- Käivita see pärast API üleslaadimist

## Seadistamine

### 1. Zone.eu serveris

1. **Lae failid üles** Zone.eu serveri kausta (nt. `/api/`)
2. **Muuda seadeid** `email_sender_api.php` failis:
   ```php
   $api_token = 'your-secure-api-token-here-change-this'; // Genereeri turvaline token
   ```
3. **Testi API-t**:
   ```bash
   php test_api.php
   ```

### 2. Laravel CRM-is (VPS)

1. **Uuenda Docker Compose** seadeid:
   ```yaml
   ZONE_EMAIL_API_URL: "https://sinu-domeen.ee/api/email_sender_api.php"
   ZONE_EMAIL_API_TOKEN: "sama-token-mis-api-failis"
   ```

2. **Taaskäivita konteinerid**:
   ```bash
   docker-compose up -d
   ```

## API Kasutamine

### Päring (POST)
```json
{
    "api_token": "your-secure-api-token",
    "recipient_email": "test@example.com",
    "subject": "E-maili teema",
    "message": "<h1>HTML sisu</h1><p>Ettevõte: {company_name}</p>",
    "company_name": "Ettevõte OÜ",
    "recipient_name": "Kasutaja Nimi"
}
```

### Edukas vastus
```json
{
    "success": true,
    "message": "Email sent successfully",
    "recipient": "test@example.com",
    "timestamp": "2025-09-18 19:00:00"
}
```

### Vea vastus
```json
{
    "success": false,
    "error": "Invalid email address",
    "recipient": "invalid-email"
}
```

## Turvalisus

- ✅ API token autentimine
- ✅ E-maili aadressi valideerimine
- ✅ JSON andmete valideerimine
- ✅ Logimise süsteem
- ✅ HTTP meetodi kontroll

## Logid

- `email_api.log` - API tegevuste logi
- `api_requests.log` - Kõik päringud (JSON formaat)

## Muutujad sõnumis

API toetab järgmisi muutujaid:
- `{company_name}` - asendatakse ettevõtte nimega
- `{recipient_name}` - asendatakse saaja nimega

## Vea diagnoosimise

1. **Kontrolli logisid** Zone serveris
2. **Testi API-t** `test_api.php` abil
3. **Kontrolli SMTP seadeid** Zone.eu paneelis
