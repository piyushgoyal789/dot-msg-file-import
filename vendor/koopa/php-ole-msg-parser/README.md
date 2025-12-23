# php-ole-msg-parser

Minimal PHP library for parsing Outlook .msg files stored in OLE compound documents.

## Features

- Reads raw OLE compound streams without external dependencies
- Extracts headers, plain-text body, RTF body, and attachments from .msg files
- Provides lightweight loader interfaces for custom property handling

## Installation

```bash
composer require koopa/php-ole-msg-parser
```

## Usage

```php
require __DIR__ . '/vendor/autoload.php';

use Opt\OLE\MsgParser;

$parser = new MsgParser('path/to/message.msg');
$message = $parser->parse();

print_r($message->headers);
echo $message->body;
foreach ($message->attachments as $attachment) {
    file_put_contents($attachment['filename'], base64_decode($attachment['data']));
}
```

## Requirements

- PHP 7.4+
- mbstring extenstion

## License

MIT
