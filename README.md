# Kagome Analyzer WordPress Plugin

A WordPress plugin that provides Japanese text morphological analysis using the
powerful [Kagome](https://github.com/ikawaha/kagome) analyzer. The kagome binary
is bundled with the plugin and invoked from PHP via `proc_open`.

> Internal (Hametuha) plugin. Not distributed on WordPress.org. The repository
> root **is** the plugin.

## Features

- 🗾 **Japanese Text Analysis**: Advanced morphological analysis for Japanese content
- 🔌 **REST API Integration**: Easy-to-use REST endpoints for developers
- 🎛️ **Admin Interface**: User-friendly admin panel for testing and analysis
- 🔧 **Developer Functions**: Helper functions for theme and plugin developers
- 📚 **Multiple Dictionaries**: Support for IPA/UniDic system dictionaries and custom user dictionaries
- ⚡ **High Performance**: Direct integration with the Kagome binary for optimal speed

## Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher
- `exec`/`proc_open` must not be disabled via `disable_functions`
- The kagome binary — **not committed to git**; built by `bin/build.sh` or shipped
  in the release ZIP (see [Binaries](#binaries))

## Binaries

The binaries under `resources/kagome/` are **build artifacts and are not committed
to git**. The binary for the current OS/architecture is selected at runtime by
`Analyzer::detectBinaryPath()` (`PHP_OS_FAMILY` × `php_uname('m')`), so the same
plugin runs on both x86_64 and arm64 (e.g. Intel and Graviton EC2).

Build them locally (needs Go; kagome is pure Go so this cross-compiles without a C
toolchain):

```sh
bin/build.sh                          # builds linux/amd64, linux/arm64, darwin/arm64
KAGOME_VERSION=v2.11.0 bin/build.sh   # pin a kagome version
```

A fresh `git clone` has no binaries until you build them (or unpack a release ZIP).

## Installation

### Development

```sh
composer install            # PHP dependencies (incl. dev tooling)
bin/build.sh                # build the kagome binaries into resources/kagome/
npm install && npm run env -- start   # local WordPress via wp-env
```

Development site: http://localhost:8888/wp-admin/ (admin / password).

### Production

Deploy the packaged ZIP from a GitHub Release (CI builds the binaries and runs
`composer install --no-dev`), or replicate those two steps when deploying from
source:

1. Place the plugin in `wp-content/plugins/wp-kagome-analyzer/`
2. `composer install --no-dev` and `bin/build.sh` (the plugin relies on Composer's
   autoloader and the bundled binary)
3. Activate the plugin — activation restores the binary's executable bit
   (`chmod 0755`) in case a ZIP install stripped it

## Usage

### Admin Interface

Navigate to **Tools > Kagome Analyzer** in your WordPress dashboard.

### REST API Endpoints

#### Analyze Text

**Endpoint:** `POST /wp-json/kagome/v1/analyze`

**Parameters:**
- `text` (required): Japanese text to analyze
- `options` (optional): Analysis options

```javascript
fetch('/wp-json/kagome/v1/analyze', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        text: 'すもももももももものうち',
        options: { mode: 'search' }
    })
});
```

#### Tokenize Text

**Endpoint:** `POST /wp-json/kagome/v1/tokenize`

**Parameters:** Same as the analyze endpoint.

```javascript
fetch('/wp-json/kagome/v1/tokenize', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({ text: '東京タワーに行きました' })
});
```

### PHP Helper Functions

#### `wp_kagome_analyze( $text, $options = [] )`

Analyze Japanese text and return detailed morphological information.

```php
$result = wp_kagome_analyze('すもももももももものうち');
if ($result) {
    foreach ($result as $token) {
        echo $token['surface'] . ' (' . $token['pos'][0] . ')' . "\n";
    }
}
```

#### `wp_kagome_tokenize( $text, $options = [] )`

Tokenize Japanese text and return simplified token information.

```php
$tokens = wp_kagome_tokenize('東京タワーに行きました');
if ($tokens) {
    foreach ($tokens as $token) {
        echo $token['surface'] . ' ';
    }
}
```

### Analysis Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `mode` | string | `'normal'` | Analysis mode: `'normal'`, `'search'`, or `'extended'` |
| `sysdict` | string | `'ipa'` | System dictionary: `'ipa'` or `'uni'` |
| `udict` | string | `null` | Path to user dictionary file |
| `simple` | bool | `false` | Display abbreviated dictionary contents |
| `split` | bool | `false` | Use tiny sentence splitter |

### User Dictionary

You can create custom dictionaries for domain-specific terms:

```csv
# Custom user dictionary
専門用語,専門用語,センモンヨウゴ,名詞
固有名詞,固有名詞,コユウメイシ,名詞
```

## Development

### Code quality & tests

```sh
composer test        # PHPUnit — exercises the real bundled binary (run bin/build.sh first)
composer phpcs       # WordPress Coding Standards (src/Analyzer.php is excluded; kept PSR-12)
composer phpcs-fix   # Fix coding standard issues
composer check       # phpcs + test
```

`src/Analyzer.php` is intentionally WordPress-independent (PSR-12) so it can be
reused as a standalone library; it is excluded from the WPCS ruleset.

### CI

- `.github/workflows/test.yml` — builds the binaries from the Go module, then runs
  PHPUnit on PHP 8.1/8.3 (also exercises the linux-amd64 binary).
- `.github/workflows/release.yml` — on a published Release, builds the binaries and
  packages a plugin ZIP (Linux binaries only; darwin is dev-only) attached to the
  release. Dev files are excluded via `.distignore`.

## Project Structure

```
php-kagome/                       # repo root = the plugin
├── wp-kagome-analyzer.php        # Main plugin file
├── composer.json                 # PHP dependencies (autoload Hametuha\KagomeWp\ => src/)
├── .wp-env.json                  # WordPress environment config
├── phpcs.xml / phpunit.xml.dist  # QA config
├── .distignore                   # Files excluded from the distribution ZIP
├── src/                          # PHP classes (PSR-4); Analyzer.php is the WP-independent core
├── assets/                       # JavaScript and CSS assets
├── tests/                        # PHPUnit tests (pure-PHP integration)
├── resources/kagome/             # Bundled kagome binaries (build artifacts, gitignored)
├── bin/build.sh                  # Cross-compiles the binaries (dev tooling, not shipped)
└── .github/workflows/            # CI (not shipped)
```

## API Response Format

### Analyze Response

```json
[
    {
        "id": 1,
        "start": 0,
        "end": 3,
        "surface": "すもも",
        "class": "KNOWN",
        "pos": ["名詞", "一般", "*", "*"],
        "base_form": "すもも",
        "reading": "スモモ",
        "pronunciation": "スモモ",
        "features": ["名詞", "一般", "*", "*", "*", "*", "すもも", "スモモ", "スモモ"]
    }
]
```

### Tokenize Response

```json
[
    {
        "surface": "すもも",
        "features": ["名詞", "一般", "*", "*", "*", "*", "すもも", "スモモ", "スモモ"],
        "pos": "名詞"
    }
]
```

## Permissions

- **REST API Access**: Users with `edit_posts` capability can use the API endpoints
- **Admin Interface**: Users with `manage_options` capability can access the admin panel

## Deployment notes

- The release ZIP ships only the Linux binaries (the darwin build is dev-only).
- Installing from a ZIP upload strips the executable bit from the bundled binary;
  the plugin restores it on activation (`chmod 0755`), with a fallback attempt in
  `Analyzer`'s constructor. Hosts that forbid `chmod` will surface an admin notice.
- A bare `git` checkout has neither the binaries nor `vendor/` — build both.

## Internationalization

The plugin is internationalized with the text domain `wp-kagome-analyzer`.
Translation files go in a `languages/` directory.

## Changelog

### 1.0.0
- Initial release
- REST API endpoints for analysis and tokenization
- Admin interface for testing
- Helper functions for developers
- WordPress Coding Standards compliance

## License

GPL v2 or later.

## Acknowledgments

- [Kagome](https://github.com/ikawaha/kagome) — the excellent Japanese morphological analyzer
