(function ($, Drupal) {
  Drupal.behaviors.msca_menu = {
    attach: function attach(context, settings) {

      // Expand second and third level menus on click
      $(context).find('.has-submenu').each(function () {
        if (!$(this).data('processed')) {
          $(this).data('processed', true);
          $(this).click(function (e) {
            if ($(e.target).closest('li').hasClass('has-submenu')) {
              // Find the parent of the current link and toggle it's immediate child UL.
              // Another safeguard to keep parent classes from toggling
              $(e.target).closest('li').toggleClass('submenu-open');

              $(e.target).closest('li').find('> ul').toggleClass('subnav-active');
              if (!window.matchMedia('(min-width: 768px)').matches) {
                e.preventDefault();
                e.stopPropagation();
              }
            }
          });
        }
      });
    }
  };
})(jQuery, Drupal);
