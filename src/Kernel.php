<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getCacheDir(): string
    {
        if (isset($_SERVER['APP_CACHE_DIR'])) {
            return $_SERVER['APP_CACHE_DIR'].'/'.$this->environment;
        }

        return parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        if (isset($_SERVER['APP_LOG_DIR'])) {
            return $_SERVER['APP_LOG_DIR'];
        }

        return parent::getLogDir();
    }
}
