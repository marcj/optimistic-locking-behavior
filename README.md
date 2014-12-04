### OptimisticLockingBehavior

A behavior for Propel2 for optimistic locking.

## Usage

```xml
<table name="user">
    <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER" />
    <column name="username" type="VARCHAR" size="100" primaryString="true" />
    <behavior name="optimistic_locking" />
</table>
```

If you haven't installed this behavior through composer, you need to specify the full class name as behavior name:

```xml
    <behavior name="\MJS\OptimisticLocking\OptimisticLockingBehavior">
```

You can define a different locking columns. Default is `version`.

```xml
<behavior name="optimistic_locking" />
    <parameter name="version_column" value="locked_version"/>
</behavior>
```

```php
$user = UserQuery::create()->findById($id);
$user->setUsername('Secret');

try {
    $user->save();
} catch (\MJS\OptimisticLocking\StaleObjectException $e) {
    //react on that case. Maybe show the edit form again with a hint
    //or reload $user and apply again your changes.
}

if (!$user->optimisticSave(){ 
    //whoops, there was someone faster.
}
```