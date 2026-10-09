# Developer guide — Supertext Translation for Magento

How the module works, how to run it locally, how it's tested, deployed and released.

## Architecture

Magento 2 module `Supertext_Translation` (PHP 8.2+, Magento Open Source / Adobe Commerce 2.4.7+, Mage-OS). The repository root is the module: Composer installs it from GitHub as `supertext/magento-supertext-translation`, a hand install copies it to `app/code/Supertext/Translation`.

```
├── registration.php, composer.json (version), etc/module.xml
├── Api/                                  no Magento classes (unit-tested on their own)
│   ├── SupertextClient.php               Supertext AI file translation API v1
│   ├── HtmlDocument.php                  fields ⇄ one HTML document with data-st-id elements
│   ├── CurlTransport.php                 HTTP via cURL (or streams), honours HTTPS_PROXY
│   └── SupertextException.php
├── Model/
│   ├── Config.php                        settings, env variables, store views and their languages, version
│   ├── Translation/
│   │   ├── EntityTypes.php               what is translated: product, category, cms_page, cms_block
│   │   ├── FieldPlanner.php              which fields to send, what to write back (no Magento classes)
│   │   ├── EavTranslator.php             products, categories: store-view values, URL key, rewrites
│   │   ├── CmsTranslator.php             pages, blocks: copies per store view + supertext_translation_link
│   │   └── Translator.php                entry point for the admin and the console command
│   └── Config/Backend|Source/            API key (encrypted), language code, option lists
├── Controller/Adminhtml/Translate/       Index (the page), Mass (grid mass action → page), Run (JSON)
├── Controller/Adminhtml/System/Test.php  "Test connection"
├── Block/Adminhtml/                      translate page, edit-page buttons, config widgets
├── Console/Command/TranslateCommand.php  bin/magento supertext:translate
├── etc/                                  acl, routes, system.xml, config.xml, db_schema.xml, di.xml
├── view/adminhtml/
│   ├── ui_component/                     mass actions (product, cms_page, cms_block listings), form buttons
│   ├── layout/supertext_translate_index.xml
│   └── templates/                        translate.phtml (page + script), system/test-button.phtml
├── i18n/<locale>.csv                      German, French, Italian admin phrases (English is the source)
├── tools/sync-translations.php           writes the regional copies (de_CH, fr_CH, it_CH, …)
└── Test/Unit/                            PHPUnit, runs without Magento (Test/bootstrap.php)
```

Entry points:

| Where | How |
| --- | --- |
| *Catalog → Products*, *Content → Pages*, *Content → Blocks* | `view/adminhtml/ui_component/*_listing.xml` adds the mass action `supertext/translate/mass/type/<type>`. `Mass` reads the selection with Magento's `Ui\Component\MassAction\Filter` (selected, excluded and filters) and redirects to `supertext/translate/index/type/<type>/ids/1,2` (100 items at most). |
| Edit pages (product, category, page, block) | `*_form.xml` adds a button (`Block\Adminhtml\Button\*`) that opens the translate page for the saved item. Categories have no grid, only this button. |
| Command line | `supertext:translate <product|category|cms_page|cms_block> <ids…> [--store=<code or id>]… [--overwrite]` |

The translate page (`Block\Adminhtml\Translate` + `translate.phtml`) shows the items and, per store view, a state from `FieldPlanner::state()`. Its script then posts to `Run` once per item and store view, one after the other (Supertext rate-limits per second), with `form_key`, `type`, `id`, `store`, `overwrite`. `Run` checks the ACL resource of the type (`Magento_Catalog::products`, `Magento_Catalog::categories`, `Magento_Cms::save`, `Magento_Cms::block`) on top of `Supertext_Translation::translate`.

### Languages

The source is always the **default values** (store id 0), in the language of the default locale (`general/locale/code` at default scope; only the primary subtag is sent, e.g. `en`). Targets are the store views: `supertext/language/code` (store scope) if set, else the store view's `general/locale/code` as a tag (`de_CH` → `de-CH`). Store views whose language equals the source's are listed but not ticked, and the console command skips them without `--store`. Tone: `supertext/language/tone` (store scope).

### What is translated

From `Model/Translation/EntityTypes.php` (keep `docs/USER_GUIDE.md` → *What is translated* in sync):

| Type | Kind | Fields (rich text in bold) | URL |
| --- | --- | --- | --- |
| `product` | EAV | `name`, **`short_description`**, **`description`**, `meta_title`, `meta_keyword`, `meta_description` | `url_key` from `name` |
| `category` | EAV | `name`, **`description`**, `meta_title`, `meta_keywords`, `meta_description` | `url_key` (and `url_path`) from `name` |
| `cms_page` | CMS copy | `title`, `content_heading`, **`content`**, `meta_title`, `meta_keywords`, `meta_description` | same `identifier` |
| `cms_block` | CMS copy | `title`, **`content`** | same `identifier` |

**EAV (products, categories)** — `EavTranslator`:

- Reads values with the resource's `getAttributeRawValue($id, $codes, $storeId)`, which falls back to the default value: a store view without its own value returns the default, so it counts as untranslated (`FieldPlanner`: translated = has text that differs from the source, ignoring whitespace).
- Writes each translated attribute with `$resource->saveAttribute($object, $code)` on the object loaded with the store id — the same per-store write Magento uses, without the "save everything in store scope" side effect of a full `save()`.
- URL key: when the name was translated and the store view's URL key is still the default one (or with overwrite), sets `url_key` via `Product\Url::formatUrlKey()` (categories also `url_path` via `CategoryUrlPathGenerator`), then regenerates the store view's rewrites (`ProductUrlRewriteGenerator` / `CategoryUrlRewriteGenerator` → `UrlPersistInterface::replace()`; the old URL becomes a 301). If the URL already exists in the store view, the old key is restored and a note is returned.
- Then reindexes the product in `catalogsearch_fulltext` (unless the indexer is scheduled) and cleans the item's cache tags (`clean_cache_by_tags`).
- Children of a category keep their stored `url_path` until they are translated themselves: translate categories from the top down.

**CMS (pages, blocks)** — `CmsTranslator`:

- The translation of an original for a store view is found in `supertext_translation_link` (`entity_type`, `source_id`, `store_id` → `target_id`), else adopted: a page/block with the same identifier assigned only to that store view.
- New copy: all data of the original except ids, dates and stores, then the translated fields; `store_id` = the target store view; `is_active` = the original's, or 0 with *Status of new translations: Disabled*.
- Pages only: Magento allows one page per URL and store view (URL rewrites, `PageRepository` check), so the target store view is first removed from the original's stores (`[0]` becomes all other store views). If saving the copy fails, the original's stores are restored. Blocks don't need this: a store-view block wins over an *All Store Views* block with the same identifier.
- Adobe Commerce's `row_id` (content staging) is handled when looking up copies; saving goes through the repositories.

Each item × store view is one Supertext document: one `<div data-st-id="N">` per field, a whole rich-text field in one element. `HtmlDocument` wraps Magento directives in text (`{{widget …}}`, `{{config …}}`) in `<span translate="no" data-st-keep>` and removes the wrapper afterwards; directives in attributes (`src="{{media url=…}}"`) are not touched. The translated document is cut apart as text (tracking nested `<div>`s), not re-serialised through `DOMDocument`, which would URL-encode such attributes and rewrite Page Builder's attribute quoting.

### Interface strings

Every phrase goes through Magento's own mechanism: `__()` in PHP and templates (including the texts the translate page's script uses, passed as `window.supertextTexts`), `translate="label comment"` in `system.xml`, `translate="title"` in `acl.xml`, `<label translate="true">` in UI components. Translations live in `i18n/<locale>.csv` (`"English","translation"`, placeholders `%1`, `%2`). Magento reads the file of the user's exact interface locale and doesn't fall back from de_CH to de_DE, so:

- Edit `de_DE.csv`, `fr_FR.csv` and `it_IT.csv` by hand (formal address: Sie, vous, Lei; Magento's own terms: *Store View* / *vue magasin* / *visualizzazione negozio*, *Stores → Konfiguration*, *Magasins → Configuration*, *Negozi → Configurazione*; never translate "Supertext", placeholders, tags or URLs), then run `php tools/sync-translations.php` to write de_AT, de_CH (ß → ss), fr_BE, fr_CA, fr_CH and it_CH.
- A new or changed phrase goes into all three files in the same commit. `Test/Unit/TranslationFilesTest.php` collects the phrases like `bin/magento i18n:collect-phrases` (`Test/ModuleStrings.php`) and fails if one is missing, unused, has different placeholders, or a regional copy is out of date.
- `SupertextException` keeps its English template and `%1` parameters (`template()`, `parameters()`) apart from Supertext's own detail (`detail()`); `Run` and `Test` translate it with `__()`, so `Api/` and the models throw English and stay testable. The console command prints English.

## Supertext API protocol

AI file translation API v1, same as the WordPress, PrestaShop and other Supertext plugins (`Api/SupertextClient.php`):

1. `POST {base}/translate/ai/file` — multipart: `file` (`content.html`, part `Content-Type: text/html` exactly, or the API answers 415), `target_lang` (e.g. `de-CH`), `source_lang` (primary subtag only, e.g. `en`; a full tag is rejected with `INVALID_LANGUAGE_PAIR`), optional `politeness` (`more` formal, `less` informal). Answers `{"file_id": "…"}`.
2. `GET {base}/translate/ai/file/{id}/status` every 2 s until `done` (or `error`, `limit_exceeded`, `deleted`), up to the timeout (default 180 s).
3. `GET {base}/translate/ai/file/{id}/translation` — the translated HTML.
4. `DELETE {base}/translate/ai/file/{id}` — always, also after errors.

- Base URLs: live `https://api.supertext.com/v1/`, staging `https://api.staging.supertext.com/v1/`, testing `https://api.testing.supertext.com/v1/`, or a custom one (setting, or `SUPERTEXT_API_ENDPOINT`).
- Header `Authorization: Supertext-Auth-Key <key>` (the name must be `Authorization`). A pasted `Supertext-Auth-Key ` prefix is stripped, so exactly one is sent.
- HTTP 429 (`RATE_LIMIT_EXCEEDED`, per second per key) is retried up to 4 times, after `Retry-After` or 1/2/4/8 s with jitter.
- *Test connection* calls `GET {base}/features` (free).
- Documents stay below 900,000 characters (API limit 1,000,000).

## Local development

Mage-OS installs without Adobe Marketplace keys (Magento Open Source from `repo.magento.com` needs them). With Docker for MySQL and Elasticsearch:

```bash
docker run -d --name mysql -p 3306:3306 -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=magento mysql:8.4
docker run -d --name es -p 9200:9200 -e discovery.type=single-node -e xpack.security.enabled=false elasticsearch:8.17.0
composer create-project --repository-url=https://repo.mage-os.org/ mage-os/project-community-edition:3.5.0 magento
cd magento
bin/magento setup:install --base-url=http://127.0.0.1:8082/ --db-host=127.0.0.1 --db-name=magento --db-user=root \
  --db-password=root --search-engine=elasticsearch8 --elasticsearch-host=127.0.0.1 --admin-user=admin \
  --admin-password=… --admin-email=… --admin-firstname=Local --admin-lastname=Admin --language=en_US --use-rewrites=1
mkdir -p app/code/Supertext && cp -R /path/to/this/repo app/code/Supertext/Translation
bin/magento module:enable Supertext_Translation && bin/magento setup:upgrade
bin/magento module:disable Magento_TwoFactorAuth && bin/magento setup:upgrade      # local only
DEMO_ADMIN_EMAIL=… DEMO_ADMIN_PASSWORD=… DEMO_EDITOR_EMAIL=… DEMO_EDITOR_PASSWORD=… php /path/to/this/repo/demo/setup.php "$PWD"
PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:8082 -t pub/ phpserver/router.php
```

Admin: http://127.0.0.1:8082/admin/. Copy the module rather than symlinking it: Magento refuses templates outside its root. After changing PHP files, flush the cache; after changing `etc/*.xml` or constructors, also run `setup:upgrade` (or delete `generated/code/Supertext`). Static admin files are generated on demand; with the PHP server, deploy them once (`bin/magento setup:static-content:deploy -f --area adminhtml en_US`) to keep pages fast.

To translate without a Supertext key, run the stand-in API (`node tests/docs/stand-in.mjs`, any key) and set `SUPERTEXT_API_ENDPOINT=http://127.0.0.1:8765/v1/` for the PHP server and the console.

## Tests

```bash
php phpunit.phar        # PHPUnit 10 (phar): API client, HTML document, field planner, i18n files — no Magento needed
./build.sh              # dist/magento-supertext-translation-<version>.zip
```

The module's `composer.json` requires `magento/*` packages, which aren't on Packagist, so the unit tests don't use `composer install`: `Test/bootstrap.php` autoloads the Magento-free classes.

CI (`.github/workflows/ci.yml`):

- **unit** (PHP 8.2, 8.3, 8.4): lint (`.php`, `.phtml`), PHPUnit, XML well-formedness, `sh -n demo/entrypoint.sh`, `./build.sh`.
- **phpstan**: PHPStan on the module's code with Mage-OS's classes (see *Code quality and security checks*).
- **magento**: downloads Mage-OS, installs it with MySQL 8.4 and Elasticsearch 8, adds the module in `app/code`, runs `demo/setup.php` twice (the second run must change nothing, and no password may appear in the log), translates the sample product, category, page and block with `supertext:translate` against the stand-in and checks the store values, URL key and rewrite, page copy with Page Builder markup, block directives, link table and accounts; a second run must skip everything.

## Demo (Railway)

`demo/` is a Mage-OS 3.5 shop with English sample content and store views for German (Switzerland), French (Switzerland) and Italian (Switzerland), plus this module. The Railway service *Magento* (project *supertext-cms-demos-php*) builds `demo/Dockerfile` with the repository root as context, from `main`. Railway no longer reads `railway.json`, so the Dockerfile path, healthcheck (`/health`, a static file) and restart policy are set on the service itself; `railway.json` documents the same values.

The image downloads Mage-OS with Composer at build time (no keys needed), copies the module to `app/code`, enables all modules except `Magento_TwoFactorAuth` (demo convenience: Supertext staff log in with the `DEMO_*` accounts only) and runs Apache with `pub/` as document root. The container keeps **no files** between deploys:

- The shop is in MySQL: `DATABASE_URL` (`mysql://…`, the shared Railway MySQL 8.4 service, as root so the demo can create its database) and its own database `MAGENTO_DB_NAME` (default `magento`).
- `app/etc/env.php` (database access and Magento's encryption key) is stored in that database (`supertext_demo_state`, `demo/state.php`) and restored on every start.
- The search index lives in the shared Elasticsearch 8 service (`ELASTICSEARCH_HOST`, index prefix `supertext_magento_demo`); it has no volume either, so every start reindexes.
- On the **first start** (empty database) `demo/entrypoint.sh` runs `setup:install` with a throwaway admin (random name and `@supertext-demo.invalid` address and password, never shown). The `DEMO_*` accounts replace it: `demo/setup.php` deletes it as soon as the `DEMO_ADMIN` account exists.
- On **every start** the entrypoint restores `env.php`, runs `setup:upgrade` (module updates), sets the base URLs to the public domain (`MAGENTO_DOMAIN`, else `RAILWAY_PUBLIC_DOMAIN`; HTTPS except for localhost) and the search engine, turns off the admin captcha, runs `demo/setup.php` and reindexes. `demo/setup.php` adds the store views (`de_ch`, `fr_ch`, `it_ch` with their locales), the sample content (`demo/sample-content.json`, with a Page Builder page and a block with directives), the role *Supertext editor* and the accounts, if missing. It never changes existing content, translations or accounts.
- Uploaded images and generated static files are lost on redeploy (the sample products have no images). Every push to `main` deploys the demo; if one doesn't, check Railway's GitHub access and reconnect the service's source once.

Variables (Railway service variables; template in `demo/.env.example`):

| Variable | Purpose |
| --- | --- |
| `DATABASE_URL` | MySQL server, `mysql://user:password@host:port/any`. On Railway: `mysql://root:${{MySQL-8.MYSQL_ROOT_PASSWORD}}@${{MySQL-8.RAILWAY_PRIVATE_DOMAIN}}:3306/mysql` |
| `MAGENTO_DB_NAME` | The demo's database on it (default `magento`) |
| `MAGENTO_DOMAIN` | Public host name; default `RAILWAY_PUBLIC_DOMAIN` |
| `ELASTICSEARCH_HOST`, `ELASTICSEARCH_PORT`, `ELASTICSEARCH_INDEX_PREFIX` | Search engine; default `elasticsearch.railway.internal`, `9200`, `supertext_magento_demo` |
| `SUPERTEXT_API_KEY` | Supertext API key. No Supertext account yet? Create one at https://www.supertext.com/person/en/account/signin. Generate your API key at https://www.supertext.com/en/integrations/api (requires the Admin role). |
| `SUPERTEXT_API_ENDPOINT` | Optional: another API, e.g. the stand-in |
| `DEMO_ADMIN_EMAIL`, `DEMO_ADMIN_PASSWORD` | Admin in the *Administrators* role. `MAGENTO_ADMIN_EMAIL` / `MAGENTO_ADMIN_PASSWORD` are read as fallbacks. |
| `DEMO_EDITOR_EMAIL`, `DEMO_EDITOR_PASSWORD` | Editor for tests and screenshots. Magento has no editor role out of the box, so the demo creates the role **Supertext editor**: dashboard, products, categories, pages, blocks, media gallery and *Translate with Supertext* (no configuration, sales or system). |

- Accounts log in with their **email address as user name**. They are created only if that user name or email doesn't exist yet; existing accounts are never modified (no password resets).
- A password that fails Magento's rules (at least 12 characters with letters and digits in Mage-OS 3) skips that account with a warning in the log naming the rule; the demo still starts. The log names variables, never passwords.

Build and run it locally:

```bash
docker build -f demo/Dockerfile -t supertext-magento-demo .
docker run --rm -p 8091:80 -e MAGENTO_DOMAIN=localhost:8091 \
  -e DATABASE_URL=mysql://root:root@host.docker.internal:3306/mysql -e ELASTICSEARCH_HOST=host.docker.internal \
  -e DEMO_ADMIN_EMAIL=… -e DEMO_ADMIN_PASSWORD=… -e DEMO_EDITOR_EMAIL=… -e DEMO_EDITOR_PASSWORD=… \
  supertext-magento-demo
# shop http://localhost:8091/  admin http://localhost:8091/admin/
```

The first start takes a few minutes (Magento's installer and the first reindex); later starts about a minute.

## Docs screenshots

`tests/docs/screenshots.mjs` regenerates `docs/images/` with Playwright from a freshly started demo (empty database) whose module talks to `tests/docs/stand-in.mjs`. The stand-in answers like the Supertext API and returns real German, French and Italian for the sample content (`tests/docs/samples.json`, keyed by the HTML the module sends, directives protected); the settings screenshots show the live API, because the endpoint comes from `SUPERTEXT_API_ENDPOINT`. The script works with Magento's secret keys in admin URLs: it opens pages from the admin menu's links and adds `/store/<id>/` before the key for a store view's scope.

```bash
cd tests/docs && npm install && npx playwright install chromium
node stand-in.mjs &
# a fresh demo running with SUPERTEXT_API_ENDPOINT=http://127.0.0.1:8765/v1/,
# no SUPERTEXT_API_KEY (the script enters a key) and DEMO_* set, on port 8091:
BASE_URL=http://localhost:8091/admin DEMO_ADMIN_EMAIL=… DEMO_ADMIN_PASSWORD=… \
  DEMO_EDITOR_EMAIL=… DEMO_EDITOR_PASSWORD=… node screenshots.mjs
```

`ONLY=admin` or `ONLY=editor` runs one part. Run it in the same commit as any UI change the images show.

## Code quality and security checks

Before starting work in this repo, look at its open findings: code scanning alerts, secret scanning alerts, Dependabot PRs and the "Broken links in the docs" issue.

- **Checks** (`.github/workflows/checks.yml`): actionlint and zizmor lint the workflows on every push and pull request; dependency review fails a pull request that adds a package with a known vulnerability (moderate or worse). Third-party actions are pinned to commit SHAs (Dependabot keeps them current).
- **Links** (`.github/workflows/links.yml`): lychee checks the links in all Markdown files weekly and when docs change on `main`. Broken links open (or update) the issue "Broken links in the docs"; links that can't work from CI go in `.lycheeignore` (one regex per line).
- **PHPStan** (job **phpstan** in `ci.yml`, configuration `phpstan.neon`): level 5 on `Api/`, `Block/`, `Console/`, `Controller/`, `Model/` and `registration.php` (not the templates, `demo/` or the tests). PHPStan has to know Magento's classes, so it runs from a Mage-OS root (no database needed) with [bitexpert/phpstan-magento](https://github.com/bitExpert/phpstan-magento), which also understands the generated factories and proxies. Locally:

  ```bash
  composer create-project --no-dev --repository-url=https://repo.mage-os.org/ mage-os/project-community-edition:3.5.0 /tmp/mageos
  cd /tmp/mageos
  composer config allow-plugins.phpstan/extension-installer true
  composer require --dev --with-all-dependencies phpstan/phpstan:^2.1 phpstan/extension-installer bitexpert/phpstan-magento
  vendor/bin/phpstan analyse -c /path/to/magento-supertext-translation/phpstan.neon --memory-limit=2G
  ```

  Known findings that aren't fixed yet go in `phpstan-baseline.neon` (add `--generate-baseline /path/to/magento-supertext-translation/phpstan-baseline.neon`); fix new findings instead of adding them.
- **GitHub settings** (set by Remy's setup script, not in the repo): secret scanning with push protection (a push containing a known token format is rejected; findings under *Security → Secret scanning*) and CodeQL default setup (findings under *Security → Code scanning* and as pull request comments). CodeQL doesn't cover PHP, which is why this repo runs PHPStan.

## Releasing

Releases are published by `.github/workflows/release.yml` when the version is officially bumped; nobody tags or creates releases by hand.

1. Check that the unit tests, PHPStan (CI job **phpstan**) and `./build.sh` pass.
2. Move the *Unreleased* entries in `CHANGELOG.md` under a new `## X.Y.Z — YYYY-MM-DD` section, and keep an empty *Unreleased* above it.
3. Set the same version in `composer.json` (`version`). Magento reads it through `PackageInfo`: the configuration page shows it and links it to the GitHub release, and the console command prints it.
4. Push to `main`. The workflow checks that `composer.json` matches `CHANGELOG.md`, then tags `vX.Y.Z` and creates the GitHub release with the CHANGELOG section as notes (0.x versions as pre-releases). A push that adds no new version does nothing, and a version that is already released is skipped. After fixing a failed run, start it again with *Run workflow* on the *Release* workflow.

The release attaches `magento-supertext-translation-X.Y.Z.zip`, built by `./build.sh` (for `app/code/Supertext/Translation`). Composer installs use the tag.

## Conventions

- Magento coding standard (PSR-12 based), constructor property promotion, `declare(strict_types=1)`.
- Keep `Api/` and `Model/Translation/FieldPlanner.php` free of Magento classes so they stay unit-testable.
- User-visible strings go through `__()` (or `translate` in XML), with German, French and Italian in `i18n/` (see *Interface strings*); the API client and models throw English templates that the controllers translate.
- New settings go in `etc/adminhtml/system.xml`, `etc/config.xml` (default), `Model/Config.php` and the settings table in `docs/INSTALLATION.md`.
- Keep the three docs in `docs/` current with every change (see `CLAUDE.md`).

## Known limitations / roadmap

- The source is always the default values (*All Store Views*); translating from one store view into another isn't offered.
- Not translated yet: other product attributes (custom text attributes, dropdown option labels), image labels, custom options, widget instances, emails.
- Subcategory URL paths in a store view follow a translated parent only once the subcategory is translated too.
- Mage-OS's own automatic translation module, when switched on with periodic re-translation, can overwrite store-view values.
- Translation runs in the browser, one item and store view at a time; selections are cut to 100 items. A queue (Magento's message queue) would allow more.
- Professional (human) translation orders, as in the WordPress plugin, are not offered.
