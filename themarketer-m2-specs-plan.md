# theMarketer / Magento2 — Specs, plan de implementare și estimări

**Repository:** https://github.com/the-marketer/Magento2 · commit `25a2479`
**Domeniu:** remedierea celor 18 probleme din auditul de securitate & best practices
**Documente însoțitoare:** `themarketer-magento2-audit.md` (auditul), `themarketer-m2-task-tracker.xlsx` (taskuri + man-hours)

## Cum se citește documentul

Fiecare problemă are: **ID**, **Spec** (ce e greșit, de ce contează, criterii de acceptanță testabile) și **Plan de implementare** (pași concreți). Fiecare spec e spartă în taskuri punctuale în fișierul XLSX, unde man-hours sunt estimate separat per task.

Convenții de estimare folosite în tracker:
- Estimările sunt în **ore-om (man-hours)** pentru un dezvoltator Magento 2 mid/senior.
- Fiecare problemă include, unde e cazul, sub-taskuri de **implementare**, **teste** și **QA/review**.
- Nu includ timp de release/deploy pe producție (variază per client); există un task global de regression + release la final.
- Estimările sunt „pesimist-realiste" (includ overhead de context-switching), nu best-case.

Legendă severitate: 🔴 High · 🟠 Medium · 🟡 Low · 🔵 Info

---

# A. Probleme de securitate

## SEC-01 🔴 Reactivare verificare TLS la apelurile de ieșire

**Spec.** În `Model/Api.php` apelurile cURL setează `CURLOPT_SSL_VERIFYPEER = false`, dezactivând validarea certificatului către `t.themarketer.com`. Prin acest canal pleacă PII (`save_order`, `add_subscriber`) și, indirect, credențiale. Rezultă expunere la MITM.
*De ce contează:* interceptarea/alterarea traficului cu date de clienți; posibil furt de credențiale.
*Criterii de acceptanță:*
- Toate cererile ies cu `CURLOPT_SSL_VERIFYPEER = true` și `CURLOPT_SSL_VERIFYHOST = 2` (sau echivalent al clientului HTTP Magento).
- O cerere către un endpoint cu certificat invalid eșuează controlat (nu se trimit date).
- Nu mai există nicio referință la dezactivarea verificării TLS în cod.

**Plan de implementare.**
1. Înlocuiește apelurile cURL brute cu `Magento\Framework\HTTP\Client\Curl` (sau Guzzle) injectat prin DI.
2. Elimină `CURLOPT_SSL_VERIFYPEER = false`; setează explicit verificarea activă.
3. Adaugă timeout-uri configurabile și tratarea codului de status.
4. Test: mock al clientului HTTP + un test de integrare care validează refuzul certificatului invalid.

## SEC-02 🔴 Cheia REST scoasă din URL, mutată pe header Authorization

**Spec.** Endpoint-urile `Controller/Api/*` primesc cheia prin query string (`?key=`), documentat și în `ReadMe.md`. Ajunge în loguri web/proxy/CDN, `Referer`, istoric browser. Cheia dă acces la exportul complet de comenzi (PII) și la generarea de discounturi.
*Criterii de acceptanță:*
- Toate endpoint-urile acceptă cheia prin `Authorization: Bearer <key>` (regula `KeyAuth`).
- Autentificarea prin `?key=` este eliminată (sau, tranzitoriu, deprecată explicit și logată).
- `ReadMe.md` și documentația API actualizate; niciun exemplu cu cheia în URL.

**Plan de implementare.**
1. Generalizează regula `KeyAuth` din `Model/Func.php` și aplic-o pe toate controllerele (Feed, Orders, Category, Brands, Reviews, Subscribes, CodeGenerator).
2. Elimină regula `Key` bazată pe query param (sau adaugă fallback deprecat cu warning în log pentru o versiune).
3. Actualizează `ReadMe.md` + docs.
4. Teste pentru fiecare controller: 200 cu Bearer valid, 401 fără/greșit.

## SEC-03 🔴 Eliminare endpoint de debug și domeniu `.ga`

**Spec.** `Model/Api.php` conține `debug()` care trimite payload-ul (cu `k`=cheia REST și `u`=customer id atașate automat de `REST()`) către `https://eaxdev.ga/mktr/BugTrap`. Domeniile `.ga` pot fi preluate de terți la expirare → scurgere de credențiale.
*Criterii de acceptanță:*
- `debug()`, `$bURL` și URL-urile comentate `eaxdev.ga` sunt eliminate din cod.
- Nu se mai trimit date către niciun domeniu de dezvoltare.
- (Opțional) Dacă e nevoie de telemetrie, e configurabilă, dezactivată implicit și nu trimite niciodată credențiale.

**Plan de implementare.**
1. Șterge metoda `debug()` și constanta `$bURL` din `Model/Api.php`.
2. Caută în tot codul apeluri `->debug(` și referințe `eaxdev` și curăță-le.
3. Verifică logica de raportare a erorilor și înlocuiește cu `LoggerInterface` local (vezi SEC/BP-E).

## SEC-04 🟠 Comparație de chei constant-time (`hash_equals`)

**Spec.** În `Model/Func.php`, cazurile `Key` și `KeyAuth` compară cheile cu `!==` — nu e constant-time (timing attack).
*Criterii de acceptanță:* orice comparație de secrete folosește `hash_equals()`; nu mai există comparație directă `===`/`!==` pe cheie/token.

**Plan de implementare.**
1. Înlocuiește comparațiile cu `hash_equals((string)$expected, (string)$provided)`.
2. Asigură ordinea argumentelor (known-string primul).
3. Test unitar pentru validare corectă/incorectă.

## SEC-05 🟠 Stocare criptată a cheilor în configurație

**Spec.** În `etc/adminhtml/system.xml` câmpurile `tracking_key`, `rest_key`, `customer_id` sunt `type="text"` → stocate în clar în `core_config_data`, afișate în clar în admin.
*Criterii de acceptanță:*
- Câmpurile sensibile sunt `type="obscure"` + `backend_model` de criptare.
- Valorile se citesc decriptat prin `EncryptorInterface`.
- Migrare: valorile existente rămân funcționale (script de re-criptare sau re-salvare documentată).

**Plan de implementare.**
1. Setează `type="obscure"` și `<backend_model>Magento\Config\Model\Config\Backend\Encrypted</backend_model>` pe cele 3 câmpuri.
2. Adaptează citirea în `Model/Config.php` să decripteze prin `EncryptorInterface`.
3. Script de upgrade (`setup:upgrade` / patch de date) pentru re-criptarea valorilor existente.
4. Teste + verificare în admin.

## SEC-06 🟠 PII pe disc: mutare în `var/`, secret în nume, expirare

**Spec.** `Model/Func.php` (`readOrWrite`, `Write`) + `Model/FileSystem.php` scriu răspunsuri (comenzi cu email/telefon/adresă) necriptat, cu nume predictibil (`base64` fără secret), fără expirare, în directorul modulului din `app/code`.
*Criterii de acceptanță:*
- Cache-ul se scrie în `var/` prin API-ul Magento de filesystem, nu în `app/code`.
- Numele fișierului include un secret (HMAC), nu doar base64.
- Fișierele au TTL / mecanism de curățare; PII nu persistă nelimitat.
- (Recomandat) conținutul cu PII e criptat la scriere sau nu se persistă deloc.

**Plan de implementare.**
1. Refactor `FileSystem.php` să folosească `Filesystem\Directory\WriteInterface` pe `DirectoryList::VAR_DIR`.
2. Schimbă schema de denumire în `readOrWrite`/`Write`: HMAC cu cheie internă în loc de `base64` simplu.
3. Adaugă TTL + job de curățare (extinde cronul existent `Model/Cron`).
4. Decizie produs: criptare la scriere sau eliminare cache pentru PII.
5. Teste: scriere/citire/expirare, verificare că nu se scrie în `app/code`.

## SEC-07 🟠 Controllere: interfețe HTTP moderne + separarea acțiunilor cu efecte

**Spec.** Toate `Controller/Api/*` extind `Magento\Framework\App\Action\Action` (depreciat). `SaveOrder`/`setEmail` produc efecte (trimit date, mutează sesiunea) pe GET.
*Criterii de acceptanță:*
- Fiecare controller implementează `HttpGetActionInterface` sau `HttpPostActionInterface` potrivit.
- Acțiunile cu efecte nu se declanșează pe GET simplu neintenționat.
- Comportament funcțional identic pentru integrarea existentă.

**Plan de implementare.**
1. Refactor fiecare controller la interfețele `Http*ActionInterface` + `ResultInterface`.
2. Reevaluează `SaveOrder`/`setEmail`: menține compatibilitatea cu loader-ul JS, dar clarifică metoda.
3. Teste funcționale per rută.

## SEC-08 🟡 Escaping corect la injectarea în JS/HTML

**Spec.** `Block/Loader.php` injectează `selectors` în JS cu doar `str_replace('"','\"')` (nu tratează `</script>`, `\`, newline). `Google/Block/Bod.php` concatenează `$key` (GTM id) direct în `src`-ul unui `<iframe>`.
*Criterii de acceptanță:*
- Valorile injectate în JS folosesc `json_encode`/`Escaper::escapeJs`.
- Valorile în HTML/atribute folosesc `Escaper::escapeHtmlAttr`/`escapeUrl`.
- Payload-uri de test cu `</script>` și ghilimele nu sparg pagina.

**Plan de implementare.**
1. Injectează `Magento\Framework\Escaper` în ambele block-uri.
2. Înlocuiește concatenările cu variante escapate (`selectors` → `json_encode`; GTM id → `escapeHtmlAttr`).
3. Teste cu payload-uri malițioase.

## SEC-09 🟡 Plafonare `limit`/`page` (anti-DoS)

**Spec.** În `Controller/Api/Orders.php` `limit` e doar castat la `int` și dat în `setPageSize()`; valori uriașe pot epuiza memoria.
*Criterii de acceptanță:* `limit` plafonat (ex. 1..250), `page` validat `>=1`; valori în afara intervalului sunt normalizate sau resping controlat.

**Plan de implementare.**
1. Adaugă clamp pentru `limit` și `page` în `Orders` (și oriunde se folosesc).
2. Documentează limitele în API docs.
3. Test unitar pentru margini.

## SEC-10 🟡 Mesaje de eroare fără reflectarea input-ului

**Spec.** `Model/Func.php` întoarce mesaje care reflectă valoarea trimisă (ex. cheia) și expun formatul de dată / ora serverului.
*Criterii de acceptanță:* mesaje generice, fără ecou al valorilor sensibile primite; detaliile rămân doar în log-ul intern.

**Plan de implementare.**
1. Rescrie mesajele din `isParamValid` să fie generice.
2. Mută detaliile în `LoggerInterface`.
3. Test: răspunsul de eroare nu conține input-ul.

## SEC-11 🔵 Documentare/configurare config Firebase

**Spec.** `Model/Config.php` conține config Firebase hardcodat. Cheile web Firebase nu sunt secrete prin design, deci risc informativ; totuși ar trebui documentat și, ideal, configurabil per instalare.
*Criterii de acceptanță:* comentariu/documentație clară că nu e secret; opțional, valori mutate în config admin.

**Plan de implementare.**
1. Documentează în cod/README natura non-secretă a cheii.
2. (Opțional) Mută în `system.xml` pentru override per store.

---

# B. Probleme de best practice

## BP-A 🟠 Eliminarea utilizării directe a `ObjectManager`

**Spec.** Aproape toate clasele folosesc `ObjectManager::getInstance()->get(...)`. Sparge DI, testabilitatea, e semnalat la `di:compile`.
*Criterii de acceptanță:* nicio utilizare directă de `ObjectManager` în codul modulului (excepții doar în contexte statice justificate, ex. `registration.php`); dependențe injectate prin constructor; `setup:di:compile` fără avertismente legate de modul.

**Plan de implementare.**
1. Inventariază toate aparițiile `ObjectManager` (grep).
2. Refactor clasă cu clasă: dependențe → constructor DI. Prioritizează Helper/Data, Config, Func, Api, FileSystem, controllerele.
3. Elimină pattern-urile `getHelp()`/`get...()` lazy-static.
4. `di:compile` + teste de regresie.

*Notă:* este cel mai mare efort din listă; e cuplat strâns cu BP-B.

## BP-B 🟠 Eliminarea stării statice mutabile partajate

**Spec.** `private static $ins`, `self::$configValues`, singletoni statici și metode `public static execute()` — stare care poate „scurge" între cereri/store-uri în procese long-running.
*Criterii de acceptanță:* fără stare statică mutabilă care traversează cereri; membri de instanță + DI; comportament corect la schimbarea store scope-ului.

**Plan de implementare.**
1. Converteste cache-urile statice în proprietăți de instanță.
2. Elimină `self::$cons`/singletoni improvizați.
3. Verifică izolarea pe store scope (test cu multiple store-uri).

## BP-C 🟠 Filesystem prin API-ul Magento

**Spec.** `FileSystem.php` folosește `fopen/fwrite/fread/unlink` direct, cu `filesize()` pe fișiere posibil inexistente (warnings) și scrie în `app/code/.../Storage/` (read-only la deploy).
*Criterii de acceptanță:* operațiile de fișier trec prin `Filesystem\Directory\WriteInterface`/`ReadInterface`; scriere în `var/`; fără warnings PHP. (Se implementează împreună cu SEC-06.)

**Plan de implementare.**
1. Rescrie `FileSystem.php` peste API-ul Magento (`DirectoryList::VAR_DIR`).
2. Tratare corectă a existenței fișierului (fără `filesize` pe inexistent).
3. Teste de citire/scriere/ștergere.

## BP-D 🟡 Convenții de denumire / PSR-1

**Spec.** Clasa `setEmail` (fișier `setEmail.php`) încalcă PSR-1 (StudlyCaps).
*Criterii de acceptanță:* clasa redenumită `SetEmail`; ruta/`di` actualizate; comportament identic.

**Plan de implementare.**
1. Redenumește clasa/fișierul; actualizează referințele (routes, layout, di).
2. Verifică ruta `mktr/api/setEmail` (păstrează compat sau documentează schimbarea).
3. Smoke test.

## BP-E 🟡 Logare în loc de excepții înghițite

**Spec.** `Model/Api.php` are `catch (\Exception $e) {}` gol — erorile de rețea dispar.
*Criterii de acceptanță:* excepțiile sunt logate prin `Psr\Log\LoggerInterface`; nu mai există `catch` gol.

**Plan de implementare.**
1. Injectează `LoggerInterface`.
2. Loghează context util (URL, cod status, mesaj) fără PII/secrete.
3. Test cu mock de logger.

## BP-F 🟡 Înlocuirea override-ului global al `Newsletter\Subscriber` cu plugin

**Spec.** `etc/di.xml` folosește `<preference for="Magento\Newsletter\Model\Subscriber" ...>` — schimbă comportamentul newsletter global, risc de conflict cu alte extensii.
*Criterii de acceptanță:* comportamentul (blocarea emailurilor de confirmare când `opt_in != 0`) se obține printr-un plugin (interceptor) pe metodele vizate, nu prin `preference`; nu mai există preference global.

**Plan de implementare.**
1. Creează un plugin (`around`/`after`) pe `sendConfirmationSuccessEmail`/`sendUnsubscriptionEmail`.
2. Elimină `preference` și clasa `Model/Newsletter.php` (sau redu-o la plugin).
3. Test funcțional pe fluxul de newsletter.

## BP-G 🔵 Curățare cod mort / TODO / dev

**Spec.** `ReadMe.md` cu cod comentat de scriere/ștergere fișiere; `Api.php` cu URL-uri comentate `eaxdev.ga`; multe `/** TODO: Magento 2 */`.
*Criterii de acceptanță:* cod comentat/mort eliminat; TODO-urile rezolvate sau transformate în issue-uri urmăribile; README curat.

**Plan de implementare.**
1. Grep după blocuri comentate, `TODO`, `eaxdev`.
2. Șterge codul mort; convertește TODO-urile rămase în tichete.
3. Curăță `ReadMe.md`.

---

# Ordine de execuție recomandată

**Sprint 1 — critice & quick wins de securitate:** SEC-01, SEC-03, SEC-04, SEC-02, SEC-08, SEC-09, SEC-10.
**Sprint 2 — securitate cu migrare:** SEC-05, SEC-06 (+ BP-C), SEC-07.
**Sprint 3 — refactor de fond:** BP-A + BP-B (cuplate), BP-F, BP-D, BP-E, SEC-11, BP-G.
**Închidere:** regression complet + release (task global în tracker).

Man-hours detaliate per task în `themarketer-m2-task-tracker.xlsx`.
