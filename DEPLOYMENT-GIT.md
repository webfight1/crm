# Git-Based Deployment Guide

## ⚠️ ESMAKORDSEKS SEADISTAMISEKS

**Kui kasutad esimest korda**, pead esmalt serveris git repository seadistama:

```bash
./setup-git-on-server.sh
```

See küsib sinu git repository URL-i ja seadistab kõik vajaliku serveris.

---

## Kiire Deploy

Pärast seadistamist, lihtsalt käivita:
```bash
./deploy-git.sh
```

See skript:
1. ✅ Kontrollib, kas kõik muudatused on committed
2. ✅ Teeb `git push` repositooriumi
3. ✅ Serveris teeb `git pull` Docker volume'i
4. ✅ Uuendab Composer dependencies
5. ✅ Käivitab database migratsioonid
6. ✅ Puhastab cache'd
7. ✅ Restartib Docker konteinerit

## Esmakordseks Seadistamiseks Serveris

Kui serveris pole veel git repository't, tee järgmist:

```bash
ssh root@45.93.139.96

# Navigeeri õigesse kausta
cd /opt

# Kustuta vana crm kaust kui vaja
rm -rf crm

# Clone git repository
git clone <your-git-repo-url> crm

# Seadista .env fail
cd crm
cp .env.example .env
nano .env  # Muuda andmebaasi ühendust jms

# Käivita Docker konteiner (kui pole veel käivitatud)
docker run -d \
    --name crm-app \
    --network crm_network \
    -p 8082:80 \
    -v /opt/crm:/var/www/html \
    -v /opt/crm/.env:/var/www/html/.env \
    --restart unless-stopped \
    crm-app:latest
```

## Kui Midagi Läheb Valesti

### Kontrolli konteinerit:
```bash
ssh root@45.93.139.96
docker ps -a | grep crm-app
docker logs crm-app
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

## Erinevus Vanade Deployment Skriptidega

### Vana viis (deploy-to-production.sh):
- ❌ Aeglane - buildib Docker image'i (~5-10 min)
- ❌ Suur - üles laeb terve image (~500MB+)
- ✅ Isoleeritud - täielik keskkond

### Uus viis (deploy-git.sh):
- ✅ Kiire - ainult git pull (~10-30 sek)
- ✅ Väike - ainult muudetud failid
- ✅ Lihtne - git push + git pull
- ⚠️ Vajab git repository seadistamist serveris

## Millal Kasutada Vana Viisi?

Kasuta `deploy-to-production.sh` kui:
- Muutuvad PHP versioonid
- Muutuvad Apache/Nginx konfiguratsioonid
- Muutuvad süsteemi sõltuvused (apt packages)
- Muutub Dockerfile.prod

Kasuta `deploy-git.sh` kui:
- Muutub ainult PHP kood
- Muutuvad Blade template'id
- Muutuvad Composer dependencies
- Muutuvad database migratsioonid
- Muutub .env konfiguratsioon

## Näpunäited

1. **Enne deploymenti:**
   - Testi koodi lokaalses keskkonnas
   - Veendu, et kõik testid läbivad
   - Commit'i kõik muudatused

2. **Pärast deploymenti:**
   - Kontrolli, kas rakendus töötab: http://45.93.139.96:8082
   - Vaata logisid: `ssh root@45.93.139.96 "docker logs crm-app --tail 50"`

3. **Rollback:**
   ```bash
   ssh root@45.93.139.96
   cd /opt/crm
   git log --oneline  # Vaata viimased commitid
   git reset --hard <commit-hash>  # Tagasi eelmisele versioonile
   docker restart crm-app
   ```
