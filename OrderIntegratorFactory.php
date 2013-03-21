<?php

require_once "GiuseppeOrderIntegrator.php";

class OrderIntegratorFactory
{
	public static function getOrderIntegrator($origin)
	{
	  switch ($origin) 
	  {
            case 'Giuseppe':
                return new GiuseppeOrderIntegration();
            default:
                return null;
        }
	}
}

?>
