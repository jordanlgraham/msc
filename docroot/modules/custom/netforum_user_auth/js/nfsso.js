(function ($, Drupal) {
  Drupal.behaviors.nfSso = {
    attach: function (context, settings) {
      const elements = once('nfsso', 'body', context);
      elements.foreach(function () {
        if (!settings.hasOwnProperty('nfsso')) {
          return;
        }
        // Log in to NF by creating an image with a special token URL.
        loginImg = new Image();
        loginImg.src = settings.nfsso.login_url;
        // POST to the token expiration endpoint.
        $.post(settings.nfsso.expire_endpoint,
          {
            token: settings.nfsso.sso_token,
            csrf: settings.nfsso.csrf_token
          }, function () {
            // Perform the redirect.
            document.getElementById('redirect-link').click();
          });
      });
    }
  };
})(jQuery, Drupal);
