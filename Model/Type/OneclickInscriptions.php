<?php
namespace Transbank\Webpay\Model\Type;
use Transbank\Webpay\Helper\Inscriptions;

class OneclickInscriptions
{
  protected $getInscriptions;
  public function __construct(Inscriptions $getInscriptions){
    $this->getInscriptions = $getInscriptions;
  }
  public function afterInitCheckout()
  {
      /*
       * We want to load the correct customer oneclick inscriptions
       */
      return $this->getInscriptions->getInscriptions();
  }
}
?>
