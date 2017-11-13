(function ($, Drupal) {
  Drupal.behaviors.mscaDonate = {
    attach: function (context, settings) {
      var $this = this;
      if (!settings.hasOwnProperty('mscaDonate')) {
        return;
      }
      $('form.donate-form', context).once('donate').each(function (delta, form) {
        var $form = $(form);
        var settings = drupalSettings.mscaDonate;
        $this.toggleValidationMessage($form);
        $form.find('input.amount').on('keydown', function() {
          $this.toggleValidationMessage($form);
        });
        var key = $form.data('donate');
        paypal.Button.render({

          env: settings.mode,
          client: {
            sandbox:    settings.credentials.sandbox,
            production: settings.credentials.production,
          },

          // Show the buyer a 'Pay Now' button in the checkout flow
          commit: true,

          validate: function(actions) {
            $this.isValid($form, actions);
          },

          onClick: function() {
            $this.toggleValidationMessage($form)
          },
          // payment() is called when the button is clicked
          payment: function(data, actions) {

            // Make a call to the REST api to create the payment
            return actions.payment.create({
              payment: {
                transactions: [
                  {
                    amount: { total: $this.getAmount($form), currency: 'USD' }
                  }
                ]
              },
              experience: {
                input_fields: {
                  no_shipping: 1
                }
              }
            });
          },

          onAuthorize: function(data, actions) {
            return actions.payment.execute().then(function(data) {
              // Success message.
              var msg = $form.find('.success-template').html();
              var replacements = {
                '@first': data.payer.payer_info.first_name,
                '@last': data.payer.payer_info.first_name
              };
              msg = Drupal.formatString(msg, replacements);
              $form.find('.success-message').html(msg);
            });
          }

        }, '#' + key);
      });
    },

    getAmount: function(form) {
      return form.find('input.amount').val();
    },

    isValid: function(form) {
      var regex = /^[1-9]\d*(?:\.\d{0,2})?$/;
      return regex.test(form.find('.amount').val()  );
    },

    toggleValidationMessage: function (form) {
      var indicator = form.find('.invalid-amount');
      var input = form.find('input.amount');
      if (!this.isValid(form)) {
        indicator.show();
        input.addClass('error');
      }
      else {
        indicator.hide();
        input.removeClass('error');
      }
    }
  };

})(jQuery, Drupal);
