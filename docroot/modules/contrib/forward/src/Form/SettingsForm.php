<?php

namespace Drupal\forward\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure settings for this module.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'forward_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['forward.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('forward.settings');
    $settings = $config->get();

    // Forward Form.
    $form['forward_form'] = [
      '#type' => 'details',
      '#title' => $this->t('Forward Form'),
      '#open' => FALSE,
    ];
    $form['forward_form']['forward_form_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Page title'),
      '#default_value' => $settings['forward_form_title'],
      '#maxlength' => 255,
      '#description' => $this->t('Page title for the Forward form page.'),
      '#required' => TRUE,
    ];
    $form['forward_form']['forward_form_noindex'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate a noindex meta tag on the forward page (name="robots", content="noindex, nofollow")'),
      '#default_value' => $settings['forward_form_noindex'],
    ];
    $form['forward_form']['forward_form_instructions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Instructions'),
      '#default_value' => $settings['forward_form_instructions'],
      '#rows' => 5,
      '#description' => $this->t('The instructions to display above the form.  Replacement tokens may be used.  This field may contain HTML.'),
    ];
    $form['forward_form']['forward_form_allow_plain_text'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow plain text option'),
      '#default_value' => $settings['forward_form_allow_plain_text'],
      '#description' => $this->t('Should there be an option on the forward form to send the email as plain text?'),
    ];
    $form['forward_form']['form_display_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Form Fields'),
    ];
    $form['forward_form']['form_display_options']['forward_form_display_page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display a link to the page being forwarded'),
      '#default_value' => $settings['forward_form_display_page'],
    ];
    $form['forward_form']['form_display_options']['forward_form_display_subject'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the email message subject'),
      '#default_value' => $settings['forward_form_display_subject'],
    ];
    $form['forward_form']['form_display_options']['forward_form_display_body'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display the email introductory message text'),
      '#default_value' => $settings['forward_form_display_body'],
    ];
    $form['forward_form']['personal_messages'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Personal Message Field'),
    ];
    $form['forward_form']['personal_messages']['forward_personal_message'] = [
      '#type' => 'select',
      '#title' => $this->t('Personal message'),
      '#options' => [0 => 'Hidden', 1 => 'Optional', 2 => 'Required'],
      '#default_value' => $settings['forward_personal_message'],
      '#description' => $this->t('Choose whether the personal message field on the form will be hidden, optional or required.'),
    ];
    $form['forward_form']['personal_messages']['forward_personal_message_filter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow HTML in personal messages'),
      '#return_value' => 1,
      '#default_value' => $settings['forward_personal_message_filter'],
      '#states' => [
        'invisible' => [
          ':input[name=forward_personal_message]' => ['value' => 0],
        ],
      ],
    ];
    $form['forward_form']['personal_messages']['forward_personal_message_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed HTML tags'),
      '#default_value' => $settings['forward_personal_message_tags'],
      '#description' => $this->t('List of tags (separated by commas) that will be allowed if HTML is enabled above.  Defaults to: p,br,em,strong,cite,code,ul,ol,li,dl,dt,dd'),
      '#states' => [
        'invisible' => [
          [':input[name=forward_personal_message]' => ['value' => 0]],
          [':input[name=forward_personal_message_filter]' => ['checked' => FALSE]],
        ],
      ],
    ];
    $form['forward_form']['forward_max_recipients'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum allowed recipients'),
      '#default_value' => $settings['forward_max_recipients'],
      '#description' => $this->t('The maximum number of recipients for the email.'),
    ];
    $form['forward_form']['forward_form_confirmation'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Confirmation message'),
      '#default_value' => $settings['forward_form_confirmation'],
      '#rows' => 5,
      '#description' => $this->t('The thank you message displayed after the user successfully submits the form.  Replacement tokens may be used.'),
    ];
    // Defaults for Message to Recipient.
    $form['forward_email_defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Forward Email'),
      '#open' => FALSE,
    ];
    $form['forward_email_defaults']['forward_email_logo'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to custom logo'),
      '#default_value' => $settings['forward_email_logo'],
      '#maxlength' => 256,
      '#description' => $this->t('The path to the logo you would like to use instead of the site default logo. Example: sites/default/files/logo.png'),
      '#required' => FALSE,
    ];
    $form['forward_email_defaults']['forward_email_from_address'] = [
      '#type' => 'email',
      '#title' => $this->t('From address'),
      '#default_value' => $settings['forward_email_from_address'],
      '#maxlength' => 254,
      '#required' => TRUE,
    ];
    $form['forward_email_defaults']['forward_email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject line'),
      '#default_value' => $settings['forward_email_subject'],
      '#maxlength' => 256,
      '#description' => $this->t('Email subject line. Replacement tokens may be used.'),
      '#required' => TRUE,
    ];
    $form['forward_email_defaults']['forward_email_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Introductory message text'),
      '#default_value' => $settings['forward_email_message'],
      '#rows' => 5,
      '#description' => $this->t('Introductory text that appears above the entity being forwarded. Replacement tokens may be used. The sender may be able to add their own personal message after this.  This field may contain HTML.'),
    ];
    $form['forward_email_defaults']['forward_email_footer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('E-mail footer text'),
      '#default_value' => $settings['forward_email_footer'],
      '#rows' => 5,
      '#description' => $this->t('Replacement tokens may be used.'),
    ];
    // Post processing filters.
    $filter_options = [];
    $filter_options[''] = $this->t('- None -');
    foreach (filter_formats($this->currentUser()) as $key => $format) {
      $filter_options[$key] = $format->label();
    }
    if (count($filter_options) > 1) {
      $form['forward_filter_options'] = [
        '#type' => 'details',
        '#title' => $this->t('Filter'),
        '#open' => FALSE,
      ];
      $form['forward_filter_options']['forward_filter_format_html'] = [
        '#type' => 'select',
        '#title' => t('Filter format'),
        '#default_value' => $settings['forward_filter_format_html'],
        '#options' => $filter_options,
        '#description' => $this->t('Select a filter to apply to the email message body. A filter with <a href="http://drupal.org/project/pathologic">Pathologic</a> assigned to it will convert relative links to absolute links. &nbsp;<a href="http://drupal.org/project/modules">More filters</a>.'),
      ];
      $form['forward_filter_options']['forward_filter_format_plain_text'] = [
        '#type' => 'select',
        '#title' => t('Plain text filter format'),
        '#default_value' => $settings['forward_filter_format_plain_text'],
        '#options' => $filter_options,
        '#description' => $this->t('Select a filter to apply to the email message body when sending as plain text.'),
        '#states' => [
          'visible' => [
            ':input[name=forward_form_allow_plain_text]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
    // Access Control.
    $form['forward_access_control'] = [
      '#type' => 'details',
      '#title' => $this->t('Access Control'),
      '#open' => FALSE,
      '#description' => $this->t('The email build process normally uses anonymous visitor permissions to render the entity being forwarded.  This is appropriate for most sites.  If you bypass anonymous access control, and the person doing the forward is logged in, the permissions of the logged in account are used instead.  Bypassing anonymous access control creates a potential security risk because privileged information could be sent to people who are not authorized to view it.'),
    ];
    $form['forward_access_control']['forward_bypass_access_control'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bypass anonymous access control'),
      '#default_value' => $settings['forward_bypass_access_control'],
      '#description' => $this->t('<em>Warning: selecting this option has security implications.</em>'),
    ];
    // Flood Control.
    $form['forward_flood_control_options'] = [
      '#type' => 'details',
      '#title' => $this->t('Flood Control'),
      '#open' => FALSE,
    ];
    $form['forward_flood_control_options']['forward_flood_control_limit'] = [
      '#type' => 'select',
      '#title' => $this->t('Flood control limit'),
      '#default_value' => $settings['forward_flood_control_limit'],
      '#options' => [
        '1' => '1',
        '5' => '5',
        '10' => '10',
        '15' => '15',
        '20' => '20',
        '25' => '25',
        '30' => '30',
        '35' => '35',
        '40' => '40',
        '50' => '50',
      ],
      '#description' => $this->t('How many times a user can use the form in a one hour period. This will help prevent the forward module from being used for spamming.'),
    ];
    $form['forward_flood_control_options']['forward_flood_control_error'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Flood control error'),
      '#default_value' => $settings['forward_flood_control_error'],
      '#rows' => 5,
      '#description' => $this->t('This text appears if a user exceeds the flood control limit.  The value of the flood control limit setting will appear in place of @number in the message presented to users.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save all other settings.
    $this->configFactory->getEditable('forward.settings')
      ->set('forward_form_title', $form_state->getValue('forward_form_title'))
      ->set('forward_form_noindex', $form_state->getValue('forward_form_noindex'))
      ->set('forward_form_instructions', $form_state->getValue('forward_form_instructions'))
      ->set('forward_form_allow_plain_text', $form_state->getValue('forward_form_allow_plain_text'))
      ->set('forward_form_display_page', $form_state->getValue('forward_form_display_page'))
      ->set('forward_form_display_subject', $form_state->getValue('forward_form_display_subject'))
      ->set('forward_form_display_body', $form_state->getValue('forward_form_display_body'))
      ->set('forward_form_confirmation', $form_state->getValue('forward_form_confirmation'))
      ->set('forward_personal_message', $form_state->getValue('forward_personal_message'))
      ->set('forward_personal_message_filter', $form_state->getValue('forward_personal_message_filter'))
      ->set('forward_personal_message_tags', $form_state->getValue('forward_personal_message_tags'))
      ->set('forward_email_logo', $form_state->getValue('forward_email_logo'))
      ->set('forward_email_from_address', $form_state->getValue('forward_email_from_address'))
      ->set('forward_email_subject', $form_state->getValue('forward_email_subject'))
      ->set('forward_email_message', $form_state->getValue('forward_email_message'))
      ->set('forward_email_footer', $form_state->getValue('forward_email_footer'))
      ->set('forward_filter_format_html', $form_state->getValue('forward_filter_format_html'))
      ->set('forward_filter_format_plain_text', $form_state->getValue('forward_filter_format_plain_text'))
      ->set('forward_bypass_access_control', $form_state->getValue('forward_bypass_access_control'))
      ->set('forward_flood_control_limit', $form_state->getValue('forward_flood_control_limit'))
      ->set('forward_flood_control_error', $form_state->getValue('forward_flood_control_error'))
      ->set('forward_max_recipients', $form_state->getValue('forward_max_recipients'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
