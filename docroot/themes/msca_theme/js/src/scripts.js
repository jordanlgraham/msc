// Custom scripts go here

(function ($, Drupal) {
  Drupal.behaviors.msca_theme = {
    attach: function (context, settings) {

      // Todo: implement panels for this.
        if ($(body).hasClass('page-node-type-newsletter-article') || $(body).hasClass('page-node-type-page')
          || $(body).hasClass('page-node-type-newsroom') || $(body).hasClass('page-node-type-scholarships')
          || $(body).hasClass('page-node-type-resources') || $(body).hasClass('page-node-type-facility')
          || $(body).hasClass('page-node-type-video')) {
          $('div.newsletter-article-bottom-row').appendTo('.main-container > .row');
        }

    }
  };
})(jQuery, Drupal);

