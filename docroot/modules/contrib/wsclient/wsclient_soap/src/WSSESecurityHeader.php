<?php
namespace Drupal\wsclient_soap;

/**
 * Class WSSESecurityHeader
 *
 * A 'Security' Soap header block to support
 * Web Services Security UsernameToken Profile
 * http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0.pdf
 */
class WSSESecurityHeader extends SoapHeader {
  // Thanks to http://stackoverflow.com/a/20498574/213577
  /**
   * Create the header block.
   *
   * @param string $username
   *   Username.
   * @param string $password
   *   Password.
   */
  public function __construct($username, $password) {
    $wsse_ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    $security = new SoapVar(
      array(new SoapVar(
        array(
          new SoapVar($username, XSD_STRING, NULL, NULL, 'Username', $wsse_ns),
          new SoapVar($password, XSD_STRING, NULL, NULL, 'Password', $wsse_ns),
        ),
        SOAP_ENC_OBJECT,
        NULL,
        NULL,
        'UsernameToken',
        $wsse_ns
        )),
      SOAP_ENC_OBJECT
    );
    parent::__construct($wsse_ns, 'Security', $security, FALSE);
  }
}
