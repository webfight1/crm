# 🚀 CRM Deployment Võimalused

## Ülevaade

Sul on nüüd **3 erinevat deployment meetodit**:

---

## 1. 🎯 Git-Põhine Deploy (SOOVITATUD IGAPÄEVASEKS)

**Skript:** `deploy-git.sh`

### ✅ Plussid:
- ⚡ **Kiire** - ainult 30 sekundit
- 📦 **Väike** - ainult muudetud failid
- 🔄 **Lihtne** - git push + git pull
- 💰 **Odav** - väike andmemahtu kasutus

### ❌ Miinused:
- Vajab git repository seadistamist serveris (ainult esimest korda)
- Ei uuenda Docker image'i (PHP versioon, süsteemi paketid)

### 📝 Kasutamine:
```bash
./deploy-git.sh
```

### 🎯 Millal kasutada:
- ✅ PHP koodi muudatused
- ✅ Blade template'id
- ✅ JavaScript/CSS
- ✅ Database migratsioonid
- ✅ Composer dependencies
- ✅ Config failid

---

## 2. 🐳 Docker Image Deploy (KAPITAALSED MUUDATUSED)

**Skript:** `deploy-to-production.sh`

### ✅ Plussid:
- 🔒 **Isoleeritud** - täielik keskkond
- 🛡️ **Turvaline** - kõik on image'is
- 🔧 **Täielik kontroll** - PHP versioon, süsteemi paketid

### ❌ Miinused:
- 🐢 **Aeglane** - 5-10 minutit
- 📦 **Suur** - ~500MB+ image
- 💾 **Ressursimahukas** - build + upload

### 📝 Kasutamine:
```bash
./deploy-to-production.sh
```

### 🎯 Millal kasutada:
- ✅ PHP versiooni muudatus
- ✅ Apache/Nginx konfiguratsioon
- ✅ Süsteemi paketid (apt install)
- ✅ Dockerfile.prod muudatused
- ✅ PHP extensionid

---

## 3. 📁 Rsync Deploy (VANA MEETOD)

**Skript:** `deploy.sh`

### ✅ Plussid:
- 📂 **Lihtne** - ainult failide kopeerimine
- 🔄 **Kiire** - sarnane git-põhisele

### ❌ Miinused:
- ❌ Ei kasuta git versiooni kontrolli
- ❌ Raskem rollback
- ❌ Ei ole Docker-keskkonnaga integreeritud

### 📝 Kasutamine:
```bash
./deploy.sh
```

---

## 📊 Võrdlustabel

| Meetod | Aeg | Suurus | Kasutamine | Rollback |
|--------|-----|--------|------------|----------|
| **Git-Deploy** | 30 sek | ~10MB | Igapäevane | ✅ Lihtne |
| **Docker-Deploy** | 5-10 min | ~500MB | Harv | ⚠️ Keeruline |
| **Rsync-Deploy** | 1-2 min | ~50MB | Vananenud | ❌ Raske |

---

## 🎯 Soovitatud Töövoog

### Igapäevane arendus:
1. Tee muudatused koodis
2. Testi lokaalses keskkonnas
3. Commit muudatused
4. Käivita: `./deploy-git.sh`

### Suuremad muudatused (1-2 korda kuus):
1. Muuda Dockerfile.prod või PHP versiooni
2. Testi lokaalses keskkonnas
3. Commit muudatused
4. Käivita: `./deploy-to-production.sh`

---

## 🔧 Esmakordseks Seadistamiseks

### Git-Deploy seadistamine:
```bash
./setup-git-on-server.sh
```

See küsib:
- Git repository URL
- Branch nimi

---

## 📚 Täpsemad Juhendid

- **Git-Deploy:** Vaata `DEPLOYMENT-GIT.md`
- **Kiire Start:** Vaata `QUICK-START-DEPLOYMENT.md`
- **Docker-Deploy:** Vaata `DEPLOYMENT.md`

---

## 💡 Näpunäited

1. **Kasuta Git-Deploy igapäevaseks tööks** - see on kiire ja lihtne
2. **Kasuta Docker-Deploy ainult kui vaja** - PHP versioon, süsteemi paketid
3. **Tee backup enne suuri muudatusi** - serveris `/opt/crm-backup-*`
4. **Kontrolli logisid pärast deploymenti** - `docker logs crm-app`

---

## 🆘 Abi

Kui midagi läheb valesti:

```bash
# Vaata logisid
ssh root@45.93.139.96 "docker logs crm-app --tail 100"

# Restart konteinerit
ssh root@45.93.139.96 "docker restart crm-app"

# Rollback
ssh root@45.93.139.96
cd /opt/crm
git reset --hard HEAD~1
docker restart crm-app
```
