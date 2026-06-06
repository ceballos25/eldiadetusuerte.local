<?php
declare(strict_types=1);

namespace App\Shared\Config;

final class DynamicConfig
{
    private const CACHE_TTL_SECONDS = 120;

    private static function cacheFile(): string
    {
        return \appDataPath('cache/settings.json');
    }

    /** @return array<string, string> */
    public function all(): array
    {
        $cached = $this->fromCache();
        if ($cached !== null) {
            return $cached;
        }

        $settings = [];
        try {
            $rows = \Db::fetchAll(
                'SELECT key_setting, value_setting FROM settings ORDER BY id_setting ASC'
            );
            foreach ($rows as $row) {
                $settings[(string)$row->key_setting] = (string)$row->value_setting;
            }
        } catch (\Throwable) {
            $settings = [];
        }

        $this->writeCache($settings);

        return $settings;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    public function flush(): void
    {
        @unlink(self::cacheFile());
    }

    /** @return array<string, string>|null */
    private function fromCache(): ?array
    {
        $cacheFile = self::cacheFile();
        if (!is_file($cacheFile)) {
            return null;
        }

        $mtime = @filemtime($cacheFile);
        if ($mtime === false || (time() - $mtime) > self::CACHE_TTL_SECONDS) {
            return null;
        }

        $json = @file_get_contents($cacheFile);
        if ($json === false || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, string> $settings */
    private function writeCache(array $settings): void
    {
        @file_put_contents(self::cacheFile(), json_encode($settings, JSON_UNESCAPED_UNICODE));
    }
}
