# Kagome Analyzer (WordPress plugin)

Japanese morphological analysis for WordPress, powered by the Go-based
[kagome](https://github.com/ikawaha/kagome) analyzer. The kagome binary is
bundled with the plugin and invoked from PHP via `proc_open`.

> This was originally split into a Composer library + WordPress plugin for
> general distribution. It is now a single, self-contained plugin for internal
> (Hametuha) use, so the library layer has been folded into the plugin.

## 📦 Project structure

```
php-kagome/
├── bin/
│   └── build.sh                     # Cross-compiles the kagome binaries into the plugin
└── plugins/
    └── wp-kagome-analyzer/          # The plugin (this is the deployable artifact)
        ├── resources/kagome/        # Bundled kagome binaries (per OS/arch)
        │   ├── kagome-linux-amd64
        │   ├── kagome-linux-arm64
        │   └── kagome-darwin-arm64
        ├── src/
        │   ├── Analyzer.php          # WP-independent kagome wrapper (PSR-12)
        │   └── ...                   # WordPress integration (REST, CLI, settings)
        └── tests/                    # Pure-PHP integration tests for Analyzer
```

The binary for the current OS/architecture is selected at runtime by
`Analyzer::detectBinaryPath()` (`PHP_OS_FAMILY` × `php_uname('m')`), so the same
plugin runs on both x86_64 and arm64 (e.g. Intel and Graviton EC2).

## 🛠️ Development

Build/refresh the bundled binaries (needs Go; kagome is pure Go so this
cross-compiles without a C toolchain):

```sh
bin/build.sh                          # builds linux/amd64, linux/arm64, darwin/arm64
KAGOME_VERSION=v2.11.0 bin/build.sh   # pin a kagome version
```

Install PHP dependencies and run checks from the plugin directory:

```sh
cd plugins/wp-kagome-analyzer
composer install
composer test                    # PHPUnit: exercises the real bundled binary
composer phpcs                   # WPCS (Analyzer.php is excluded; kept PSR-12)
```

Local WordPress via [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/):

```sh
cd plugins/wp-kagome-analyzer
npm install && npm run env -- start
```

## 🚀 Deployment notes

- Deploying the plugin must include `composer install --no-dev` (the plugin
  relies on Composer's autoloader for its own classes).
- Installing from a ZIP upload strips the executable bit from the bundled
  binary; the plugin restores it on activation (`chmod 0755`), with a fallback
  attempt in `Analyzer`'s constructor. Hosts that forbid `chmod` will surface an
  admin notice.
- `exec`/`proc_open` must not be disabled via `disable_functions`.
