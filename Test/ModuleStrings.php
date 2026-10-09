<?php

declare(strict_types=1);

namespace Supertext\Translation\Test;

/**
 * Finds the module's translatable phrases, like bin/magento i18n:collect-phrases: __() in PHP
 * and templates, labels and comments in system.xml, ACL titles, UI component labels, plus the
 * English messages of SupertextException and the content type labels.
 */
final class ModuleStrings
{
    private const QUOTED = "'((?:[^'\\\\]|\\\\.)*)'";

    /** @return list<string> */
    public static function all(string $moduleDir): array
    {
        $strings = [];

        foreach (self::files($moduleDir) as $file) {
            $code = (string) file_get_contents($file);

            if (str_ends_with($file, '.xml')) {
                array_push($strings, ...self::fromXml($file, $code));

                continue;
            }

            $patterns = ['/__\(\s*' . self::QUOTED . '/', '/new SupertextException\(\s*' . self::QUOTED . '/'];

            if (str_ends_with($file, 'EntityTypes.php')) {
                $patterns[] = "/'label'\\s*=> " . self::QUOTED . '/';
            }

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $code, $matches);
                array_push($strings, ...array_map('stripslashes', $matches[1]));
            }

            // HTTP error messages of the API client: the arms of its "$message = match" block.
            if (preg_match('/\$message = match \(true\) \{(.*?)\};/s', $code, $block)) {
                preg_match_all('/=> ' . self::QUOTED . ',/', $block[1], $matches);
                array_push($strings, ...array_map('stripslashes', $matches[1]));
            }
        }

        $strings = array_values(array_unique($strings));
        sort($strings);

        return $strings;
    }

    /** @return list<string> */
    private static function fromXml(string $file, string $code): array
    {
        $xml = simplexml_load_string($code);

        if ($xml === false) {
            return [];
        }

        $strings = [];

        // <label translate="true">…</label> (UI components)
        foreach ($xml->xpath('//*[@translate="true"]') ?: [] as $node) {
            $strings[] = trim((string) $node);
        }

        // translate="label comment" or translate="title" (system.xml, acl.xml)
        foreach ($xml->xpath('//*[@translate and @translate!="true"]') ?: [] as $node) {
            foreach (preg_split('/\s+/', trim((string) $node['translate'])) as $name) {
                $value = isset($node[$name]) ? (string) $node[$name] : (isset($node->{$name}) ? (string) $node->{$name} : '');

                if (trim($value) !== '') {
                    $strings[] = trim($value);
                }
            }
        }

        return $strings;
    }

    /** @return list<string> */
    private static function files(string $moduleDir): array
    {
        $files = [];

        foreach (['Api', 'Block', 'Controller', 'Model', 'etc', 'view'] as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($moduleDir . '/' . $dir, \FilesystemIterator::SKIP_DOTS));

            foreach ($it as $file) {
                if (preg_match('/\.(php|phtml|xml)$/', $file->getPathname())) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }
}
