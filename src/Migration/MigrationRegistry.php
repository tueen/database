<?php

declare(strict_types=1);

namespace Tueen\Database\Migration;

final class MigrationRegistry
{
    /**
     * @var array<string, string> Namespace => Directory path
     */
    private array $paths = [];

    /**
     * @param array<string, string> $initialPaths
     */
    public function __construct(array $initialPaths = [])
    {
        foreach ($initialPaths as $namespace => $path) {
            $this->register($namespace, $path);
        }
    }

    public function register(string $namespace, string $path): self
    {
        $this->paths[rtrim($namespace, '\\')] = rtrim($path, '/\\');
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Finds all migration classes across registered paths.
     *
     * @return list<class-string>
     */
    public function discoverMigrationClasses(): array
    {
        $classes = [];

        foreach ($this->paths as $namespace => $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $files = scandir($dir) ?: [];
            foreach ($files as $file) {
                if (str_ends_with($file, '.php') && str_starts_with($file, 'Version')) {
                    $className = substr($file, 0, -4);
                    $fullClass = $namespace . '\\' . $className;
                    if (class_exists($fullClass)) {
                        $classes[] = $fullClass;
                    } else {
                        // Attempt to require the file
                        require_once $dir . DIRECTORY_SEPARATOR . $file;
                        if (class_exists($fullClass)) {
                            $classes[] = $fullClass;
                        }
                    }
                }
            }
        }

        sort($classes);

        return $classes;
    }
}
