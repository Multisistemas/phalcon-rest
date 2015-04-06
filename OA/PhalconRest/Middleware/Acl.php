<?php

namespace OA\PhalconRest\Middleware;

use OA\PhalconRest\Mvc\Micro,
	OA\PhalconRest\Exception,
	OA\PhalconRest\Services\ErrorService as ERR,
	Phalcon\Events\Event;

class Acl extends \Phalcon\Mvc\User\Plugin
{
	protected $_privateEndpoints = [
		"/users/search",
		"/users/me",
		"/users/logout",
		"/users/me/participationinvitations",
		"/users/me/pushtoken",
		"/projects",
		"/projects/{project_id}",
		"/projects/{project_id}/participants",
		"/projects/{project_id}/participants/{participant_id}",
		"/projects/{project_id}/participants/join",
		"/projects/{project_id}/participants/leave",
		"/projects/{project_id}/resources",
		"/projects/{project_id}/resources/{resource_id}",
		"/projects/{project_id}/items",
		"/projects/{project_id}/items/{item_id}",
		"/projects/{project_id}/mutationgroups/{mutation_group_id}",
		"/projects/{project_id}/mutationgroups",
	];

	protected $_publicEndpoints = ["/users/login", "/users/activate", "/users", "/docs", "/docs.json"];

	protected function _getAcl()
	{
		/**
		TODO: Put this in cache
		**/
		if (is_null($this->persistent->acl) || true)
		{
			$acl = new \Phalcon\Acl\Adapter\Memory();
			$acl->setDefaultAction(\Phalcon\Acl::DENY);

			$acl->addRole(new \Phalcon\Acl\Role('Public'));
			$acl->addRole(new \Phalcon\Acl\Role('Private'));

			// Allow All Roles to access the Public resources
			foreach($this->_publicEndpoints as $endpoint) {
				$acl->addResource(new \Phalcon\Acl\Resource('api'), $endpoint);
				$acl->allow('Public', 'api', $endpoint);
				$acl->allow('Private', 'api', $endpoint);
			}

			foreach($this->_privateEndpoints as $endpoint) {
				$acl->addResource(new \Phalcon\Acl\Resource('api'), $endpoint);
				$acl->allow('Private', 'api', $endpoint);
			}

			$this->persistent->set('acl', $acl);

		}

		return $this->persistent->acl;
	}

	public function beforeExecuteRoute(Event $event, Micro $app)
	{

		$role = $this->authservice->loggedIn() ? 'Private' : 'Public';

		// Get the current resource/endpoint from the micro app
		$resource = 'api';
		$endpoint = $app->getRouter()->getMatchedRoute()->getPattern();

		// Get the access control list
		$acl = $this->_getAcl();

		// See if they have permission
		$allowed = $acl->isAllowed($role, $resource, $endpoint);

		if ($allowed != \Phalcon\Acl::ALLOW)
		{
            if ($this->authservice->loggedIn()) {

            	throw new Exception(ERR::AUTH_FORBIDDEN);

            } else {

            	throw new Exception(ERR::AUTH_UNAUTHORIZED);

            }

			// Path not allowed!
			return false;
		}
	}
}
