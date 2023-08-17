<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH.'/saml/php-saml/_toolkit_loader.php';




class Auth extends CI_Controller {

	/**
	 * Index Page for this controller.
	 *
	 * Maps to the following URL
	 * 		http://example.com/index.php/welcome
	 *	- or -  
	 * 		http://example.com/index.php/welcome/index
	 *	- or -
	 * Since this controller is set as the default controller in 
	 * config/routes.php, it's displayed at http://example.com/
	 *
	 * So any other public methods not prefixed with an underscore will
	 * map to /index.php/welcome/<method_name>
	 * @see http://codeigniter.com/user_guide/general/urls.html
	 */
	
	public function login()
	{
		// Initilize SAMLAuthRequest. It will redirect to PureAUTH Authentication Page
		// Initiate Auth Object
		$auth = new \OneLogin\Saml2\Auth();
		$auth->login();
	}

	public function acs()
	{
		// ACS Endpoint | PureAUTH will send SAMLResponse at this endpoint.

		try {
			if (isset($_POST['SAMLResponse'])) {
				$samlSettings = new \OneLogin\Saml2\Settings();
				$samlResponse = new \OneLogin\Saml2\Response($samlSettings, $_POST['SAMLResponse']);
				if ($samlResponse->isValid()) {
					echo 'You are: ' . htmlentities($samlResponse->getNameId()) . '<br>';
					$attributes = $samlResponse->getAttributes();
					
					// SetUserData in Session
					$userData = array(
						'email' => $samlResponse->getNameId(),
						'attributes' => $attributes
					);

					// If Attributes
					if (!empty($attributes)) {
						echo '<br>You have the following attributes:<br><br>';
						echo '<table border="1"><thead><th>Name</th><th>Values</th></thead><tbody>';
						foreach ($attributes as $attributeName => $attributeValues) {
							echo '<tr><td>' . htmlentities($attributeName) . '</td><td>';
							foreach ($attributeValues as $attributeValue) {
								echo '<p>' . htmlentities($attributeValue) . '</p>';
							}
							echo '</td></tr>';
						}
						echo '</tbody></table>';
					}
				}
				else {
					echo 'Invalid SAML Response';
				}
			}
			else {
				echo 'No SAML Response found in POST.';
			}
		}
		catch (Exception $e) {
			echo 'Invalid SAML Response: ' . htmlentities($e->getMessage());
		}
	}

	public function logout()
	{
		// Optional Logout Endpoint

		$samlSettings = new \OneLogin\Saml2\Settings();
		// Get IdP Configuration Data from settings.php
		$idpData = $samlSettings->getIdPData();

		// Check if singleLogoutService url provided or not
		if (isset($idpData['singleLogoutService']) && isset($idpData['singleLogoutService']['url'])) {
			$sloUrl = $idpData['singleLogoutService']['url'];
		} else {
			throw new Exception("The IdP does not support Single Log Out");
		}

		if (isset($_SESSION['IdPSessionIndex']) && !empty($_SESSION['IdPSessionIndex'])) {
			$logoutRequest = new \OneLogin\Saml2\LogoutRequest($samlSettings, null, $_SESSION['IdPSessionIndex']);
		} else {
			$logoutRequest = new \OneLogin\Saml2\LogoutRequest($samlSettings);
		}

		$samlRequest = $logoutRequest->getRequest();

		$parameters = array('SAMLRequest' => $samlRequest);

		$url = \OneLogin\Saml2\Utils::redirect($sloUrl, $parameters, true);

		header("Location: $url");

	}
}

/* End of file auth.php */
/* Location: ./application/controllers/auth.php */