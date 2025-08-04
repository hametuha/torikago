# Kagome PHP

A PHP wrapper library for [Kagome](https://github.com/ikawaha/kagome), a Japanese morphological analyzer written in Go.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

## Features

- 🗾 **Japanese Text Analysis**: Morphological analysis with detailed part-of-speech tagging
- 🔧 **Multiple Analysis Modes**: Normal, search, and extended modes
- 📚 **Dictionary Support**: Both system dictionaries (IPA/UniDic) and custom user dictionaries
- 🐳 **Cross-platform**: Supports both macOS and Linux environments with Docker
- ⚡ **High Performance**: Direct binary execution with JSON output parsing
- 🧪 **Well Tested**: Comprehensive test suite with PHPUnit

## Installation

Install via Composer:

```bash
composer require kagome/kagome-php
```

**Note**: This library requires the Kagome binary to be available. In a monorepo setup, the binary should be placed in `../../bin/kagome-binary/` relative to the package root.

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use Kagome\Analyzer;

$analyzer = new Analyzer();

// Basic analysis
$result = $analyzer->analyze('すもももももももものうち');
print_r($result);

// Simplified tokenization
$tokens = $analyzer->tokenize('東京タワーに行きました');
foreach ($tokens as $token) {
    echo $token['surface'] . ' (' . $token['pos'] . ')' . "\n";
}
```

## API Reference

### Constructor

```php
$analyzer = new Analyzer(?string $binaryPath = null);
```

- `$binaryPath`: Optional path to kagome binary. Auto-detects if not provided.

### Methods

#### `analyze(string $text, array $options = []): array`

Performs detailed morphological analysis and returns full token information.

**Parameters:**
- `$text`: The Japanese text to analyze
- `$options`: Analysis options (see below)

**Returns:** Array of token objects with detailed information

#### `tokenize(string $text, array $options = []): array`

Performs analysis and returns simplified token information.

**Parameters:** Same as `analyze()`

**Returns:** Array of simplified token objects with `surface`, `features`, and `pos` fields

### Analysis Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `mode` | string | `'normal'` | Analysis mode: `'normal'`, `'search'`, or `'extended'` |
| `sysdict` | string | `'ipa'` | System dictionary: `'ipa'` or `'uni'` |
| `udict` | string\|null | `null` | Path to user dictionary file |
| `simple` | bool | `false` | Display abbreviated dictionary contents |
| `split` | bool | `false` | Use tiny sentence splitter |

### Examples

#### Different Analysis Modes

```php
$text = '関西国際空港';

// Normal mode (default)
$normal = $analyzer->analyze($text, ['mode' => 'normal']);

// Search mode (more detailed tokenization)
$search = $analyzer->analyze($text, ['mode' => 'search']);

// Extended mode
$extended = $analyzer->analyze($text, ['mode' => 'extended']);
```

#### System Dictionary Selection

```php
// Use IPA dictionary (default)
$result = $analyzer->analyze('テキスト', ['sysdict' => 'ipa']);

// Use UniDic dictionary
$result = $analyzer->analyze('テキスト', ['sysdict' => 'uni']);
```

#### User Dictionary

```php
// Analyze with custom user dictionary
$result = $analyzer->analyze('カスタム語を解析', ['udict' => '/path/to/user_dict.txt']);
```

## User Dictionary Format

User dictionaries allow you to define custom words and their segmentation. Create a UTF-8 encoded CSV file with the following format:

```
# Comments start with #
<surface>,<tokens>,<readings>,<part-of-speech>
```

### Examples

```csv
# Custom user dictionary
日本経済新聞,日本 経済 新聞,ニッポン ケイザイ シンブン,名詞
関西国際空港,関西 国際 空港,カンサイ コクサイ クウコウ,名詞
朝青龍,朝青龍,アサショウリュウ,名詞
カスタム語,カスタム語,カスタムゴ,名詞
専門用語,専門 用語,センモン ヨウゴ,名詞
```

### Field Descriptions

1. **Surface**: The word as it appears in text
2. **Tokens**: How the word should be segmented (space-separated)
3. **Readings**: Pronunciation in katakana (space-separated for each token)
4. **Part-of-speech**: Grammatical category (e.g., 名詞, 動詞, 形容詞)

## Output Format

### `analyze()` Output

```php
[
    [
        'id' => 1,
        'start' => 0,
        'end' => 3,
        'surface' => 'すもも',
        'class' => 'KNOWN', // or 'USER', 'UNKNOWN'
        'pos' => ['名詞', '一般', '*', '*'],
        'base_form' => 'すもも',
        'reading' => 'スモモ',
        'pronunciation' => 'スモモ',
        'features' => ['名詞', '一般', '*', '*', '*', '*', 'すもも', 'スモモ', 'スモモ']
    ],
    // ... more tokens
]
```

### `tokenize()` Output

```php
[
    [
        'surface' => 'すもも',
        'features' => ['名詞', '一般', '*', '*', '*', '*', 'すもも', 'スモモ', 'スモモ'],
        'pos' => '名詞'
    ],
    // ... more tokens
]
```

## Development

### Requirements

- PHP 8.1 or higher
- Composer
- Kagome binary (macOS: `kagome-cli-macos-arm64`, Linux: `kagome-cli-linux`)
- Docker (for Linux environment testing)

### Local Testing (macOS)

```bash
# Install dependencies
composer install

# Run tests
composer test

# Check code style
composer phpcs

# Fix code style issues
composer phpcs-fix

# Run all checks
composer check
```

### Docker Testing (Linux Environment)

```bash
# Build and start container
docker-compose run --rm php

# Inside container:
composer install
composer test
composer check
```

### Available Composer Scripts

| Script | Description |
|--------|-------------|
| `composer test` | Run PHPUnit tests |
| `composer phpcs` | Run PHP CodeSniffer |
| `composer phpcs-fix` | Fix code style issues automatically |
| `composer check` | Run both tests and code style checks |

## Error Handling

The library throws `RuntimeException` for various error conditions:

```php
try {
    $analyzer = new Analyzer('/invalid/path');
} catch (RuntimeException $e) {
    echo "Error: " . $e->getMessage();
}

try {
    $result = $analyzer->analyze('text', ['udict' => '/nonexistent/dict.txt']);
} catch (RuntimeException $e) {
    echo "Dictionary error: " . $e->getMessage();
}
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass: `composer check`
5. Submit a pull request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- [Kagome](https://github.com/ikawaha/kagome) - The excellent Japanese morphological analyzer by ikawaha
- Built for use in WordPress plugins and other PHP applications requiring Japanese text analysis