<?php
declare(strict_types=1);

namespace Opt\OLE;

use Exception;
use stdClass;

/**
 * High-level parser that turns an OleFile-backed Outlook .msg into
 * headers, body content, and attachments for simple consumption.
 */
class MsgParser {
    protected OleFile $ole;
    /** @var array<string, string> */
    protected array $streams;

    /**
     * @param string $filename Absolute path to a .msg file inside an OLE container.
     */
    public function __construct(string $filename) {
        $this->ole = new OleFile($filename);
        $this->streams = $this->ole->getStreams();
    }

    /**
     * Parse the root message stream and return a portable stdClass payload.
     *
     * @return \stdClass
     * @throws \Exception When the root properties stream is missing.
     */
    public function parse(): stdClass {
        if (isset($this->streams['__properties_version1.0'])) {
            $data = $this->streams['__properties_version1.0'];
            return $this->parseMessageStream($data, true, $this->streams, '');
        }
        throw new Exception("Root properties stream not found.");
    }

    /**
     * Recursively walk a message stream and build a stdClass with headers/body/attachments.
     */
    protected function parseMessageStream(string $data, bool $isTopLevel, array $container, string $basePath): stdClass {
        $props = $this->parseProperties($data, $isTopLevel, $container, $basePath);
        $msg = new stdClass();
        $msg->headers = [];

        if (isset($props['TRANSPORT_MESSAGE_HEADERS'])) {
            $headers = is_string($props['TRANSPORT_MESSAGE_HEADERS']) ? $props['TRANSPORT_MESSAGE_HEADERS'] : "";
            $headers = preg_replace('/Content-Type: .*(\n\s.*)*\n/i', '', $headers);
            $msg->headers = $this->parseHeaders($headers);
        } else {
            if (isset($props['MESSAGE_DELIVERY_TIME'])) {
                $dt = $props['MESSAGE_DELIVERY_TIME'];
                $msg->headers['Date'] = $dt->format(DATE_RFC2822);
            }
            if (isset($props['SENDER_NAME'])) {
                if (isset($props['SENT_REPRESENTING_NAME']) && $props['SENT_REPRESENTING_NAME'] && $props['SENDER_NAME'] !== $props['SENT_REPRESENTING_NAME']) {
                    $props['SENDER_NAME'] .= " (" . $props['SENT_REPRESENTING_NAME'] . ")";
                }
                $msg->headers['From'] = $props['SENDER_NAME'];
            }
            if (isset($props['DISPLAY_TO']) && $props['DISPLAY_TO']) {
                $msg->headers['To'] = $props['DISPLAY_TO'];
            }
            if (isset($props['DISPLAY_CC']) && $props['DISPLAY_CC']) {
                $msg->headers['CC'] = $props['DISPLAY_CC'];
            }
            if (isset($props['DISPLAY_BCC']) && $props['DISPLAY_BCC']) {
                $msg->headers['BCC'] = $props['DISPLAY_BCC'];
            }
            if (isset($props['SUBJECT']) && $props['SUBJECT']) {
                $msg->headers['Subject'] = $props['SUBJECT'];
            }
        }

        $hasBody = false;
        if (isset($props['BODY'])) {
            $msg->body = $props['BODY'];
            $hasBody = true;
        } elseif ($streamBody = $this->loadStreamProperty($container, 0x1000, [
            0x001F => new UnicodeLoader(),
            0x001E => new String8Loader()
        ], $props, $basePath)) {
            $msg->body = $streamBody;
            $hasBody = true;
        }
        if (isset($props['RTF_COMPRESSED'])) {
            $rtf = $this->decompressRTF($props['RTF_COMPRESSED']);
            if (!$hasBody) {
                $msg->body = $rtf;
                $hasBody = true;
            } else {
                $msg->alternativeBody = $rtf;
            }
        }
        if (!$hasBody) {
            $msg->body = "<no message body>";
        }

        $msg->attachments = [];
        $attachmentBases = [];
        foreach ($container as $streamName => $streamData) {
            if (strpos($streamName, "__attach_version1.0_#") !== 0) {
                continue;
            }
            $parts = explode('/', $streamName, 2);
            $base = $parts[0];
            $attachmentBases[$base] = true;
        }
        foreach (array_keys($attachmentBases) as $storageBase) {
            $attachment = $this->processAttachment($storageBase, $container);
            if ($attachment) {
                $msg->attachments[] = $attachment;
            }
        }
        return $msg;
    }

    /**
     * Convert an attachment storage into a lightweight associative array.
     */
    protected function processAttachment(string $storageName, array $container): ?array {
        $propsStreamName = $storageName . '/__properties_version1.0';
        if (!isset($container[$propsStreamName])) {
            return null;
        }
        $propsData = $container[$propsStreamName];
        $props = $this->parseProperties($propsData, false, $container, $storageName);
        if (!isset($props['ATTACH_DATA_BIN'])) {
            return null;
        }
        $blob = $props['ATTACH_DATA_BIN'];
        $filename = $props['ATTACH_LONG_FILENAME'] ?? $props['ATTACH_FILENAME'] ?? $props['DISPLAY_NAME'] ?? 'attachment';
        $filename = basename($filename);
        $mimeType = $props['ATTACH_MIME_TAG'] ?? 'application/octet-stream';
        return [
            'filename' => $filename,
            'mimeType' => $mimeType,
            'data'     => base64_encode($blob)
        ];
    }

    /**
     * Decode a properties stream using the registered property tags and loaders.
     */
    protected function parseProperties(string $data, bool $isTopLevel, array $container, string $basePath): array {
        $props = [];
        $offset = $isTopLevel ? 32 : 24;
        while ($offset + 16 <= strlen($data)) {
            $chunk = substr($data, $offset, 16);
            $unpacked = unpack('Vtag/Vflags/a8value', $chunk);
            $rawTag = $unpacked['tag'];
            $ptype = $rawTag & 0xFFFF;
            $ptag  = ($rawTag >> 16) & 0xFFFF;
            if (!isset(self::$propertyTags[$ptag])) {
                $offset += 16;
                continue;
            }
            $tagName = self::$propertyTags[$ptag][0];
            $loader = $this->getLoader($ptype);
            $streamName = sprintf("__substg1.0_%04X%04X", $ptag, $ptype);
            $qualifiedStream = $basePath ? $basePath . '/' . $streamName : $streamName;

            if ($loader instanceof EmbeddedMessageLoader) {
                if (isset($container[$streamName])) {
                    $embeddedData = $container[$streamName];
                    $props[$tagName] = $this->parseMessageStream($embeddedData, false, $container, $basePath);
                } elseif (isset($container[$qualifiedStream])) {
                    $embeddedData = $container[$qualifiedStream];
                    $props[$tagName] = $this->parseMessageStream($embeddedData, false, $container, $basePath ? $basePath . '/' . $streamName : $streamName);
                }
            } elseif ($loader instanceof LoaderInterface && ($loader instanceof String8Loader || $loader instanceof UnicodeLoader || $loader instanceof BinaryLoader)) {
                if (isset($container[$streamName])) {
                    $value = $container[$streamName];
                    $props[$tagName] = $loader->load($value, ['encodings' => $this->getEncodings($props, $tagName)]);
                } elseif (isset($container[$qualifiedStream])) {
                    $value = $container[$qualifiedStream];
                    $props[$tagName] = $loader->load($value, ['encodings' => $this->getEncodings($props, $tagName)]);
                }
            } elseif ($loader instanceof LoaderInterface) {
                $value = substr($unpacked['value'], 0, 8);
                $props[$tagName] = $loader->load($value);
            }
            $offset += 16;
        }
        return $props;
    }

    /**
     * Resolve string/binary properties that are stored in dedicated substg streams.
     */
    protected function loadStreamProperty(array $container, int $ptag, array $loaderMap, array $props, string $basePath): ?string
    {
        $tagName = self::$propertyTags[$ptag][0] ?? '';
        $encodings = $this->getEncodings($props, $tagName);
        foreach ($loaderMap as $ptype => $loader) {
            $streamName = sprintf("__substg1.0_%04X%04X", $ptag, $ptype);
            $qualifiedStream = $basePath ? $basePath . '/' . $streamName : $streamName;
            if (isset($container[$streamName])) {
                $value = $container[$streamName];
            } elseif (isset($container[$qualifiedStream])) {
                $value = $container[$qualifiedStream];
            } else {
                continue;
            }
            if ($loader instanceof LoaderInterface) {
                return (string)$loader->load($value, ['encodings' => $encodings]);
            }
            return $value;
        }
        return null;
    }

    /**
     * Determine preferred encoding order for the given property tag.
     */
    protected function getEncodings(array $props, string $tagName): array {
        $encodings = [];
        $bodyEncoding = isset($props['PR_INTERNET_CPID']) && isset(self::$codePages[$props['PR_INTERNET_CPID']])
            ? self::$codePages[$props['PR_INTERNET_CPID']] : null;
        $propertiesEncoding = isset($props['PR_MESSAGE_CODEPAGE']) && isset(self::$codePages[$props['PR_MESSAGE_CODEPAGE']])
            ? self::$codePages[$props['PR_MESSAGE_CODEPAGE']] : null;
        if ($tagName === "BODY") {
            if ($bodyEncoding) $encodings[] = $bodyEncoding;
            if ($propertiesEncoding) $encodings[] = $propertiesEncoding;
        } else {
            if ($propertiesEncoding) $encodings[] = $propertiesEncoding;
            if ($bodyEncoding) $encodings[] = $bodyEncoding;
        }
        if (empty($encodings)) {
            $encodings[] = 'cp1252';
        }
        return $encodings;
    }

    /**
     * Map a property type to a loader implementation.
     */
    protected function getLoader(int $ptype): ?LoaderInterface {
        switch ($ptype) {
            case 0x1:  return new NullLoader();
            case 0x2:  return new Integer16Loader();
            case 0x3:  return new Integer32Loader();
            case 0xB:  return new BooleanLoader();
            case 0x14: return new Integer64Loader();
            case 0x40: return new IntTimeLoader();
            case 0x1e: return new String8Loader();
            case 0x1f: return new UnicodeLoader();
            case 0x102:return new BinaryLoader();
            case 0xD:  return new EmbeddedMessageLoader();
            default:   return null;
        }
    }

    /**
     * Placeholder RTF decompression hook – currently returns the raw payload.
     */
    protected function decompressRTF(string $data): string {
        return $data;
    }

    /**
     * Collapse raw RFC822-style headers into a key/value array.
     */
    protected function parseHeaders(string $headers): array {
        $result = [];
        $lines = preg_split("/\r\n|\n|\r/", $headers);
        $currentHeader = '';
        foreach ($lines as $line) {
            if (preg_match('/^\s+/', $line)) {
                $result[$currentHeader] .= ' ' . trim($line);
            } else {
                $parts = explode(":", $line, 2);
                if (count($parts) == 2) {
                    $currentHeader = trim($parts[0]);
                    $result[$currentHeader] = trim($parts[1]);
                }
            }
        }
        return $result;
    }

    /** @var array<int, array{0:string,1:string}> Partial mapping of property tags; extend as needed. */
    public static array $propertyTags = [
        0x01   => ['ACKNOWLEDGEMENT_MODE', 'I4'],
        0x02   => ['ALTERNATE_RECIPIENT_ALLOWED', 'BOOLEAN'],
        0x03   => ['AUTHORIZING_USERS', 'BINARY'],
        0x37   => ['SUBJECT', 'STRING'],
        0x1000 => ['BODY', 'STRING'],
        0x1009 => ['RTF_COMPRESSED', 'BINARY'],
        0x0E06 => ['MESSAGE_DELIVERY_TIME', 'SYSTIME'],
        0x0C1A => ['SENDER_NAME', 'STRING'],
        0x4F   => ['SENT_REPRESENTING_NAME', 'STRING'],
        0x0E04 => ['DISPLAY_TO', 'STRING'],
        0x0E03 => ['DISPLAY_CC', 'STRING'],
        0x0E02 => ['DISPLAY_BCC', 'STRING'],
        0x3701 => ['ATTACH_DATA_BIN', 'BINARY'],
        0x3707 => ['ATTACH_LONG_FILENAME', 'STRING'],
        0x3704 => ['ATTACH_FILENAME', 'STRING'],
        0x370E => ['ATTACH_MIME_TAG', 'STRING'],
    ];

    /** @var array<int, string> Mapping between Windows code pages and iconv/mbstring names. */
    public static array $codePages = [
        437   => 'cp437',
        850   => 'cp850',
        852   => 'cp852',
        936   => 'gb2312',
        1250  => 'cp1250',
        1251  => 'cp1251',
        1252  => 'cp1252',
        1253  => 'cp1253',
        1254  => 'cp1254',
        1255  => 'cp1255',
        1256  => 'cp1256',
        1257  => 'cp1257',
        1258  => 'cp1258',
        20127 => 'ascii',
        20866 => 'koi8-r',
        21866 => 'koi8-u',
        28591 => 'iso8859-1',
        28592 => 'iso8859-2',
        28593 => 'iso8859-3',
        28594 => 'iso8859-4',
        28595 => 'iso8859-5',
        28596 => 'iso8859-6',
        28597 => 'iso8859-7',
        28598 => 'iso8859-8',
        28599 => 'iso8859-9',
        28603 => 'iso8859-13',
        28605 => 'iso8859-15',
        65001 => 'utf-8'
    ];
}
