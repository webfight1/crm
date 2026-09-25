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
   │  Ettevõte + klient (prospect) + tehing (etapp: auto.deal_stage)
   ▼
Audit                                        [auto.audit_on_warm]
   │  Kontrollnimekiri (Playbook): sisseehitatud kontrollid + sinu AI-küsimused
   │  → kaalutud skoor 0–100 + kokkuvõte kliendile (AI juhised)
   ▼
Pakkumise MUSTAND                            [auto.offer_after_audit]
   │  põhiread + iga läbikukkunud kontrolli hinnaga parandus
   │  → Pakkumised (staatus draft). Saadad alati SINA.
   ▼
Telegram: üks kokkuvõttev teade + link auditile
```

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
| Audit | `app/Seo/Services/SeoAuditService.php`, HTML-kontrollid `PageAnalyzer.php` |
| Pakkumine | `app/Seo/Services/SeoOfferService.php` |
| Voog pärast vastust | `app/Seo/Jobs/HandleSeoReplyJob.php` (käivitab `OutreachLead::booted`) |
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
- [ ] **Konkurentide võrdlus auditis**: sama märksõna top 3 lehe sõnade arv ja pealkirjad.
- [ ] **Pakettide loogika**: skoori või leidude arvu järgi S/M/L pakett üksikridade asemel.
- [ ] **„Hiljem“ vastajad**: automaatne meeldetuletusülesanne 30/60/90 päeva pärast.
- [ ] **Pakkumise vastuvõtmine → ülesanded**: auditi leiud muutuvad tööülesanneteks.
- [ ] **Kirjamallide A/B**: vastamismäär malli järgi.

## Muudatuste logi
- 2026-09-24: Playbook, CSV filter, vastuste liigitus, soe klient, audit ja pakkumise mustand.
