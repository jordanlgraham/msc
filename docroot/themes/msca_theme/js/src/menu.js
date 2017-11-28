(function ($, Drupal) {
  Drupal.behaviors.msca_menu = {
    attach: function (context, settings) {

      // Expand second and third level menus on click
      $(context).find('.has-submenu').once('has-submenu').each(function() {
        $(this).click(function(e) {
          if ($(e.target).closest('li').hasClass('has-submenu')) {
            // Find the parent of the current link and toggle it's immediate child UL.
            // Another safeguard to keep parent classes from toggling
            $(e.target).closest('li').toggleClass('submenu-open');

            $(e.target).closest('li')
              .find('> ul')
              .toggleClass('subnav-active');
          }
        })
      });
    }
  };
})(jQuery, Drupal);


