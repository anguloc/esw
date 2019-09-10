<?php
/**
 * Created by PhpStorm.
 * User: gk
 * Date: 2019/5/16
 * Time: 22:52
 */

namespace Esw\Tetris\Service;


class Stage
{
    private $config = [];

    private $abscissa = 8;
    private $ordinate = 20;

    private $matrix = [];

    public function __construct()
    {
        $this->initMatrix();
    }

    private function initMatrix()
    {
        $this->matrix = [];
    }



}