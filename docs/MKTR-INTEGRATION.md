# TheMarketer (Mktr) Integration - Overview

Acest document explică cum sunt organizate și cum funcționează modulele `Mktr_Tracker` și `Mktr_Google` din proiect.

## Scop
- `Mktr_Tracker`: sincronizează date (feed produse, recenzii, subscriberi, export comenzi) către serviciul TheMarketer; rulează cron-uri și oferă controllere API interne.
- `Mktr_Google`: inserează Google Tag Manager (GTM) în paginile frontend (script în `head` + `noscript` în `body`).

## Locație fișiere cheie
- Module: `app/code/Mktr/Tracker` și `app/code/Mktr/Google`.
- Config module/registration: 
  - [Mktr Tracker module.xml](app/code/Mktr/Tracker/etc/module.xml#L1)
  - [Mktr Google module.xml](app/code/Mktr/Google/etc/module.xml#L1)
  - [Mktr Tracker registration.php](app/code/Mktr/Tracker/registration.php#L1)
  - [Mktr Google registration.php](app/code/Mktr/Google/registration.php#L1)

## Unde se configurează
- `Mktr_Tracker` folosește `Stores -> Configuration -> The Marketer` (vezi `etc/adminhtml/system.xml`).
- `Mktr_Google` citește din `mktr_google/google/*` (ex: `status` și `tracking` – GTM ID).

## Flux principal - `Mktr_Tracker`
1. Helper/Factory: `Mktr\Tracker\Helper\Data` folosește `ObjectManager` pentru a obține multe servicii (DB, store manager, modele).
2. Cron-uri: `Mktr\Tracker\Model\Cron` iterează magazinele și rulează joburi configurate (feed, review, subscribe).
3. Generare date: modelele din `Model/Pages/*` construiesc feed-uri (XML/CSV) și le scriu folosind `Model/FileSystem`.
4. Comunicare: `Mktr\Tracker\Model\Api::send()` / `REST()` face POST/GET către API-ul TheMarketer, atașând `rest_key` și `customer_id` din configurație.
5. Controllere: `Controller/Api/*` expun endpoint-uri interne (ex: `Orders.php`, `Reviews.php`, `LoadEvents.php`) pentru flows specifice și webhook handling.

### Observații tehnice Tracker
- `Api::REST` folosește cURL nativ; se poate înlocui cu `\Magento\Framework\HTTP\Client\Curl` pentru consistență și testabilitate.
- Sunt folosite apeluri directe la `ObjectManager` în multe locuri — anti-pattern; recomand refactorizare prin dependency injection în constructor.
- Configurațiile cron-urilor și intervalele sunt definite în `system.xml` (per store).

## Flux principal - `Mktr_Google`
- Layout: `view/frontend/layout/default.xml` adaugă două block-uri:
  - `Mktr\Google\Block\Top` -> inserează scriptul GTM în `head`.
  - `Mktr\Google\Block\Bod` -> inserează `noscript` iframe imediat după începutul `body`.
- Ambele verifică `mktr_google/google/status` înainte de a returna snippet-ul și citesc GTM ID din `mktr_google/google/tracking`.

## Cum preiei / verifici funcționarea local
1. Verifică modulele active:
```bash
bin/magento module:status
```
2. Activează/Configurează în Admin: `Stores -> Configuration -> The Marketer` (setează `tracking_key`, `rest_key`, `customer_id`, și activează cronurile dacă e cazul).
3. Flush cache:
```bash
bin/magento cache:flush
```
4. Rulează cron manual (dacă ai nevoie):
```bash
bin/magento cron:run
```
5. Pentru GTM verifică sursa paginii: trebuie să apară scriptul în `<head>` și iframe-ul `noscript` imediat după `<body>`.

## Debug & logs
- Uitați-vă în `var/log` pentru erori PHP/Magento.
- Poți instrumenta `Model/Api::REST` să mai scrie răspunsuri în log pentru debugging (sau folosește Xdebug pentru debugging pas-cu-pas).

## Recomandări pentru preluare (takeover)
- Refactor: înlocuiește `ObjectManager::getInstance()` cu DI în constructor pentru clasele principale (`Helper`, `Model`, `Block`, `Controller`).
- Înlocuiește cURL nativ cu `\Magento\Framework\HTTP\Client\Curl` sau un client PSR pentru testabilitate.
- Adaugă unit/integration tests pentru generarea feed-ului și pentru `Api::REST` (mocks pentru http client).
- Documentează orice custom endpoint din `Controller/Api` (acest doc face overview; pentru detalii vezi fișierele controller).

## Pași sugerați inițiali pentru preluare
1. Randament: rulează `module:status` și verifică setările din Admin.
2. Local: activează logging extins temporar în `Model/Api::REST` pentru a vedea request/response.
3. Testează cron-uri și verifică `var` pentru fișierele generate (feed/exports).
4. Plan refactor: listează cele mai critice 3-5 clase cu `ObjectManager` și prioritizează DI.

---
Document creat rapid pentru onboarding. Dacă vrei, pot extinde cu:
- descrieri pas-cu-pas pentru fiecare controller (`Controller/Api/Orders.php`, etc.)
- un diagramă a fluxului (mermaid)
- exemplu de test unit pentru `Model/Api::REST`

## Controllere - Detaliat
Toate endpoint-urile publice din acest modul rulează sub frontName-ul `mktr` (vezi `etc/frontend/routes.xml`). Path-urile controllerelor din `Controller/Api` se accesează astfel:

- `https://<magento-host>/mktr/api/orders` → `Controller/Api/Orders.php`
- `https://<magento-host>/mktr/api/feed` → `Controller/Api/Feed.php`
- `https://<magento-host>/mktr/api/reviews` → `Controller/Api/Reviews.php`
- `https://<magento-host>/mktr/api/brands` → `Controller/Api/Brands.php`
- `https://<magento-host>/mktr/api/categories` → `Controller/Api/Category.php`
- `https://<magento-host>/mktr/api/subscribes` → `Controller/Api/Subscribes.php`
- `https://<magento-host>/mktr/api/saveorder` → `Controller/Api/SaveOrder.php`
- `https://<magento-host>/mktr/api/setemail` → `Controller/Api/setEmail.php`
- `https://<magento-host>/mktr/api/loadevents` → `Controller/Api/LoadEvents.php`
- `https://<magento-host>/mktr/api/codegenerator` → `Controller/Api/CodeGenerator.php`

Fiecare controller urmează un pattern comun:
- validează parametrii (de obicei necesită `key`)
- apelează `Helper\Data` și `Helper\Data->getFunc` pentru utilitare (verificare parametri, formatare, read/write)
- fie întoarce date proaspete (metodă `freshData` sau `getOrderInfo`), fie folosește `getFunc->readOrWrite()` pentru a citi/crea fișiere cache și a returna rezultatul.

Mai jos, un sumar scurt pentru fiecare controller (ce primește, ce returnează, note utile):

- `Orders.php` (export /mktr/api/orders)
  - Parametri importanți: `key` (obligatoriu), `start_date` (obligatoriu), `end_date` (opțional), `page`, `limit`, `customerId`.
  - Ce face: construiește lista de comenzi între date, pentru fiecare comandă adaugă informații detașate (produse, prețuri, imagini, categorie, brand). Folosește `getOrderInfo()` și modelul `order` collection. Returnează JSON sau alt mime-type stabilit prin `mime-type` param.
  - Notă: folosește `getHelp()->getFunc->readOrWrite()` — mecanismul de caching/împărțire în pagini este implementat acolo.

- `Feed.php` (export produse /mktr/api/feed)
  - Parametri: `key` (obligatoriu) și alții gestionați de `getFunc->readOrWrite`.
  - Ce face: returnează lista de produse (folosește `Model/Pages/Feed::freshData()`), formatul și caching-ul sunt gestionate de helper.

- `Reviews.php` (export recenzii /mktr/api/reviews)
  - Parametri: `key`, `start_date`.
  - Ce face: apelează `getPagesReviews->execute()` și întoarce recenziile într-un shape JSON.

- `Brands.php` (lista branduri /mktr/api/brands)
  - Parametri: `key`.
  - Ce face: construiește lista de valori pentru atributele folosite ca brand (din `system.xml` configurat) și returnează nume, id și URL de căutare.

- `Category.php` (lista categorii /mktr/api/categories)
  - Parametri: `key`, `rmExt` (dacă trebuie eliminată extensia `.html` din URL-uri).
  - Ce face: parcurge categoriile de top și construiește obiecte cu `name`, `url`, `hierarchy`, `image_url`.

- `Subscribes.php` (unsubscribe checks /mktr/api/subscribes)
  - Parametri: `key`, `date_from`, `date_to`.
  - Ce face: returnează lista de unsubscribes (folosește `Model/Pages/Subscribes`).

- `SaveOrder.php` (sync la salvari de comandă /mktr/api/saveorder)
  - Endpoints utilizat de frontend JS (ex: `SaveOrder` observer) pentru a trimite comanda la API TheMarketer.
  - Ce face: citește datele stocate în sesiune (`saveOrder`), trimite apelul `save_order` prin `Model/Api`, și, dacă răspunsul e 200, curăță sesiunea.

- `setEmail.php` (sincronizare subscriber /mktr/api/setemail)
  - Ce face: citește email-ul din sesiune, verifică dacă este abonat la newsletter iar dacă da trimite `add_subscriber` către API; rezultatul este servit ca JS (console.log) pentru front-end.

- `LoadEvents.php` (încărcare script evenimente /mktr/api/loadevents)
  - Ce face: citește evenimentele stocate în sesiune, le transformă în apeluri JS (`window.mktr.eventPush(...)`) și returnează un `Result\raw` cu conținut JavaScript. Folosit în frontend pentru a executa evenimente adunate server-side.

- `CodeGenerator.php`
  - Folosit pentru generare de coduri/chei (vezi fișierul pentru detalii implementative). Poate fi folosit intern pentru debugging sau generare de chei.

## Alte componente importante (pe scurt)
- `Helper/Data` (`Mktr\\Tracker\\Helper\\Data`): centru de acces la servicii și utilitare (`getFunc`, `getConfig`, `getApi`, `getFileSystem`). Majoritatea controllerelor și modelelor apelează helperul pentru a obține serviciile.
- `Model/Api`: comunică cu API-ul TheMarketer (`send`, `REST`). Atașează `rest_key` și `customer_id` din configurație.
- `Model/Cron`: controlează cron-urile (`feed`, `reviews`, `subscribes`) per store.
- `Model/Pages/*`: `Feed`, `Reviews`, `Subscribes` — conțin logica de a construi datele exportabile.
- `Observer/Events`: capturează evenimente Magento (addToCart, saveOrder, register, etc.) și pune date în sesiune sau pregătește payload-uri pentru trimitere.

## Sugestii de documentare suplimentară
- Pot genera exemplu de request (curl) pentru fiecare endpoint cu parametrii minim necesari.
- Pot adăuga o diagramă `mermaid` care arată cum fluxul trece de la Observer → Session → LoadEvents/SaveOrder → Api.

