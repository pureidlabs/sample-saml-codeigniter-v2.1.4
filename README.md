# CodeIgniter v2.1.4 - PureAUTH SAML Demo Application

## Test Running Application

```sh
## runing with docker compose
docker-compose -f docker-compose.yml up --build
# or run in background process 
docker-compose -f docker-compose.yml up --build -d
```
```sh
app running in port 8181 ---> cek in browser
```

## Prerequisites

To support SAML authentication in CodeIgniter, we need to download two supporting PHP libraries.

- [SAML PHP Toolkit](https://github.com/onelogin/php-saml/releases/latestm) by OneLogin
- [Xmlseclibs](https://github.com/robrichards/xmlseclibs/releases/tag/3.1.0) by robrichards


## Implementation

### 1. Include external SAML libraries in Project

a. Create a new directory called ***“saml”*** under *application* directory.

b. Paste the recently downloaded two PHP libraries into the ***saml*** directory. Now the directory structure will look like this.

    saml
    |
    |___php-saml
    |   |__...
    |
    |___xmlseclibs
    |   |__...


c. The ***php-saml*** library depends on the ***xmlseclibs*** library. We need to autoload all the ***xmlseclibs*** classes before loading ***php-saml*** classes. To autoload xmlseclibs classes, Open ***_toolkit_loader.php*** file which is located under the ***php-saml*** directory.


```php
<?php


// Create an __autoload function
// (can conflicts other autoloaders)
// http://php.net/manual/en/language.oop5.autoload.php


// Load composer vendor folder if any
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
require __DIR__ . '/vendor/autoload.php';
}

// Load xmlseclibs


$xmlseclibsSrcDir = '/public/application/saml/xmlseclibs/src';

include_once $xmlseclibsSrcDir.'/XMLSecEnc.php';
include_once $xmlseclibsSrcDir.'/XMLSecurityDSig.php';
include_once $xmlseclibsSrcDir.'/XMLSecurityKey.php';
include_once $xmlseclibsSrcDir.'/Utils/XPath.php';


// Load php-saml
$libDir = __DIR__ . '/src/Saml2/';

```

Update ***$xmlseclibsSrcDir*** variable value to the path of *saml/xmlseclibs/src* directory.


### 2. Creating a new controller to handle SAML Authentication

a. In ***application > controllers*** directory, add a new file ***auth.php***

b. To support SAML flow we need to implement 3 endpoints as following:

```php

<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');


require_once APPPATH.'/saml/php-saml/_toolkit_loader.php';


class Auth extends CI_Controller {


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


```

c. Configuration of PureAUTH SAML

1. Visit [PureAUTH N4CER Dashboard](http://live.pureauth.io).
2. Go to *Applications* and click on *Add Application*
3. Fill following details

    1. Enter Application Name
    2. Select Corporate Email in Dataset for Email dropdown
    3. SAML RESPONSE URL (ACS URL)will be the ***/auth/acs*** endpoint of your application. For Example: http://localhost:8181/auth/acs
    4. AUDIENCE (ENTITY ID) will be the ***/auth/login*** endpoint of your application. For Example: http://localhost:8181/auth/login
    5. SAML LOGOUT RESPONSE URL (SLO URL) with be the ***/auth/logout*** endpoint of your application. For Example: http://localhost:8181/auth/logout
    6. To Support IdP Initiated Flow: APP LOGIN URL will be the ***/auth/login*** endpoint of your application. For Example: http://localhost:8181/auth/login
    7. Enable SIGN ASSERTION checkbox and Save Changes.

Now, we have to configure the SP and IdP in the ***php-saml*** library.

- Edit ***settings.php*** file which located under ***application/saml/php-saml*** 

```php
<?php


$settings = array(
   // If 'strict' is True, then the PHP Toolkit will reject unsigned
   // or unencrypted messages if it expects them signed or encrypted
   // Also will reject the messages if not strictly follow the SAML
   // standard: Destination, NameId, Conditions ... are validated too.
   'strict' => true,


   // Enable debug mode (to print errors)
   'debug' => false,


   // Set a BaseURL to be used instead of try to guess
   // the BaseURL of the view that process the SAML Message.
   // Ex. http://sp.example.com/
   //     http://example.com/sp/ 
   'baseurl' => 'https://BASE_URL/auth',  // For Example: http://localhost:8181/auth
 

   // Service Provider Data that we are deploying
   'sp' => array(
       // Identifier of the SP entity  (must be a URI)
       'entityId' => 'http://localhost:8181/auth/login',
       // Specifies info about where and how the <AuthnResponse> message MUST be
       // returned to the requester, in this case our SP.
       'assertionConsumerService' => array(
           // URL Location where the <Response> from the IdP will be returned
           'url' => 'http://localhost:8181/auth/acs',
           // SAML protocol binding to be used when returning the <Response>
           // message.  Onelogin Toolkit supports for this endpoint the
           // HTTP-POST binding only
           'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
       ),
      
       // Specifies info about where and how the <Logout Response> message MUST be
       // returned to the requester, in this case our SP.
       'singleLogoutService' => array(
                  // URL Location where the <Response> from the IdP will be returned
           'url' => 'http://localhost:8181/auth/logout',
           // SAML protocol binding to be used when returning the <Response>
           // message.  Onelogin Toolkit supports for this endpoint the
           // HTTP-Redirect binding only
           'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
       ),
       // Specifies constraints on the name identifier to be used to
       // represent the requested subject.
       // Take a look on lib/Saml2/Constants.php to see the NameIdFormat supported
       'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
   ),


   // Identity Provider Data that we want connect with our SP
   'idp' => array(
       // Identifier of the IdP entity  (must be a URI)
       'entityId' => 'LOGIN_URL_RECIEVED_FROM_PUREAUTH',
       // SSO endpoint info of the IdP. (Authentication Request protocol)
       'singleSignOnService' => array(
           // URL Target of the IdP where the SP will send the Authentication Request Message
           'url' => 'LOGIN_URL_RECEIVED_FROM_PUREAUTH',
           // SAML protocol binding to be used when returning the <Response>
           // message.  Onelogin Toolkit supports for this endpoint the
           // HTTP-Redirect binding only
           'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
       ),
       // SLO endpoint info of the IdP.
       'singleLogoutService' => array(
           // URL Location of the IdP where the SP will send the SLO Request
           'url' => 'LOGOUT_URL_RECEIVED_FROM_PUREAUTH',
           // URL location of the IdP where the SP SLO Response will be sent (ResponseLocation)
           // if not set, url for the SLO Request will be used
            'responseUrl' => '',
           // SAML protocol binding to be used when returning the <Response>
           // message.  Onelogin Toolkit supports for this endpoint the
           // HTTP-Redirect binding only
           'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
       ),
       // Public x509 certificate of the IdP
       'x509cert' => 'FORMATED_X509_CERTIFICATE',
   ),
);
```

Note: You need to replace all URIs to actual URIs. At my end, CodeIgniter project running at http://localhost:8181. Replace localhost URIs with your project URIs.

### To Format X509 Certificate, follow below steps:

- Copy X.509 Certificate from PureAUTH N4CER Dashboard.
- Visit [https://www.browserling.com/tools/remove-all-whitespace](https://www.browserling.com/tools/remove-all-whitespace)
- Paste your X509 certificate to remove all whitespaces.
- Remove ```-----BEGIN CERTIFICATE-----``` and ```-----END CERTIFICATE-----``` lines from the certificate.
- Now, you can paste the certificate in *```settings.php```* file.

