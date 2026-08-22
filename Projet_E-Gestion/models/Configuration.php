<?php
class Configuration{
    public $con;
    public $username,$login,$pwd;

    function __construct(){
        $this->con=Connexion::getInstance()->getPDO();
    }
}

