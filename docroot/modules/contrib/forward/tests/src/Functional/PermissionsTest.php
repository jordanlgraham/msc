<?php

namespace Drupal\Tests\forward\Functional;

/**
 * Test the permissions.
 *
 * @group forward
 */
class PermissionsTest extends ForwardTestBase {

  /**
   * Test access to Forward links.
   */
  public function testPermissions() {
    // Users with access content permission cannot change settings.
    $this->drupalLogin($this->webUser);
    $this->drupalGet('admin/config/user-interface/forward');
    $this->assertResponse(403, 'Users with access content permission cannot change settings.');

    // Users with access forward permission cannot change settings.
    $this->drupalLogin($this->forwardUser);
    $this->drupalGet('admin/config/user-interface/forward');
    $this->assertResponse(403, 'Users with access forward permission cannot change settings.');

    // Users with administer forward permission can change settings.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/user-interface/forward');
    $this->assertResponse(200, 'Users with administer forward permission can change settings.');

    // Users without override email address permission cannot
    // change their email address on the Forward form.
    $article = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalLogin($this->forwardUser);
    $this->drupalGet('/forward/node/' . $article->id());
    $this->assertNoText('Your email address', 'Users without override email address permission cannot change their email address on the Forward form.');

    // Users with override email address permission can change
    // their email address on the Forward form.
    $overrideUser = $this->drupalCreateUser([
      'access content',
      'access forward',
      'override email address',
      'override flood control',
    ]);
    $article = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalLogin($overrideUser);
    $this->drupalGet('/forward/node/' . $article->id());
    $this->assertText('Your email address', 'Users with override email address permission can change their email address on the Forward form.');

    // Set flood control limit to 1.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/user-interface/forward');
    $edit = [
      'forward_flood_control_limit' => 1,
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    // Users without override flood control permission
    // cannot do more than 1 forward in an hour.
    $this->drupalLogin($this->forwardUser);
    $this->drupalGet('/forward/node/' . $article->id());
    $edit = [
      'name' => 'Test Forwarder',
      'recipient' => 'test@test.com',
      'message' => 'This is a test personal message.',
    ];
    $this->drupalPostForm(NULL, $edit, 'Send Message');
    $this->drupalGet('/forward/node/' . $article->id());
    $edit = [
      'name' => 'Test Forwarder',
      'recipient' => 'test@test.com',
      'message' => 'This is a test personal message.',
    ];
    $this->drupalPostForm(NULL, $edit, 'Send Message');
    $this->assertNoText('Thank you for spreading the word about Drupal.', 'Users without override flood control permission cannot do more than 1 forward in an hour.');

    // Users with override flood control permission
    // can do more than 1 forward in an hour.
    $this->drupalLogin($overrideUser);
    $this->drupalGet('/forward/node/' . $article->id());
    $edit = [
      'name' => 'Test Forwarder',
      'recipient' => 'test@test.com',
      'message' => 'This is a test personal message.',
    ];
    $this->drupalPostForm(NULL, $edit, 'Send Message');
    $this->drupalGet('/forward/node/' . $article->id());
    $edit = [
      'name' => 'Test Forwarder',
      'recipient' => 'test@test.com',
      'message' => 'This is a test personal message.',
    ];
    $this->drupalPostForm(NULL, $edit, 'Send Message');
    $this->assertText('Thank you for spreading the word about Drupal.', 'Users with override flood control permission can do more than 1 forward in an hour.');
  }

}
