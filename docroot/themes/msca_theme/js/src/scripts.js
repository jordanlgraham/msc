// Custom scripts go here

(function ($, Drupal) {
  Drupal.behaviors.msca_theme = {
    attach: function (context, settings) {
      // Moving search block into masthead search menu link so bootstrap dropdown is happy.
      $('div#block-searchform', context).once(function() {
          $('div#block-searchform').appendTo('.search.nav-item');
      });

    }
  };
})(jQuery, Drupal);

