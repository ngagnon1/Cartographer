<?php

namespace Ncg\CartographerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class NcgCartographerBundle extends Bundle
{

  public function __construct(){
    Core\Util::initialize();
  }

}

