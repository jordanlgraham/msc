##Utilities
###ArrayIterator.php
```php
$data = [
	'spoon',
	'fork',
	'knife',
	...
];

$iterator = new ArrayIterator($data);
foreach ($iterator->chunk(3) as $chunk) {
  foreach ($chunk as $value) {
    ...
  }
}
```
##Batch
###BatchBuilder.php
```php
$batch = (new BatchBuilder())
  ->setProcessorClass(get_class($this))
  ->size(19)
  ->data($accounts)
  ->run();
```
##Tests
###EmailTestTrait.php
###ExistingSiteBase.php
##Forms
###FormAlterBase.php


```php
/**
 * Implements hook_form_alter().
 */
my_module_form_alter(&$form, FormStateInterface $form_state) {
  \Drupal\my_module\FormAlter\MyForm::alter($form, $form_state);
}
```

```php
namespace Drupal\my_module\FormAlter;

class MyForm extends \Aten\DrupalTools\FormAlterBase {
  public function doAlter() {
    // Preform alter on $this->form and $this->formState;
  }
}
```
##Updates
###BatchUpdater
hook_update with batch process

```php
function my_module_post_update_create_new_entities(&$sandbox) {
  $user_storage = \Drupal::service('entity_type.manager')->getStorage('user');
  $order_storage = \Drupal::service('entity_type.manager')->getStorage('commerce_order');

  $batch_update = \Drupal::service('apitools.updater')->batch($sandbox, 1, 20);
  if (!$batch_update->inProgress()) {
    // Run query for all data.
    $results = $user_storage->getQuery()
      ->condition('status', 1)
      ->condition('roles', ['temporary_client', 'client'], 'IN')
      ->notExists('folder_id')
      ->execute();

    $batch_update->init($results);
  }

  $batch_update->process(function($client_user_ids) use ($order_storage) {
    $orders = $order_storage->loadByProperties(['uid' => array_values($client_user_ids)]);
    foreach ($orders as $order) {
      $user = $order->getCustomer();
      $user->setFolderId($order->id());
      $user->save();
    }
  });

  return $batch_update->summary();
}
```

###ContentUpdater
hook_update with content mapped by uuid
