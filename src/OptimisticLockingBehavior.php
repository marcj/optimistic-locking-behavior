<?php

namespace MJS\OptimisticLocking;

use Propel\Generator\Behavior\Versionable\VersionableBehavior;
use Propel\Generator\Builder\Om\ObjectBuilder;
use Propel\Generator\Exception\InvalidArgumentException;
use Propel\Generator\Model\Behavior;
use Propel\Generator\Model\Column;
use Propel\Generator\Model\ForeignKey;
use Propel\Generator\Model\Table;
use Propel\Generator\Util\PhpParser;

/**
 * Provides optimistic locking ability.
 *
 * @author Marc J. Schmidt <marc@marcjschmidt.de>
 */
class OptimisticLockingBehavior extends Behavior
{
    // default parameters value
    protected $parameters = array(
        'version_column' => 'version'
    );

    public function modifyTable()
    {
        if ($this->hasVersionableBehavior()) {
            return;
        }

        $versionColumnName = $this->getParameter('version_column');

        if (!$this->getTable()->hasColumn($versionColumnName)) {
            $this->getTable()->addColumn([
                'name' => $versionColumnName,
                'type' => 'INTEGER'
            ]);

            $this->getTable()->addIndex([
                'columns' => array_merge($this->getTable()->getPrimaryKey(), [$this->getVersionColumn()])
            ]);
        }
    }

    /**
     * @return bool
     */
    public function hasVersionableBehavior()
    {
        return !!$this->getVersionableBehavior();
    }

    /**
     * @return VersionableBehavior|null
     */
    public function getVersionableBehavior()
    {
        $behaviors = $this->getTable()->getBehaviors();
        foreach ($behaviors as $behavior) {
            if ($behavior instanceof VersionableBehavior) {
                return $behavior;
            }
        }
    }

    public function objectFilter(&$script)
    {
        $parser = new PhpParser($script, true);
        $doUpdateMethod = $parser->findMethod('doUpdate');

        $versionPhpName = $this->getVersionColumn()->getPhpName();

        $newCode = <<<EOF
if (\$this->optimisticLockEnabled) {
    \$selectCriteria->filterBy{$versionPhpName}(\$this->locked{$versionPhpName});
}
EOF;
        $doUpdateMethod = str_replace(' return ', " $newCode\n       return ", $doUpdateMethod);

        $parser->replaceMethod('doUpdate', $doUpdateMethod);
        $script = $parser->getCode();
    }

    public function objectAttributes($builder)
    {
        $versionPhpName = $this->getVersionColumn()->getPhpName();

        return <<<EOF
    /**
     * Used as additional WHERE condition.
     *
     * @var integer
     */
    protected \$locked{$versionPhpName};

    /**
     * @var boolean
     */
    protected \$optimisticLockEnabled = true;
EOF;
    }

    public function objectMethods($builder)
    {
        return <<<EOF
/**
 * Instead of throwing directly a StaleObjectException if save() fails due to locking failure,
 * this methods wraps the exception and returns simple boolean value.
 *
 * @return boolean
 */
public function optimisticSave()
{
    try {
        \$this->save();
    } catch (\MJS\OptimisticLocking\StaleObjectException \$e) {
        return false;
    }

    return true;
}

/**
 * Disables the whole optimistic locking. Make sure you know really what you're doing, this may lead to
 * a corrupt wrong data in your database. (if some connection wrote already between your model instantiation and
 * save() call)
 *
 * @return integer affected rows
 */
public function disableOptimisticLocking()
{
    \$this->optimisticLockEnabled = false;
}

/**
 * Enables optimistic locking.
 */
public function enableOptimisticLocking()
{
    \$this->optimisticLockEnabled = true;
}
EOF;
    }

    public function postUpdate()
    {
        $versionPhpName = $this->getVersionColumn()->getPhpName();

        $resetVersion = "
    \$this->set{$versionPhpName}(\$this->locked{$versionPhpName});";
//        if ($this->hasVersionableBehavior()) {
//            $resetVersion = "
//    \$this->set{$versionPhpName}(\$this->locked{$versionPhpName});";
//        }

        return <<<EOF
if (\$this->optimisticLockEnabled && \$wasModified && false === \$isInsert && 0 === \$affectedRows) {
    \$this->modifiedColumns = \$modifiedColumnsBackup;
    $resetVersion
    throw \MJS\OptimisticLocking\StaleObjectException::createFromObject(\$this, \$this->lockedVersion);
}
//\$this->locked{$versionPhpName} = \$this->get{$versionPhpName}();
EOF;
    }

    /**
     * @return Column
     */
    protected function getVersionColumn()
    {
        return $this->getTable()->getColumn($this->getParameter('version_column'));
    }

    /**
     * @param ObjectBuilder $builder
     *
     * @return string
     */
    public function preSave(ObjectBuilder $builder)
    {
        $versionPhpName = $this->getVersionColumn()->getPhpName();

        $incrementVersion = '';
        if (!$this->hasVersionableBehavior()) {
            $incrementVersion = "\$this->set{$versionPhpName}(\$this->locked{$versionPhpName} + 1);";
        }

        return <<<EOF
\$wasModified = \$this->isModified();
if (\$this->isModified()) {
//    if (null === \$this->locked{$versionPhpName}) {
        \$this->locked{$versionPhpName} = \$this->get{$versionPhpName}();
//    }
    $incrementVersion
}
\$modifiedColumnsBackup = \$this->modifiedColumns;
EOF;

    }

}
