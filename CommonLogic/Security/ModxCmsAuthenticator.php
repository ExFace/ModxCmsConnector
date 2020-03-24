<?php
namespace exface\ModxCmsConnector\CommonLogic\Security;

use exface\ModxCmsConnector\CmsConnectors\Modx;
use exface\Core\Interfaces\Security\AuthenticatorInterface;
use exface\Core\Interfaces\Security\AuthenticationTokenInterface;
use exface\Core\Interfaces\WorkbenchInterface;
use exface\Core\Events\Security\OnBeforeAuthenticationEvent;
use exface\Core\Exceptions\Security\AuthenticationFailedError;
use exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator;

/**
 * Tries to authenticates the CMS user in the Workbench on every OnBeforeAuthenticationEvent.
 * 
 * Creates a special ModxCmsAuthToken on every OnBeforeAuthenticationEvent and attempts to
 * authenticate it via Workbench. If this authenticator is included in the systems config
 * (see option `SECURITY.AUTHENTICATORS`), the token will get authenticated. Other
 * authenticators will not be able to autheticate the token because it only contains a
 * username.
 * 
 * @author Andrej Kabachnik
 *
 */
class ModxCmsAuthenticator extends AbstractAuthenticator
{    
    public function __construct(WorkbenchInterface $workbench)
    {
        parent::__construct($workbench);
        
        $workbench->eventManager()->addListener(OnBeforeAuthenticationEvent::getEventName(), function(OnBeforeAuthenticationEvent $event) {
            $token = new ModxCmsAuthToken($this->getCmsConnector(), $this->getCmsConnector()->getUserName(), $event->getFacade());
            try {
                $event->getWorkbench()->getSecurity()->authenticate($token);
            } catch (AuthenticationFailedError $e) {
                $event->getWorkbench()->getLogger()->logException($e, LoggerInterface::INFO);
            }
        });
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::authenticate()
     */
    public function authenticate(AuthenticationTokenInterface $token) : AuthenticationTokenInterface
    {
        $cms = $this->getCmsConnector();
        if ($cms !== $token->getCmsConnector()) {
            throw new AuthenticationFailedError($this, 'CMS authentication token was created for a different instance of the MODx CMS connector!');
        }
        if ($cms->getUserName() !== $token->getUsername()) {
            throw new AuthenticationFailedError($this, 'User mismatch: the token was created for different user, than the on authenticated in the CMS!');
        }
        return $token;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\SecurityManagerInterface::isAuthenticated()
     */
    public function isAuthenticated(AuthenticationTokenInterface $token) : bool
    {
        return $token->getUsername() === $this->getCmsConnector()->getUserName();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Security\AuthenticatorInterface::isSupported()
     */
    public function isSupported(AuthenticationTokenInterface $token) : bool
    {
        return $token instanceof ModxCmsAuthToken;
    }
    
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Security\Authenticators\AbstractAuthenticator::getNameDefault()
     */
    protected function getNameDefault() : string
    {
        return 'Authentication via Evolution CMS (ex. MODx v1)';
    }
    
    protected function getCmsConnector() : Modx
    {
        return $this->getWorkbench()->getCMS();
    }
}