<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb06413524e350059b16f5d2085252127
{
    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'RecycleBin\\AnonymousMembers\\' => 28,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'RecycleBin\\AnonymousMembers\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb06413524e350059b16f5d2085252127::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb06413524e350059b16f5d2085252127::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitb06413524e350059b16f5d2085252127::$classMap;

        }, null, ClassLoader::class);
    }
}
