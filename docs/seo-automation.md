# SEO teenuse automatiseerimine — strateegia

See dokument kirjeldab SEO müügiprotsessi CRM-is: mis on automaatne, mis jääb käsitsi ja mida järgmisena ehitada.
**Muuda seda vabalt.** Kirjuta uued ideed allpool olevasse nimekirja ja ütle Claude Code'ile „tee strateegiadokis järgmine punkt“.

## Kaks tasandit

| Mida muudad | Kus | Kood muutub? |
|---|---|---|
| Tekstid, filtrid, AI juhised, auditipunktid, hinnad, automaatika sisse/välja | CRM → E-post → **SEO Playbook** (`/seo`) | Ei |
| Protsessi sammud, uued integratsioonid, uued andmeväljad | See dokument → arendus | Jah |

Rusikareegel: kui muudatuse saab kirjeldada reeglina („kirjuta lühemalt“, „ära võta leade 50. kohast allpool“, „kontrolli, kas lehel on hinnad“), käib see Playbooki. Kui on vaja uut sammu või uut andmeallikat, käib see siia.

## Protsess praegu

```
SEO-monitor CSV
   │  Outreach → kampaania → CSV import
   │  • veerunimed vastendatakse (Playbook: CSV veerunimede vasted)
   │  • positsioon väljaspool vahemikku / välistatud domeen → "skip"
   ▼
Müügikiri
   │  AI mustand = kampaania juhised + Playbook "Müügikirja juhised"
   │  • {{keyword}}, {{position}}, {{google_page}}, {{competitors}}
   │  • sina kinnitad mustandid → Outreach saadab
   ▼
Vastus (IMAP, iga 5 min)
   │  HandleSeoReplyJob (2 min viitega), kui leadil on märksõna:
   │  • AI liigitab: huvitatud / hiljem / pole huvitatud / automaatvastus / ebaselge
   ▼
Soe klient                                   [auto.create_client]
   │  VÕI käsitsi: SEO → Auditid → „+ Lisa soe klient käsitsi“ (telefon, soovitus).
   │  Luuakse lead peidetud kampaaniasse „SEO – käsitsi lisatud kliendid“ (väljas, ei saada),
   │  AI liigitus jääb vahele, edasi täpselt sama voog.
   │  Ettevõte + klient (prospect) + tehing (etapp: auto.deal_stage)
   ▼
Märksõna leht
   │  1) CSV veerg ranking_url (Google'i reastuv leht) → seda kasutatakse
   │  2) muidu otsitakse: sitemap + avalehe menüülingid → URL/lingitekst
   │     → parimad 5 lehte laaditakse, võrreldakse title + H1 märksõnaga
   │  3) ei leitud → auditeeritakse avalehte, kontroll „Märksõnal on oma leht“ kukub läbi
   ▼
Saidi tüüp: E-POOD või KODULEHT  (hargnemine)
   │  Tunnused: platvorm (WooCommerce, Shopify…), ostukorv, Product schema,
   │  toodete sitemap, tooteaadressid → skoor ≥ 4 = e-pood. Käsitsi auditis saab ise valida.
   │  Kontrolli väli „Kehtib“ (kõik / koduleht / e-pood) otsustab, mis kontrollid jooksevad.
   │  E-poe lisakontrollid:
   │   • kategooriate nimed vs Google'i otsingusoovitused (AI pakub parema nime)
   │   • tootelehel Product schema
   │   • kategoorialehel tutvustav tekst (AI-kontroll)
   ▼
Audit                                        [auto.audit_on_warm]
   │  Kontrollnimekiri (Playbook): sisseehitatud kontrollid + sinu AI-küsimused
   │  → kaalutud skoor 0–100 + kokkuvõte kliendile (AI juhised)
   ▼
Täpsustuskiri (MUSTAND postkastis)           [clarify.enabled]
   │  „Kas see leht on õige? Kas huvitavad ka muud märksõnad?“
   │  + valikuliselt: „lisage mind Search Console'i kasutajaks (Piiratud)“ [clarify.gsc_email]
   │  Sina saadad postkastist → lead ootab vastust
   ▼
Kliendi vastus (HandleSeoClarifyAnswerJob)
   │  • nimetas teise lehe → uus audit sellel lehel
   │  • kinnitas → leht märgitakse kliendi kinnitatuks
   │  • lisamärksõnad → igaühele otsitakse leht; puuduv leht = müügivõimalus
   ▼
Pakkumise MUSTAND                            [auto.offer_after_audit, clarify.wait_for_answer]
   │  Kliendi vastus pakkumise kirjale („Re: Pakkumine #Q…“) → AI: sobib / küsimused / ei sobi
   │  → Telegram + tehingu link; „sobib“ märgib pakkumise vastuvõetuks. Tehingu „töös“ paned sina.
   │  + sisuturundus, kui skoor ≥ content.min_score (tehniliselt korras):
   │    blogi puudub → blogi loomine; blogi olemas → artikkel 1× nädalas (kuupakett)
   │  põhiread + iga läbikukkunud kontrolli hinnaga parandus
   │  → Pakkumised (staatus draft). Saadad alati SINA.
   ▼
Telegram: üks kokkuvõttev teade + link auditile
```

### Ligipääsud astmeti
1. **Tasuta eelanalüüs:** ei küsita midagi — audit kasutab ainult avalikke andmeid + SEO-monitori positsiooni.
2. **Soe klient:** täpsustuskirjas valikuline palve anda Search Console'i õigus „Piiratud“ (ainult vaatamine).
3. **Tasuline töö** (tehing etappi `closed_won`/`töös` või auditi lehel „🔑 Küsi ligipääsud“): ülesanne + kirjamustand postkastis.
   Audit tuvastab majutaja: lehe koodist platvorm (Voog, Wix, Shopify… → haldurikutse), muidu IP → serveri nimi + võrgu omanik (ASN) → Playbooki „Majutajate tuvastus“
   (Zone.ee, Veebimajutus.ee/Elkdata, Radicenter…). Kirja pannakse selle majutaja SSH-juhend (Playbook „Juhendid majutaja kaupa“, sinu avalik võti) + Search Console „Täielik“.
   Cloudflare'i taga näeb ainult nimeservereid — märgitakse oletusena. Paroole e-postiga ei küsi.
   Samal hetkel luuakse kliendile projekt **SEO-monitoris** (seo.webfight.ee): domeen, `sc-domain:` property, põhimärksõna oma lehega +
   lisamärksõnad, kliendikonto (roll client) ja ühekordne paroolilink kirja. Search Console'i ühendad ise projekti seadetes (Google'i nõusolek).
   Vajab CRM-i `.env`-i: `SEO_MONITOR_API_URL`, `SEO_MONITOR_API_TOKEN` (SEO-monitori admin-teenusekasutaja `crm@webfight.ee` token).

### Käsitsi kontrollpunktid (teadlikult)
1. **Mustandite kinnitamine** enne saatmist. Kui kirjad on paar nädalat head olnud, võib selle sammu kaotada.
2. **Pakkumise saatmine.** Hind ja lubadused on sinu vastutus, seega seda ei automatiseerita.

## Kood (arendaja jaoks)

| Osa | Fail |
|---|---|
| Seaded + vaikeväärtused (üks koht) | `app/Seo/Playbook.php` → `DEFINITIONS` |
| Leht + statistika | `app/Http/Controllers/Seo/SeoController.php`, `resources/views/seo/` |
| CSV filter / veerunimed | `app/Seo/Services/SerpLeadFilter.php` (kasutab `OutreachCsvImportService`) |
| Vastuse liigitus | `app/Seo/Services/ReplyIntentService.php` |
| Klient + tehing | `app/Seo/Services/WarmClientService.php` |
| Märksõna lehe leidmine | `app/Seo/Services/LandingPageFinder.php` (saidi lugemine: `SiteCrawler.php`) |
| E-pood või koduleht | `app/Seo/Services/SiteTypeDetector.php` |
| E-poe kontrollid (kategooriad, Product schema) | `app/Seo/Services/EshopAnalyzer.php` |
| Täpsustuskiri + vastuse lugemine | `app/Seo/Services/ClarifyService.php`, `app/Seo/Jobs/HandleSeoClarifyAnswerJob.php` (käivitab `OutreachMessage::booted`) |
| Audit | `app/Seo/Services/SeoAuditService.php`, HTML-kontrollid `PageAnalyzer.php` |
| Pakkumine | `app/Seo/Services/SeoOfferService.php` |
| Voog pärast vastust | `app/Seo/Jobs/HandleSeoReplyJob.php` (käivitab `OutreachLead::booted`) |
| Majutaja tuvastus + ligipääsukiri | `app/Seo/Services/HostingDetector.php`, `AccessRequestService.php` (käivitab `Deal::updated` AppServiceProvideris) |
| SEO-monitori projekt + kliendikonto | `app/Seo/Services/SeoMonitorClient.php`, `SeoMonitorSyncService.php` |
| Klientide ülevaade (etapid ✓/●/⏳/○) | `app/Seo/Services/PipelineBoard.php` → `/seo/kliendid` (sama loogika hiljem OpHubi API-sse) |
| AI klient (mudel Playbookist) | `app/Seo/Services/SeoAi.php` |

**Uus seadistatav reegel:** lisa kirje `Playbook::DEFINITIONS`-isse ja loe seda `Playbook::get()`-iga. Seadete leht tekib selle põhjal ise.
**Uus sisseehitatud auditikontroll:** lisa meetod `PageAnalyzer::check()` (või `SeoAuditService::networkCheck()`) ja rida `seo_audit_checks` tabelisse sama võtmega.
**Uus AI-kontroll:** koodi pole vaja, lisa see Playbooki lehel.

## Mõõdikud (Playbooki lehel)
- **Lehter:** imporditud, filtreeritud, saadetud, vastanud, soe, auditeeritud, pakkumine tehtud, pakkumine vastu võetud.
- **Vastamismäär positsiooni järgi:** selle põhjal sea filtri min/max.
- **Vastuste liigid:** kui „ebaselge“ on palju, täpsusta vastuste liigitamise juhiseid.

## Järgmised sammud (arenduse järjekord)

- [ ] **SEO-monitori näidis-CSV** → kontrolli veerunimede vasteid ja lisa puuduvad.
- [ ] **Otsingumaht** (`search_volume`) väljaks + filter „min otsingumaht“ + `{{volume}}` kirjades.
- [ ] **Kontaktide leidmine**: kui CSV-s pole e-posti, otsi see kodulehe kontaktlehelt või Äriregistrist.
- [ ] **Lisamärksõnad pakkumisse**: kliendi nimetatud märksõnad, millel pole oma lehte, lisatakse pakkumisse eraldi ridadena („teenuselehe loomine“).
- [ ] **Otsingumahud kategooriatele**: Google'i soovitused näitavad sõnastust, mitte mahtu. Täpsem oleks Keyword Planner / DataForSEO (tasuline API).
- [ ] **E-poe täpsustuskiri**: e-poele küsida „kategooria“ asemel „leht“, ja pakkuda kategooriate ümbernimetamist.
- [ ] **Konkurentide võrdlus auditis**: sama märksõna top 3 lehe sõnade arv ja pealkirjad.
- [ ] **Pakettide loogika**: skoori või leidude arvu järgi S/M/L pakett üksikridade asemel.
- [ ] **„Hiljem“ vastajad**: automaatne meeldetuletusülesanne 30/60/90 päeva pärast.
- [ ] **Pakkumise vastuvõtmine → ülesanded**: auditi leiud muutuvad tööülesanneteks.
- [ ] **Kirjamallide A/B**: vastamismäär malli järgi.

## Muudatuste logi
- 2026-09-26: Sama meiliaadressiga mitu leadi (vana kampaania + soe klient): kirjad lähevad aktiivsele SEO-kliendile, AI loeb ainult selle ringi kirju, postkastis on vanemad kirjad kokku volditud. Soe klient loetakse vastanuks, et tema järgmine kiri ahelat uuesti ei käivitaks.
- 2026-09-26: SEO klientide ülevaade `/seo/kliendid`: iga kliendi etapid, kelle järel ootab ja mitu päeva.
- 2026-09-26: Vastus pakkumisele → AI otsus + tehingu link Telegrami.
- 2026-09-26: Võidetud kliendile luuakse SEO-monitoris projekt, märksõnad ja kliendikonto; paroolilink ligipääsukirja.
- 2026-09-26: Majutaja tuvastus (platvorm / IP + ASN / nimeserverid) ja ligipääsukiri majutajapõhise SSH-juhendiga, kui tehing võidetakse.
- 2026-09-26: Täpsustuskirjas valikuline Search Console'i ligipääsu palve koos juhisega.
- 2026-09-26: Blogi tuvastus auditis; tehniliselt korras saidile pakutakse blogi loomist või iganädalasi artikleid (Playbook „Sisuturundus“).
- 2026-09-26: Sooja kliendi käsitsi lisamine (SEO → Auditid); kliendi vastus seotakse ka CRM-kliendi kaudu.
- 2026-09-26: Pakkumise read grupeeritakse (kontrolli „Grupp pakkumises“, Playbook `offer.group_items`).
- 2026-09-25: Saidi tüübi hargnemine (e-pood / koduleht), kontrollide „Kehtib“, e-poe kategooriate analüüs Google'i otsingusoovitustega, Product schema.
- 2026-09-25: Märksõna lehe leidmine (CSV `ranking_url` / sitemap / menüü), kontroll „Märksõnal on oma leht“, täpsustuskirja mustand postkastis, kliendi vastusest leht + lisamärksõnad, pakkumine pärast vastust.
- 2026-09-24: Playbook, CSV filter, vastuste liigitus, soe klient, audit ja pakkumise mustand.
