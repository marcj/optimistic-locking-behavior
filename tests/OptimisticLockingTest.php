<?php

namespace Propel\Tests\Generator\Behavior\ChangeLogger;

use Propel\Generator\Util\QuickBuilder;
use Propel\Tests\TestCase;

class OptimisticLockingTest extends TestCase
{
    public function setUp()
    {
        if (!class_exists('\OptimisticLockingTable')) {
            $schema = <<<EOF
<database name="optimistic_locker_behavior_test">
    <table name="optimistic_locking_table">
        <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER" />
        <column name="title" type="VARCHAR" size="100" primaryString="true" />
        <column name="age" type="INTEGER" />
        <column name="related_id" type="INTEGER" />
        <behavior name="MJS\OptimisticLocking\OptimisticLockingBehavior" />
        <foreign-key foreignTable="optimistic_locking_related_table">
            <reference local="related_id" foreign="id" />
        </foreign-key>
    </table>
    <table name="optimistic_locking_related_table">
        <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER" />
        <column name="name" type="VARCHAR" size="100" primaryString="true" />
    </table>
    <table name="optimistic_locking_versionable_table">
        <column name="id" required="true" primaryKey="true" autoIncrement="true" type="INTEGER" />
        <column name="title" type="VARCHAR" size="100" primaryString="true" />
        <column name="age" type="INTEGER" />
        <column name="related_id" type="INTEGER" />
        <behavior name="MJS\OptimisticLocking\OptimisticLockingBehavior" />
        <behavior name="versionable" />
    </table>
</database>
EOF;
            QuickBuilder::buildSchema($schema);
        }
    }

    public function testVersionable()
    {
        \OptimisticLockingVersionableTableQuery::create()->deleteAll();

        $itemAlice = new \OptimisticLockingVersionableTable();
        $itemAlice->setTitle('Alice');
        $itemAlice->setAge('24');
        $itemAlice->save();
        $this->assertEquals(1, $itemAlice->getVersion());
        $this->assertEquals(1, $itemAlice->getLastVersionNumber());

        \Map\OptimisticLockingVersionableTableTableMap::clearInstancePool();
        $itemBob = \OptimisticLockingVersionableTableQuery::create()->findOneByTitle('Alice');

        $this->assertNotSame($itemAlice, $itemBob);
        $itemBob->setTitle('Bob');
        $itemBob->setAge('26');
        $affected = $itemBob->save();
        $this->assertEquals(1, $affected);
        $this->assertEquals(2, $itemBob->getVersion());
        $this->assertEquals(2, $itemBob->getLastVersionNumber());

        //save now $itemAlice, this shouldn't work.
        $itemAlice->setAge(25);
        $lastException = null;
        $affected = 0;
        try {
            $affected = $itemAlice->save();
        } catch (\Exception $e) {
            $lastException = $e;
        }

        $this->assertNotNull($lastException);
        $this->assertInstanceOf('MJS\OptimisticLocking\StaleObjectException', $lastException);
        $this->assertEquals('Object with version 1 is outdated', $lastException->getMessage());
        $this->assertEquals(0, $affected);
        $this->assertEquals(1, $itemAlice->getVersion());
        $this->assertTrue($itemAlice->isModified(), 'still modified');
        $this->assertEquals(1, $itemAlice->getVersion());

        $this->assertFalse($itemAlice->optimisticSave(), 'this returns just false, since the StaleObjectException gets thrown.');

        // we overwrite now the database
        $itemAlice->disableOptimisticLocking();
        $this->assertEquals(1, $itemAlice->getVersion());
        $this->assertEquals(1, $itemAlice->save());
        $this->assertEquals(3, $itemAlice->getVersion()); //because of bob's save, versionable sets to newest version always
    }

    public function testLock()
    {
        \OptimisticLockingTableQuery::create()->deleteAll();
        \OptimisticLockingRelatedTableQuery::create()->deleteAll();

        $related = new \OptimisticLockingRelatedTable();
        $related->setName('Teschd');

        $itemAlice = new \OptimisticLockingTable();
        $itemAlice->setTitle('Alice');
        $itemAlice->setAge('24');
        $itemAlice->save();

        \Map\OptimisticLockingTableTableMap::clearInstancePool();
        $itemBob = \OptimisticLockingTableQuery::create()->findOneByTitle('Alice');

        $this->assertNotSame($itemAlice, $itemBob);
        $itemBob->setTitle('Bob');
        $itemBob->setAge('26');
        $affected = $itemBob->save();
        $this->assertEquals(1, $affected);
        $this->assertEquals(1, $itemAlice->getVersion());

        //save now $itemAlice, this shouldn't work.
        $itemAlice->setAge(25);
        $lastException = null;
        $affected = 0;
        try {
            $affected = $itemAlice->save();
        } catch (\Exception $e) {
            $lastException = $e;
        }

        $this->assertNotNull($lastException);
        $this->assertInstanceOf('MJS\OptimisticLocking\StaleObjectException', $lastException);
        $this->assertEquals('Object with version 1 is outdated', $lastException->getMessage());
        $this->assertEquals(0, $affected);
        $this->assertEquals(1, $itemAlice->getVersion());
        $this->assertTrue($itemAlice->isModified(), 'still modified');

        $this->assertFalse($itemAlice->optimisticSave(), 'this returns just false, since the StaleObjectException gets thrown.');

        // we overwrite now the database
        $itemAlice->disableOptimisticLocking();
        $this->assertEquals(1, $itemAlice->save());
        $this->assertEquals(2, $itemAlice->getVersion());
    }

    public function testBasics()
    {
        \OptimisticLockingTableQuery::create()->deleteAll();

        $item = new \OptimisticLockingTable();
        $item->setTitle('Alice');
        $item->setAge('24');
        $item->save();

        $this->assertEquals(1, $item->getVersion(), 'first version');
        $item->save();
        $this->assertEquals(1, $item->getVersion(), 'nothing changed');

        $item->setAge('25');
        $item->save();
        $this->assertEquals(2, $item->getVersion(), 'new version');
    }

}