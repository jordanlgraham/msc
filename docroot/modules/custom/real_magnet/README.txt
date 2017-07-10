Real Magnet

REST service that posts templated content to Real Magnet via their API (https://realmagnethelp.com/rest-api/)

INSTALLATION INSTRUCTIONS:

It assumed you have an account with RealMagnet.com.

1) Contact Real Magnet and request they enable the REST API service for your account (this can take 24-48 hours).

2) Enable module.

3) Configure credentials under Configuration > Web Services > Real Magnet.

4) Under Permissions, enable rights to config and send newsletter content to realmagnet.com.

THEME:

In order to customize the Newsletter content display, you can add the following templates to your default theme...

    node--newsletter--email.html.twig --> controls output sent to Real Magnet

    node--newsletter--full.html.twig --> controls output displayed on site.

WARNING:

Do NOT change the configuration of the two content types through the Drupal admin UI (Newsletter and Newsletter article).

Any changes made through the UI will be lost when this module is updated.

USAGE:

Once enabled, there will be two content types added: Newsletter and Newsletter article. The Newsletter article nodes are
entity reference fields in the Newsletter node. On the Newslettter node, there is a "Real Magnet" tab. A button on this
tab allows you to Send the current node to Real Magnet. One must then login to realmagnet.com in order to send the
newsletter via bulk email.

