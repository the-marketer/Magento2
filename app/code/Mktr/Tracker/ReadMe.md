# TheMarketer API

Autentificarea se face prin headerul `Authorization: Bearer <rest_key>`.

Exemple:

```bash
curl -H "Authorization: Bearer <rest_key>" "https://example.com/mktr/api/Feed?start_date=2000-01-01"
curl -H "Authorization: Bearer <rest_key>" "https://example.com/mktr/api/Orders?start_date=2000-01-01&page=2&limit=2"
curl -H "Authorization: Bearer <rest_key>" "https://example.com/mktr/api/Reviews?start_date=2000-01-01"
```

Parametri comuni:
- `start_date` / `end_date`
- `page`
- `limit` (1..250; valorile din afara intervalului sunt normalizate)
- `mime-type=json` sau `mime-type=xml`
- `read`

Cerință de securitate:
- cheia nu se mai transmite în URL;
- autentificarea prin query param nu este acceptată.

Firebase:
- configurația web Firebase inclusă în modul identifică proiectul public folosit pentru messaging; aceste valori nu sunt secrete API.
