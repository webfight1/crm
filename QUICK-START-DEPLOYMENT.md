# 🚀 Kiire Deployment Juhend

## 1️⃣ Esimene Kord (Ainult Üks Kord)

```bash
# Käivita setup skript
./setup-git-on-server.sh
```

**Skript küsib:**
- Git repository URL (nt: `git@github.com:kasutaja/crm.git`)
- Branch nimi (vaikimisi: `main`)

**Mis juhtub:**
- ✅ Serveris tehakse backup `/opt/crm-backup-YYYYMMDD-HHMMSS`
- ✅ Initsialiseeritakse git repository
- ✅ Lisatakse git remote
- ✅ Pulltakse viimane kood

---

## 2️⃣ Igapäevane Deployment

```bash
# Tee muudatused koodis
# ...

# Deploy serverisse
./deploy-git.sh
```

**Mis juhtub:**
1. Kontrollib uncommitted muudatusi
2. Küsib commit message'i (kui vaja)
3. Teeb `git push`
4. Serveris teeb `git pull`
5. Uuendab dependencies
6. Käivitab migratsioonid
7. Puhastab cache'd
8. Restartib Docker konteinerit

**Aeg:** ~30 sekundit ⚡

---

## 3️⃣ Kui Muutub Dockerfile või Süsteemi Sõltuvused

```bash
# Kasuta vana Docker-põhist deploymenti
./deploy-to-production.sh
```

**Aeg:** ~5-10 minutit 🐢

---

## ❓ Probleemide Lahendamine

### Vaata konteinerit:
```bash
ssh root@45.93.139.96 "docker logs crm-app --tail 50"
```

### Käsitsi deploy:
```bash
ssh root@45.93.139.96
cd /opt/crm
git pull
docker exec crm-app composer install --no-dev --optimize-autoloader
docker exec crm-app php artisan migrate --force
docker exec crm-app php artisan cache:clear
docker restart crm-app
```

### Rollback eelmisele versioonile:
```bash
ssh root@45.93.139.96
cd /opt/crm
git log --oneline  # Vaata viimased commitid
git reset --hard HEAD~1  # Tagasi 1 commit
docker restart crm-app
```

---

## 📊 Võrdlus

| Meetod | Aeg | Millal kasutada |
|--------|-----|-----------------|
| `deploy-git.sh` | 30 sek | PHP kood, templates, migrations |
| `deploy-to-production.sh` | 5-10 min | Dockerfile, PHP versioon, süsteemi paketid |

---

## ✅ Checklist Enne Deploymenti

- [ ] Kõik testid läbivad lokaalses keskkonnas
- [ ] `.env` muudatused on dokumenteeritud
- [ ] Database migratsioonid on testitud
- [ ] Commit message on kirjeldav

---

## 🌐 Kontroll Pärast Deploymenti

- Rakendus: http://45.93.139.96:8082
- API: http://45.93.139.96:8082/api/tasks
- Logid: `ssh root@45.93.139.96 "docker logs crm-app --tail 50"`
