<?php
namespace exface\ModxCmsConnector\CommonLogic\Security;

use exface\ModxCmsConnector\CmsConnectors\Modx;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Factories\UserFactory;
use exface\Core\Interfaces\UserInterface;

/**
 * Special authentication token for the ModxCmsAuthenticator.
 * 
 * @author Andrej Kabachnik
 *
 */
class ModxCmsAuthToken implements AuthenticationTokenInterface
{
    private $modxConnector;
    
    private $username = null;
    
    private $facade = null;
    
    public function __construct(Modx $modxConnector, string $username, FacadeInterface $facade = null)
    {
        $this->modxConnector = $modxConnector;
        $this->facade = $facade;
        $this->username = $username;
    }
    
    public function getUser() : UserInterface
    {
        return UserFactory::createFromModel($this->modxConnector->getWorkbench(), $this->getUsername());
    }
    
    public function getUsername() : ?string
    {
        return $this->username;
    }
    
    public function getFacade() : ?FacadeInterface
    {
        return $this->facade;
    }
    
    public function isAnonymous() : bool
    {
        return $this->username === '';
    }
    
    public function getCmsConnector() : Modx
    {
        return $this->modxConnector;
    }
}