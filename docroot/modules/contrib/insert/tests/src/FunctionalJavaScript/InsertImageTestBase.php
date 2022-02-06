<?php

namespace Drupal\Tests\insert\FunctionalJavascript;

abstract class InsertImageTestBase extends InsertFileTestBase {

  use ImageFieldCreationTrait;

  /**
   * @var array
   */
  public static $modules = ['node', 'file', 'image', 'insert', 'editor'];

}
