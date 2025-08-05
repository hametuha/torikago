# Kagome Analyzer WordPress Plugin

A WordPress plugin that provides Japanese text morphological analysis capabilities using the powerful [Kagome](https://github.com/ikawaha/kagome) analyzer.

## Features

- 🗾 **Japanese Text Analysis**: Advanced morphological analysis for Japanese content
- 🔌 **REST API Integration**: Easy-to-use REST endpoints for developers
- 🎛️ **Admin Interface**: User-friendly admin panel for testing and analysis
- 🔧 **Developer Functions**: Helper functions for theme and plugin developers
- 📚 **Multiple Dictionaries**: Support for IPA/UniDic system dictionaries and custom user dictionaries
- ⚡ **High Performance**: Direct integration with Kagome binary for optimal speed

## Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher
- Kagome binary (included in monorepo setup)

## Installation

### For Development (Monorepo Setup)

1. **Install dependencies:**
   ```bash
   cd plugins/wp-kagome-analyzer
   composer install --no-dev
   ```

2. **Set up WordPress environment:**
   ```bash
   npm install -g @wordpress/env
   wp-env start
   ```

3. **Access your development site:**
   - WordPress Admin: http://localhost:8888/wp-admin/
   - Username: `admin`
   - Password: `password`

### For Production

1. Copy the plugin folder to your WordPress `wp-content/plugins/` directory
2. Run `composer install --no-dev` in the plugin directory
3. Activate the plugin through the WordPress admin interface

## Usage

### Admin Interface

Navigate to **Tools > Kagome Analyzer** in your WordPress dashboard to access the analysis interface.

### REST API Endpoints

#### Analyze Text

**Endpoint:** `POST /wp-json/kagome/v1/analyze`

**Parameters:**
- `text` (required): Japanese text to analyze
- `options` (optional): Analysis options

**Example:**
```javascript
fetch('/wp-json/kagome/v1/analyze', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        text: 'すもももももももものうち',
        options: {
            mode: 'search'
        }
    })
});
```

#### Tokenize Text

**Endpoint:** `POST /wp-json/kagome/v1/tokenize`

**Parameters:** Same as analyze endpoint

**Example:**
```javascript
fetch('/wp-json/kagome/v1/tokenize', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        text: '東京タワーに行きました'
    })
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

### Prerequisites

- Node.js and npm
- Composer
- @wordpress/env

### Setup Development Environment

```bash
# Install WordPress environment
npm install -g @wordpress/env

# Install PHP dependencies
composer install

# Start WordPress development server
wp-env start

# Install and build JavaScript assets (if applicable)
npm install
npm run build
```

### Code Quality

```bash
# Check PHP code standards
composer phpcs

# Fix code standard issues
composer phpcs-fix

# Run tests
composer test

# Run all checks
composer check
```

### Available Scripts

| Command | Description |
|---------|-------------|
| `wp-env start` | Start WordPress development environment |
| `wp-env stop` | Stop WordPress development environment |
| `composer phpcs` | Run WordPress Coding Standards check |
| `composer phpcs-fix` | Fix coding standard issues |
| `composer test` | Run PHPUnit tests |
| `composer check` | Run all quality checks |

## Plugin Structure

```
wp-kagome-analyzer/
├── wp-kagome-analyzer.php      # Main plugin file
├── composer.json               # PHP dependencies
├── .wp-env.json               # WordPress environment config
├── phpcs.xml                  # Coding standards config
├── src/                       # PHP classes (PSR-4)
├── assets/                    # JavaScript and CSS assets
├── languages/                 # Translation files
├── tests/                     # PHPUnit tests
└── README.md                  # This file
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

## Error Handling

The plugin includes comprehensive error handling:

- Missing Kagome binary detection
- Composer dependency checks
- Analysis failure recovery
- User-friendly error messages

## Internationalization

The plugin is fully internationalized with the text domain `wp-kagome-analyzer`. Translation files should be placed in the `languages/` directory.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Follow WordPress Coding Standards
4. Write tests for new functionality
5. Ensure all tests pass: `composer check`
6. Submit a pull request

## Changelog

### 1.0.0
- Initial release
- REST API endpoints for analysis and tokenization
- Admin interface for testing
- Helper functions for developers
- WordPress Coding Standards compliance

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- [Kagome](https://github.com/ikawaha/kagome) - The excellent Japanese morphological analyzer
- Built on top of the [kagome-php](../../packages/kagome-php) library
- Designed for seamless WordPress integration
