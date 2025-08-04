# PHP Kagome Integration

This project integrates the Golang-based morphological analyzer `kagome` with PHP via a Composer library and optionally a WordPress plugin.

## 📦 Project Structure

```
php-kagome/
├── packages/
│   └── kagome-php/          # Composer library wrapping kagome CLI
├── plugins/
│   └── wp-kagome-analyzer/  # (Optional) WordPress plugin using the library
├── bin/

│   └── kagome-cli           # kagome binary (must be built separately)
└── composer.json            # Root composer for monorepo
```

## 🛠️ Setup Instructions

1. **Build kagome CLI** (on Linux or WSL):
   ```sh
   git clone https://github.com/ikawaha/kagome.git
   cd kagome/cmd/kagome
   GOOS=linux GOARCH=amd64 go build -o kagome-cli
   cp kagome-cli /path/to/php-kagome/bin/
   ```

2. **Install dependencies**:
   From the project root:
   ```sh
   composer install
   ```

3. **Use Analyzer class**:
   Example PHP code:
   ```php
   use Hametuha\Kagome\Analyzer;

   $analyzer = new Analyzer(__DIR__ . '/bin/kagome-cli');
   $result = $analyzer->analyze('今日はいい天気ですね。');
   print_r($result);
   ```

## ✅ Next Steps

- [ ] Add test suite
- [ ] Add plugin boilerplate under `plugins/`
- [ ] Optionally support automatic binary download if not found
- [ ] Improve error handling/logging
