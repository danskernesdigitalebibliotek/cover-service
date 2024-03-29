<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot()
    {
        parent::boot();
        $timezone = $this->getContainer()->getParameter('timezone');
        if (!is_string($timezone)) {
            throw new \InvalidArgumentException('Timezone parameter is not a string');
        }
        date_default_timezone_set($timezone);
    }
}
