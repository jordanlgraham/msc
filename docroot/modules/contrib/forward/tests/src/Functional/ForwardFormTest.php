<?php

namespace Drupal\Tests\forward\Functional;

/**
 * Test the Forward form.
 *
 * @group forward
 */
class ForwardFormTest extends ForwardTestBase {

  /**
   * Test the Forward form.
   */
  public function testForwardForm() {
    // Navigate to the Forward Form.
    $article = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalLogin($this->forwardUser);
    $this->drupalGet('node/' . $article->id());
    $this->assertText('Forward this article to a friend', 'The article has a Forward link.');
    $this->drupalGet('/forward/node/' . $article->id());
    $this->assertText('Forward this article to a friend', 'The Forward form displays for an article.');

    // Submit the Forward form.
    $edit = [
      'name' => 'Test Forwarder',
      'recipient' => 'test@test.com',
      'message' => 'This is a test personal message.',
    ];
    $this->drupalPostForm(NULL, $edit, 'Send Message');
    $this->assertText('Thank you for spreading the word about Drupal.', 'The Forward form displays a thank you message after submit.');

    // Submit the Forward form without a recipient.
    $this->drupalGet('/forward/node/' . $article->id());
    $edit = [
      'name' => 'Test Forwarder',
      'message' => 'This is a test personal message.',
    ];
    $this->drupalPostForm(NULL, $edit, 'Send Message');
    $this->assertText('Send to field is required.', 'The Forward form displays an error message when the recipient is blank.');

    // Submit the Forward form without a personal message when required.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/user-interface/forward');
    $edit = [
      'forward_personal_message' => 2,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');
    $this->drupalLogin($this->forwardUser);
    $this->drupalGet('/forward/node/' . $article->id());
    $edit = [
      'name' => 'Test Forwarder',
      'recipient' => 'test@test.com',
    ];
    $this->drupalPostForm(NULL, $edit, 'Send Message');
    $this->assertText('Your personal message field is required.', 'The Forward form displays an error message when the message is blank and one is required.');

    // Submit the Forward form without a personal message when optional.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/user-interface/forward');
    $edit = [
      'forward_personal_message' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');
    $this->drupalLogin($this->forwardUser);
    $this->drupalGet('/forward/node/' . $article->id());
    $edit = [
      'name' => 'Test Forwarder',
      'recipient' => 'test@test.com',
    ];
    $this->drupalPostForm(NULL, $edit, 'Send Message');
    $this->assertNoText('Your personal message field is required.', 'The Forward form does not display an error message when the message is blank and optional.');
  }

}
