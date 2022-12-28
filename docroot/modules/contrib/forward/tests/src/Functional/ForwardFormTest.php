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
    $this->assertSession()->pageTextContains('Forward this article to a friend');
    $this->drupalGet('/forward/node/' . $article->id());
    $this->assertSession()->pageTextContains('Forward this article to a friend');

    // Submit the Forward form.
    $edit = [
      'name' => 'Test Forwarder',
      'recipient' => 'test@test.com',
      'message' => 'This is a test personal message.',
    ];
    $this->submitForm($edit, 'Send Message');
    $this->assertSession()->pageTextContains('Thank you for spreading the word about Drupal.');

    // Submit the Forward form without a recipient.
    $this->drupalGet('/forward/node/' . $article->id());
    $edit = [
      'name' => 'Test Forwarder',
      'message' => 'This is a test personal message.',
    ];
    $this->submitForm($edit, 'Send Message');
    $this->assertSession()->pageTextContains('Send to field is required.');

    // Submit the Forward form without a personal message when required.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/user-interface/forward');
    $edit = [
      'forward_personal_message' => 2,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->drupalLogin($this->forwardUser);
    $this->drupalGet('/forward/node/' . $article->id());
    $edit = [
      'name' => 'Test Forwarder',
      'recipient' => 'test@test.com',
    ];
    $this->submitForm($edit, 'Send Message');
    $this->assertSession()->pageTextContains('Your personal message field is required.');

    // Submit the Forward form without a personal message when optional.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/user-interface/forward');
    $edit = [
      'forward_personal_message' => 1,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->drupalLogin($this->forwardUser);
    $this->drupalGet('/forward/node/' . $article->id());
    $edit = [
      'name' => 'Test Forwarder',
      'recipient' => 'test@test.com',
    ];
    $this->submitForm($edit, 'Send Message');
    $this->assertSession()->pageTextNotContains('Your personal message field is required.');
  }

}
