<?php


namespace Craft;


class Charge_SecurityService extends BaseApplicationComponent
{


    public function encode($arr)
    {
        return base64_encode(craft()->security->encrypt(serialize($arr)));
    }

    public function decode($str = '')
    {
        if($str == '') return null;

        return unserialize(craft()->security->decrypt(base64_decode($str)));
    }

}