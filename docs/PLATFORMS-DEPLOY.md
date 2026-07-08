# Deploy pe serverul `platforms`

Note rapide pentru Magento 2 din Docker, ca sa putem relua deploy-ul fara FileZilla.

## Pe unde intri

Containerul observat pentru Magento este:

- nume: `magento2`
- ID observat: `468c28d47c0d`
- Magento in container: `/var/www/html`
- folder montat pe host: `/data/magento2/data`

In mod normal intri asa:

```sh
ssh platforms
docker exec -it magento2 bash
cd /var/www/html
```

Daca numele containerului nu merge, poti folosi ID-ul observat:

```sh
docker exec -it 468c28d47c0d bash
cd /var/www/html
```

## Prima instalare a scriptului in container

Ruleaza asta in container, din `/var/www/html`:

```sh
mkdir -p scripts
curl -fsSL https://raw.githubusercontent.com/the-marketer/Magento2/refactoring/scripts/deploy-magento2.sh -o scripts/deploy-magento2.sh
chmod +x scripts/deploy-magento2.sh
./scripts/deploy-magento2.sh refactoring
```

Pentru alt branch:

```sh
./scripts/deploy-magento2.sh alt-branch
```

Merge si forma explicita:

```sh
./scripts/deploy-magento2.sh --branch alt-branch
```

## Ce inseamna "Git direct" aici

Scriptul ruleaza in `/var/www/html`. Daca Magento-ul de pe server nu are inca
`.git`, scriptul face initializarea Git acolo:

- `git init` in `/var/www/html`
- seteaza `origin` catre `https://github.com/the-marketer/Magento2.git`
- configureaza checkout restrans doar pe fisierele noastre
- face fetch pentru branch-ul primit ca argument
- reseteaza fisierele noastre la `origin/<branch>`
- ruleaza build-ul Magento

Checkout-ul Git este restrans la:

- `.gitignore`
- `mktr.sh`
- `scripts/`
- `app/code/Mktr/Tracker/`
- `app/code/Mktr/Google/`
- `pub/media/logo/`

Nu se face `git clean` pe tot Magento-ul. Instalarea Magento ramane in pace:

- `vendor`
- `var`
- `app/etc/env.php`
- `pub/media`, in afara de `pub/media/logo`
- baza de date
- fisierele generate Magento

Important: pentru fisierele noastre, deploy-ul este intentionat strict. Orice
modificare manuala facuta pe server in `app/code/Mktr/Tracker`,
`app/code/Mktr/Google`, `mktr.sh` sau `scripts/` va fi inlocuita cu ce exista pe
branch-ul ales.

## Fara build Magento

Daca vrei doar sa aduci codul din Git, fara compile/static deploy:

```sh
./scripts/deploy-magento2.sh refactoring --no-build
```

Aliasul vechi inca merge:

```sh
./scripts/deploy-magento2.sh refactoring --sync-only
```

## Build Magento rulat dupa Git update

Scriptul ruleaza in container:

```sh
php -d memory_limit=-1 -f bin/magento module:enable --clear-static-content Mktr_Tracker Mktr_Google
php -d memory_limit=-1 -f bin/magento setup:upgrade
php -d memory_limit=-1 -f bin/magento setup:di:compile
php -d memory_limit=-1 -f bin/magento cache:flush
php -d memory_limit=-1 -f bin/magento setup:static-content:deploy -f
php -d memory_limit=-1 -f bin/magento cache:clean
chmod -R 777 /var/www/html
php -d memory_limit=-1 -f bin/magento setup:db:status
```

## Elasticsearch / OpenSearch

Magento este configurat cu:

```text
catalog/search/engine = elasticsearch7
host = localhost
port = 9200
```

Dar in container nu ruleaza Elasticsearch pe `localhost:9200`.

Magento 2.4 cere Elasticsearch/OpenSearch pentru search-ul de produse si il
valideaza in `setup:upgrade`. Pentru deploy-ul modulului `Mktr_Tracker` nu avem
nevoie directa de el, dar Magento tot incearca sa il verifice.

De aceea scriptul face un bypass temporar doar pe durata `setup:upgrade`:

- modifica temporar validatorul Elasticsearch ca sa nu blocheze upgrade-ul
- ruleaza `setup:upgrade`
- restaureaza fisierul Magento original
- continua doar daca `setup:db:status` confirma `All modules are up to date`

Nu ramane patch permanent in Magento pentru Elasticsearch.

## Pasul special cu static assets

Dupa `setup:static-content:deploy`, Magento scrie versiunea in:

```text
/var/www/html/pub/static/deployed_version.txt
```

Pe serverul asta trebuie creat manual, sau automat prin script, folderul:

```text
/var/www/html/pub/static/version{{versiunea}}
```

Exemplu: daca `deployed_version.txt` contine `1783429744`, folderul devine:

```text
/var/www/html/pub/static/version1783429744
```

Scriptul face automat:

```sh
mkdir pub/static/version{{versiunea}}
cp -a pub/static/adminhtml pub/static/version{{versiunea}}/
cp -a pub/static/frontend pub/static/version{{versiunea}}/
```

## Variabile utile

Se pot suprascrie cand rulezi scriptul in container:

```sh
REPO_URL=https://github.com/the-marketer/Magento2.git \
MAGENTO_ROOT=/var/www/html \
./scripts/deploy-magento2.sh refactoring
```

Pentru repo privat prin SSH:

```sh
REPO_URL=git@github.com:the-marketer/Magento2.git ./scripts/deploy-magento2.sh refactoring
```

## Verificari dupa deploy

In container:

```sh
php -f bin/magento setup:db:status
php -f bin/magento cache:status
cat pub/static/deployed_version.txt
ls -la pub/static/version$(cat pub/static/deployed_version.txt)
```

Din server/container, cu host-ul corect:

```sh
curl -k -H "Host: magento2.dev.mktr.me" https://127.0.0.1/
```

Raspunsul asteptat pentru homepage este `HTTP 200`.

## De tinut minte

- Branch-ul trebuie sa fie deja impins pe GitHub inainte de deploy.
- Daca apare iar `Could not ping search engine`, inseamna ca Magento a ajuns la validarea Elasticsearch.
- Daca `setup:db:status` spune `All modules are up to date`, DB-ul este ok si deploy-ul poate continua.
- Varianta curata pe termen lung este sa pornim un container Elasticsearch/OpenSearch si sa configuram Magento spre el.
