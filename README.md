# AI Elementor Builder

WordPress plugin, amely Google Gemini segítségével generál és módosít Elementor oldalakat természetes nyelvű promptokból.

---

## Funkciók

- **Új oldal generálása** – Teljes Elementor struktúra létrehozása egyetlen prompt alapján
- **Iteratív módosítás** – Meglévő oldalak kontextus-tudatos szerkesztése
- **Globális stílusok** – Az Elementor Kit színei és tipográfiája automatikusan átadódik az AI-nak
- **REST API** – `/wp-json/ai-builder/v1/generate` végpont
- **Beépített editor panel** – Drag & drop kompatibilis, az Elementor szerkesztőbe injektált UI
- **Biztonság** – Nonce, capability és post-level jogosultság-ellenőrzés

---

## Gemini API kulcs megszerzése

1. Menj a [Google AI Studio](https://aistudio.google.com/app/apikey) oldalra (`aistudio.google.com/app/apikey`)
2. Jelentkezz be Google fiókoddal
3. Kattints a **„Create API key"** gombra
4. Válassz egy Google Cloud projektet (vagy hozz létre újat)
5. Másold ki a generált kulcsot – **csak egyszer látható**, mentsd el biztonságos helyre!
6. A kulcsot illeszd be a **Beállítások → AI Elementor Builder → API Kulcs** mezőbe

> **Ingyenes kvóta:** A `gemini-2.0-flash` modell ingyenes szinten is elérhető (napi korláttal). Részletek: [ai.google.dev/pricing](https://ai.google.dev/pricing)

---

## Telepítés

1. Töltsd fel a `ai-elementor-builder` mappát a `/wp-content/plugins/` könyvtárba
2. Aktiváld a plugint a WordPress admin felületen
3. Lépj a **Beállítások → AI Elementor Builder** menüpontra
4. Add meg a Gemini API kulcsot (lásd fent – [aistudio.google.com/app/apikey](https://aistudio.google.com/app/apikey))
5. Válassz modellt (ajánlott: `gemini-2.0-flash`)

---

## Követelmények

- WordPress 6.0+
- PHP 8.0+
- Elementor 3.x+
- Google Gemini API kulcs (ingyenes kvóta elérhető)
- cURL PHP extension

---

## Használat

### Editor panelből

1. Nyiss meg egy oldalt Elementor-ral szerkesztésre
2. A jobb alsó sarokban megjelenik az AI panel
3. Válassz módot (Auto / Új oldal / Módosítás)
4. Írd be a promptot, kattints a **Generálás** gombra
5. Az oldal automatikusan újratöltődik az új tartalommal

### REST API hívás programmatikusan

```bash
curl -X POST https://your-site.com/wp-json/ai-builder/v1/generate \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: <NONCE>" \
  --cookie "wordpress_logged_in_..." \
  -d '{
    "post_id": 123,
    "prompt": "Készíts egy modern hero szekciót CTA gombbal",
    "mode": "create"
  }'
```

**Paraméterek:**

| Paraméter | Típus    | Kötelező | Leírás                                   |
|-----------|----------|----------|------------------------------------------|
| post_id   | integer  | igen     | A szerkesztendő oldal ID-ja              |
| prompt    | string   | igen     | Természetes nyelvű utasítás (5–2000 char)|
| mode      | string   | nem      | `auto` \| `create` \| `modify` (def: auto) |

---

## Mappa struktúra

```
ai-elementor-builder/
├── ai-elementor-builder.php          # Fő plugin fájl + autoloader
├── uninstall.php                     # Eltávolítási script
├── README.md
├── assets/
│   └── js/
│       └── editor-panel.js           # Elementor editor UI
└── includes/
    ├── Plugin.php                    # Bootstrap (singleton)
    ├── Installer.php                 # Aktiválás/deaktiválás
    ├── Admin/
    │   └── SettingsPage.php          # Admin beállítások
    ├── Api/
    │   └── RestController.php        # REST végpont
    ├── Elementor/
    │   └── DataManager.php           # _elementor_data + Kit
    └── AI/
        ├── GeminiClient.php          # cURL Gemini kliens
        └── PromptBuilder.php         # System + user prompt
```

---

## Biztonsági ellenőrzések

A REST endpoint az alábbi rétegeket ellenőrzi:

1. **Authentikáció** – `is_user_logged_in()`
2. **Nonce** – Beépítve a WP REST API-ba (`X-WP-Nonce` header)
3. **Capability** – `current_user_can( 'edit_posts' )`
4. **Post-level jogosultság** – `current_user_can( 'edit_post', $post_id )`
5. **Input sanitálás** – Minden paraméter `sanitize_*` callback-en megy át
6. **Output validálás** – Az AI JSON-t struktúra-ellenőrzés után mentjük

---

## Architektúra-jegyzetek

### Miért nem használ Guzzle-t?

A WordPress core önmagában nem szállít Composer autoloader-t. Hogy a plugin függőség-mentes legyen, **natív cURL** hívást használ. Aki Guzzle-t szeretne, a `GeminiClient::chat()` metódus belsejét cserélheti le.

### JSON mentés Elementor-kompatibilis módon

Az Elementor a `_elementor_data` meta-t **slash-elt JSON string**-ként tárolja. Ezért a mentés:

```php
$json = wp_slash( wp_json_encode( $data ) );
update_post_meta( $post_id, '_elementor_data', $json );
\Elementor\Plugin::$instance->files_manager->clear_cache();
```

Ha ezt kihagyod (pl. csak `update_post_meta`-val mented), az Elementor **escape hibát** dob, és nem renderel.

### Prompt engineering trükkök

- **`responseMimeType: application/json`** – Gemini JSON módban garantálja a parse-olható JSON-t
- **Hőmérséklet 0.3** – Kevesebb kreativitás = stabilabb struktúra
- **System promptban explicit példák** – Heading, text, button widget JSON sablonok
- **Globális színek átadása** – Az AI így nem talál ki random hex kódokat
- **Iteratív módban a teljes JSON elküldése** – Az AI a _diff_-et nem mindig kezeli jól, de a teljes újraírást igen

### Token-takarékosság

Ha a meglévő JSON > 12 000 karakter, a `PromptBuilder::maybe_truncate_json()` levágja a közepét, megőrizve a head + tail részeket. Erre a GPT általában elboldogul, mert a struktúra elejéből és végéből leolvasható a séma.

---

---

## Hibaelhárítás

| Hiba                          | Megoldás                                          |
|-------------------------------|---------------------------------------------------|
| `aie_no_api_key`              | Add meg a Gemini API kulcsot a beállításokban     |
| `aie_json_parse_error`        | Az AI érvénytelen JSON-t adott – próbáld újra    |
| `aie_curl_error`              | Tűzfal/SSL probléma – ellenőrizd a `generativelanguage.googleapis.com` elérhetőségét |
| Az oldal nem frissül          | `Elementor → Eszközök → Cache regenerálása`       |
| `403 forbidden_post`          | A felhasználónak nincs joga az oldalhoz           |

---

## Licenc

GPL-2.0+

---

## Roadmap

- [ ] Anthropic Claude támogatás
- [ ] Több prompt template (landing page, blog post, portfolio…)
- [ ] Undo/redo előzmények tárolása
- [ ] Streaming válasz (Server-Sent Events)
- [ ] Multi-language prompt detection
- [ ] Képgenerálás az image widgetekhez (Gemini Imagen)
