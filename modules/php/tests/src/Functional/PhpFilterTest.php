<?php

namespace Drupal\Tests\php\Functional;

/**
 * Tests to make sure the PHP filter actually evaluates PHP code when used.
 *
 * @group PHP
 */
class PhpFilterTest extends PhpTestBase {

  /**
   * Makes sure that the PHP filter evaluates PHP code when used.
   */
  public function testPhpFilter() {
    // Log in as a user with permission to use the PHP code text format.
    $php_code_permission = \Drupal::service('entity_type.manager')->getStorage('filter_format')->load('php_code')->getPermissionName();
    $permissions = [
      'access content',
      'create page content',
      'edit own page content',
      $php_code_permission,
    ];
    $web_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($web_user);

    // Create a node with PHP code in it.
    $node = $this->createNodeWithCode();

    // Make sure that the PHP code shows up as text.
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextContains('php print');

    // Change filter to PHP filter and see that PHP code is evaluated.
    $edit = [];
    $edit['body[0][format]'] = $this->phpCodeFormat->id();
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->responseContains(t('@type %title has been updated.', ['@type' => 'Basic page', '%title' => $node->toLink($node->getTitle())->toString()]));

    // Make sure that the PHP code shows up as text.
    $this->assertSession()->pageTextNotContains('print "SimpleTest PHP was executed!"');
    $this->assertSession()->pageTextContains('SimpleTest PHP was executed!');

    // Verify that cache is disabled for PHP evaluates.
    $this->assertSession()->pageTextContains('Current state is empty');
    \Drupal::state()->set('php_state_test', 'not empty');
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->pageTextContains('Current state is not empty');
  }

}
