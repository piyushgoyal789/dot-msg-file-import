<?php
declare(strict_types=1);

namespace Opt\OLE;

/**
 * Minimal OLE Compound File parser tailored for Outlook .msg payloads.
 */
class OleFile {
    protected string $filename;
    protected string $data;
    protected int $sectorSize;
    protected int $miniSectorSize;
    protected int $shortStreamCutoffSize = 4096;
    /** @var array<int, int> */
    protected array $fat = [];
    /** @var array<int, int> */
    protected array $difat = [];
    /** @var array<int, int> */
    protected array $miniFat = [];
    protected string $miniStream = '';
    /** @var array<string, array{type:int,startSector:int,size:int}> */
    protected array $directoryEntries = [];
    /** @var array<int, array<string, mixed>> */
    protected array $rawDirectoryEntries = [];
    /** @var array<string, array<string, mixed>> */
    protected array $storages = [];
    /** @var array<string, int>|null */
    protected ?array $rootEntry = null;
    protected ?int $miniFatStart = null;
    protected int $miniFatSectorCount = 0;
    protected ?int $difatStart = null;
    protected int $difatSectorCount = 0;

    /**
     * @param string $filename Absolute path to an OLE compound document.
     */
    public function __construct(string $filename) {
        $this->filename = $filename;
        $this->data = file_get_contents($filename);
        if (substr($this->data, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
            throw new \RuntimeException("Not a valid OLE file.");
        }
        $this->parseHeader();
        $this->buildFAT();
        $this->buildMiniFAT();
        $this->readDirectory();
        $this->loadMiniStream();
    }

    /**
     * Parse the file header to establish sector sizes and FAT pointers.
     */
    protected function parseHeader(): void {
        $header = substr($this->data, 0, 512);
        $sectorShift = unpack('v', substr($header, 30, 2))[1];
        $this->sectorSize = 1 << $sectorShift;
        $miniSectorShift = unpack('v', substr($header, 32, 2))[1];
        $this->miniSectorSize = 1 << $miniSectorShift;
        $this->shortStreamCutoffSize = unpack('V', substr($header, 56, 4))[1];
        $this->miniFatStart = unpack('V', substr($header, 60, 4))[1];
        $this->miniFatSectorCount = unpack('V', substr($header, 64, 4))[1];
        $this->difatStart = unpack('V', substr($header, 68, 4))[1];
        $this->difatSectorCount = unpack('V', substr($header, 72, 4))[1];
        $this->difat = array_values(unpack('V109', substr($header, 76, 4 * 109)));
        $this->difat = array_filter($this->difat, static function ($entry) {
            return $entry !== 0xFFFFFFFF;
        });
        $this->loadAdditionalDIFATSectors();
    }

    /**
     * Build the FAT chain using DIFAT entries.
     */
    protected function buildFAT(): void {
        $this->fat = [];

        foreach ($this->difat as $fatSector) {
            if ($fatSector == 0xFFFFFFFF) continue;
            $sectorData = $this->getSector($fatSector);
            if ($sectorData === false) continue;
            $fatEntries = array_values(unpack('V*', $sectorData));
            $this->fat = array_merge($this->fat, $fatEntries);
        }

        if (empty($this->fat)) {
            throw new \RuntimeException("FAT table is empty. Possibly an invalid or corrupted OLE file.");
        }
    }

    /**
     * Build the mini FAT used for short streams.
     */
    protected function buildMiniFAT(): void {
        if ($this->miniFatSectorCount <= 0 || $this->miniFatStart === 0xFFFFFFFE) {
            return;
        }
        $chain = $this->readChain($this->miniFatStart, $this->miniFatSectorCount * $this->sectorSize);
        if ($chain === '') {
            return;
        }
        $this->miniFat = array_values(unpack('V*', $chain));
    }

    /**
     * Read any additional DIFAT sectors linked from the header block.
     */
    protected function loadAdditionalDIFATSectors(): void {
        $current = $this->difatStart;
        $remaining = $this->difatSectorCount;
        while ($current !== 0xFFFFFFFE && $remaining > 0) {
            $sectorData = $this->getSector($current);
            if ($sectorData === false) {
                break;
            }
            $entries = array_values(unpack('V127', substr($sectorData, 0, 127 * 4)));
            $entries = array_filter($entries, static function ($entry) {
                return $entry !== 0xFFFFFFFF;
            });
            $this->difat = array_merge($this->difat, $entries);
            $current = unpack('V', substr($sectorData, 127 * 4, 4))[1];
            $remaining--;
        }
    }

    /**
     * Walk the directory stream and materialize entries and storages.
     */
    protected function readDirectory(): void {
        $dirStart = unpack('V', substr($this->data, 48, 4))[1];
        $dirData = $this->readChain($dirStart);
        $entrySize = 128;
        $numEntries = strlen($dirData) / $entrySize;
        $this->rawDirectoryEntries = [];
        for ($i = 0; $i < $numEntries; $i++) {
            $entry = substr($dirData, $i * $entrySize, $entrySize);
            if ($entry === false || $entry === '') {
                continue;
            }
            $nameLength = unpack('v', substr($entry, 64, 2))[1];
            if ($nameLength < 2) {
                continue;
            }
            $nameData = substr($entry, 0, $nameLength - 2);
            $name = rtrim(mb_convert_encoding($nameData, 'UTF-8', 'UTF-16LE'), "\x00");
            $type = ord($entry[66]);
            $left = unpack('V', substr($entry, 68, 4))[1];
            $right = unpack('V', substr($entry, 72, 4))[1];
            $child = unpack('V', substr($entry, 76, 4))[1];
            $startSector = unpack('V', substr($entry, 116, 4))[1];
            $size = unpack('V', substr($entry, 120, 4))[1];
            $this->rawDirectoryEntries[$i] = [
                'name'        => $name,
                'type'        => $type,
                'left'        => $left,
                'right'       => $right,
                'child'       => $child,
                'startSector' => $startSector,
                'size'        => $size
            ];
        }

        $this->directoryEntries = [];
        $this->storages = [];
        if (!empty($this->rawDirectoryEntries)) {
            $this->traverseDirectory(0, '');
        }
    }

    /**
     * Traverse the red-black directory tree recursively.
     */
    protected function traverseDirectory(int $index, string $parentPath): void {
        if ($index === 0xFFFFFFFF || !isset($this->rawDirectoryEntries[$index])) {
            return;
        }

        $entry = $this->rawDirectoryEntries[$index];
        $this->traverseDirectory($entry['left'], $parentPath);

        $name = $entry['name'];
        $isRoot = ($entry['type'] == 5 && $name === 'Root Entry' && $parentPath === '');
        $currentPath = $isRoot ? '' : trim($parentPath . '/' . $name, '/');
        if ($currentPath === '') {
            $currentPath = $isRoot ? '' : $name;
        }

        if ($entry['type'] == 2) {
            $key = $parentPath === '' ? $name : $currentPath;
            $this->directoryEntries[$key] = [
                'type'        => $entry['type'],
                'startSector' => $entry['startSector'],
                'size'        => $entry['size']
            ];
        } elseif ($entry['type'] == 1) {
            $storageKey = $parentPath === '' ? $name : $currentPath;
            $this->storages[$storageKey] = $entry;
        } elseif ($entry['type'] == 5 && $isRoot) {
            $this->rootEntry = [
                'startSector' => $entry['startSector'],
                'size'        => $entry['size']
            ];
        }

        if ($entry['child'] !== 0xFFFFFFFF) {
            $childParent = $isRoot ? '' : ($parentPath === '' ? $name : $currentPath);
            $this->traverseDirectory($entry['child'], $childParent);
        }

        $this->traverseDirectory($entry['right'], $parentPath);
    }

    /**
     * Cache the mini stream so mini FAT chains can be read quickly.
     */
    protected function loadMiniStream(): void {
        if (!$this->rootEntry || $this->rootEntry['size'] <= 0) {
            return;
        }
        $this->miniStream = $this->readChain($this->rootEntry['startSector'], $this->rootEntry['size']);
    }

    /**
     * Fetch a named stream from the directory.
     */
    public function getStream(string $name): string {
        if (!isset($this->directoryEntries[$name])) {
            throw new \Exception("Stream '$name' not found.");
        }
        $entry = $this->directoryEntries[$name];
        if ($entry['size'] > 0 && $entry['size'] < $this->shortStreamCutoffSize && $entry['startSector'] !== 0xFFFFFFFE) {
            return $this->readMiniChain($entry['startSector'], $entry['size']);
        }
        return $this->readChain($entry['startSector'], $entry['size']);
    }

    /**
     * Return all available streams keyed by directory name.
     */
    public function getStreams(): array {
        $streams = [];
        foreach ($this->directoryEntries as $name => $entry) {
            if ($entry['type'] == 2) {
                $streams[$name] = $this->getStream($name);
            }
        }
        return $streams;
    }

    /**
     * Return the raw bytes for a sector index.
     */
    protected function getSector(int $sector): string {
        $offset = ($sector + 1) * $this->sectorSize;
        return substr($this->data, $offset, $this->sectorSize);
    }

    /**
     * Follow FAT pointers until the chain terminates.
     */
    protected function readChain(?int $startSector, ?int $size = null): string {
        $specialValues = [0xFFFFFFFE, 0xFFFFFFFF, -2, -1];
        if ($startSector === null || in_array($startSector, $specialValues, true)) {
            return '';
        }

        if (!isset($this->fat[$startSector])) {
            throw new \RuntimeException("Invalid sector reference: $startSector. FAT may be empty.");
        }

        $chain = "";
        $sector = $startSector;

        while (isset($this->fat[$sector]) && $sector != 0xFFFFFFFE) {
            $chain .= $this->getSector($sector);
            $sector = $this->fat[$sector] ?? 0xFFFFFFFE;
        }

        return ($size !== null) ? substr($chain, 0, $size) : $chain;
    }

    /**
     * Follow mini FAT pointers using the cached mini stream.
     */
    protected function readMiniChain(?int $startSector, ?int $size = null): string {
        $specialValues = [0xFFFFFFFE, 0xFFFFFFFF, -2, -1];
        if ($startSector === null || in_array($startSector, $specialValues, true)) {
            return '';
        }
        if ($this->miniStream === '' || empty($this->miniFat)) {
            return '';
        }

        $chain = '';
        $sector = $startSector;
        $visited = 0;

        while ($sector !== 0xFFFFFFFE) {
            $offset = $sector * $this->miniSectorSize;
            if ($offset >= strlen($this->miniStream)) {
                break;
            }
            $chain .= substr($this->miniStream, $offset, $this->miniSectorSize);
            $sector = $this->miniFat[$sector] ?? 0xFFFFFFFE;
            $visited++;
            if ($visited > 100000) {
                break;
            }
        }

        return ($size !== null) ? substr($chain, 0, $size) : $chain;
    }

}

/** Loader interfaces and implementations. */
interface LoaderInterface {
    /**
     * Load a value from a binary stream.
     *
     * @param string $value
     * @param array<string, mixed> $options
     * @return mixed
     */
    public function load(string $value, array $options = []);
}

class NullLoader implements LoaderInterface {
    public function load(string $value, array $options = []): ?string {
        return null;
    }
}

class BooleanLoader implements LoaderInterface {
    public function load(string $value, array $options = []): bool {
        return (ord($value[0]) === 1);
    }
}

class Integer16Loader implements LoaderInterface {
    public function load(string $value, array $options = []): int {
        return unpack('v', substr($value, 0, 2))[1];
    }
}

class Integer32Loader implements LoaderInterface {
    public function load(string $value, array $options = []): int {
        return unpack('V', substr($value, 0, 4))[1];
    }
}

class Integer64Loader implements LoaderInterface {
    public function load(string $value, array $options = []): int {
        $arr = unpack('Vlow/Vhigh', substr($value, 0, 8));
        return $arr['low'] + ($arr['high'] << 32);
    }
}

class IntTimeLoader implements LoaderInterface {
    public function load(string $value, array $options = []): \DateTime {
        $int64 = (new Integer64Loader())->load($value);
        $seconds = $int64 / 10000000;
        $dt = new \DateTime("1601-01-01", new \DateTimeZone("UTC"));
        $dt->modify("+$seconds seconds");
        return $dt;
    }
}

class String8Loader implements LoaderInterface {
    public function load(string $value, array $options = []): string {
        $encodings = $options['encodings'] ?? ['cp1252'];
        foreach ($encodings as $enc) {
            $converted = @mb_convert_encoding($value, 'UTF-8', $enc);
            if ($converted !== false) {
                return $converted;
            }
        }
        return mb_convert_encoding($value, 'UTF-8', 'cp1252');
    }
}

class UnicodeLoader implements LoaderInterface {
    public function load(string $value, array $options = []): string {
        return mb_convert_encoding($value, 'UTF-8', 'UTF-16LE');
    }
}

class BinaryLoader implements LoaderInterface {
    public function load(string $value, array $options = []): string {
        return $value;
    }
}

class EmbeddedMessageLoader implements LoaderInterface {
    public function load(string $value, array $options = []): string {
        return $value;
    }
}
