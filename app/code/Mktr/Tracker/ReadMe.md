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
- `limit`
- `mime-type=json` sau `mime-type=xml`
- `read`

Cerință de securitate:
- cheia nu se mai transmite în URL;
- utilizarea veche prin query param este considerată deprecated și va fi eliminată în viitor.

