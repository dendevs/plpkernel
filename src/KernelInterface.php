<?php
namespace DenDev\Plpkernel;


interface KernelInterface
{
    /**
     * Oblige la presence d'une configuration de base.
     */
    public function get_default_configs();

}
