<?php

namespace Drupal\Tests\apitools\Traits;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Test\AssertMailTrait;

trait EmailTestTrait {

  use AssertMailTrait;

  protected $currentMailSender;

  protected $currentMailSenderOverride;

  protected $currentMailFormatterOverride;

  protected $testMailSender = 'test_mail_collector';

  protected $testMailFormatter = 'test_mail_collector';

  /**
   * @see ExistingSiteBase::setUp()
   *
   * TODO: if using ::drupalPostForm this override will not work if there is
   * a local.settings.php file.
   */
  protected function setUpEmail() {
    // We should really check if the module exists.
    $mailsystem = $this->container->get('config.factory')->getEditable('mailsystem.settings');
    $defaults = $mailsystem->get('defaults');
    $this->currentMailSender = $defaults['sender'];
    $defaults['sender'] = $this->testMailSender ?? 'php_mail';
    $mailsystem->set('defaults', $defaults)->save();
    // If the settings.local.php has an override, store that, but override that.
    if (isset($GLOBALS['config']['mailsystem.settings']['defaults']['sender'])) {
      $this->currentMailSenderOverride = $GLOBALS['config']['mailsystem.settings']['defaults']['sender'];
      // This global is set and cannot be overridden with just the editable config.
      $GLOBALS['config']['mailsystem.settings']['defaults']['sender'] = $this->testMailSender ?? 'php_mail';
    }
    if (isset($GLOBALS['config']['mailsystem.settings']['defaults']['formatter'])) {
      $this->currentMailFormatterOverride = $GLOBALS['config']['mailsystem.settings']['defaults']['formatter'];
      // This global is set and cannot be overridden with just the editable config.
      $GLOBALS['config']['mailsystem.settings']['defaults']['formatter'] = $this->testMailFormatter ?? 'php_mail';
    }

    $this->clearMails();
  }

  private function _doMailHasText($values, $mail) {
    foreach ($values as $key => $value) {
      // Normalize whitespace, as we don't know what the mail system might have
      // done. Any run of whitespace becomes a single space.
      $normalized_mail = preg_replace('/\s+/', ' ', $mail[$key]);
      $normalized_string = preg_replace('/\s+/', ' ', $value);
      $values_found = (FALSE !== strpos($normalized_mail, $normalized_string));
      if (!$values_found) {
        // As soon as we get one false, then nothing was found.
        return FALSE;
      }
    }
    // Assume everything was found.
    return TRUE;
  }

  /**
   * Adds ability to search previous emails by multiple values.
   *
   * @param array $values
   * @param $email_depth
   * @param string $message
   * @param string $group
   * @return mixed
   *
   * @see AssertMailTrait::assertMailString()
   */
  protected function assertMailArray(array $values, $email_depth, $message = '', $group = 'Other') {
    $mails = $this->getMails();
    $string_found = FALSE;
    // Cast MarkupInterface objects to string.
    for ($i = count($mails) - 1; $i >= count($mails) - $email_depth && $i >= 0; $i--) {
      $mail = $mails[$i];
      $string_found = $this->_doMailHasText($values, $mail);
      // As soon as we find a value, break out and return a TRUE assertion.
      if ($string_found) {
        break;
      }
    }
    if (!$message) {
      $message = new FormattableMarkup('Expected text found in @fields of email message: "@expecteds".', [
        '@fields' => join('|', array_keys($values)),
        '@expected' => join('|', array_values($values)),
      ]);
    }
    return $this->assertTrue($string_found, $message, $group);
  }

  /**
   * @see ExistingSiteBase::tearDown()
   */
  protected function tearDownEmail() {
    $mailsystem = $this->container->get('config.factory')->getEditable('mailsystem.settings');
    $defaults = $mailsystem->get('defaults');
    $defaults['sender'] = $this->currentMailSender ?: 'php_mail';
    $mailsystem->set('defaults', $defaults)
      ->save();
  }

  protected function clearMails() {
    // Clear out mail collector before run.
    $this->container->get('state')->delete('system.test_mail_collector');
  }
}
