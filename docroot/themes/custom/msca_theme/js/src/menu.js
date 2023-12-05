(function ($, Drupal) {
  Drupal.behaviors.msca_menu = {
    attach: function (context, settings) {

      // Expand second and third level menus on click
      $(context).find('.has-submenu:not(.submenu-open)').each(function () {
        $(this).addClass('submenu-open').click(function (e) {
          if ($(e.target).closest('li').hasClass('has-submenu')) {
            // Find the parent of the current link and toggle its immediate child UL.
            $(e.target).closest('li')
              .find('> ul')
              .toggleClass('subnav-active');
            if (!window.matchMedia('(min-width: 768px)').matches) {
              e.preventDefault();
              e.stopPropagation();
            }
          }
        });
      });
    }
  };
})(jQuery, Drupal);
