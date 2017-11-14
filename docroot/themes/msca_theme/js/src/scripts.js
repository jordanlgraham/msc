// Custom scripts go here

(function ($, Drupal) {
  Drupal.behaviors.msca_theme = {
    attach: function (context, settings) {

      // Todo: implement panels for this.
      var node_classes = [
        'page-node-type-facility',
        'page-node-type-newsletter-article',
        'page-node-type-newsroom',
        'page-node-type-page',
        'page-node-type-resources',
        'page-node-type-scholarships',
        'page-node-type-video'
      ];

      $(node_classes).once().each(function() {
        var body = $('body');

        if (body.hasClass(this)) {
          $('div.newsletter-article-bottom-row').appendTo('.main-container > .row');
        }
      });

    }
  };
})(jQuery, Drupal);

