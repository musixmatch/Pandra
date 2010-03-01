<?php
/**
 * (c) 2010 phpgrease.net
 *
 * For licensing terms, plese see license.txt which should distribute with this source
 *
 * @package Pandra
 * @author Michael Pearson <pandra-support@phpgrease.net>
 */
class PandraSuperColumn extends PandraColumnContainer {

    /* @var PandraColumnFamily column family parent reference */
    private $_parent = NULL;

    private $_columnFamilyName = NULL;

    /**
     * Supercolumn constructor
     * @param string $superName Super Column name
     * @param PandraSuperColumnFamily $parent
     */
    public function __construct($superName, PandraSuperColumnFamily $parent = NULL) {
        // SuperColumn name
        $this->setName($superName);

        // Reference parent ColumnFamilySuper
        if ($parent !== NULL) {
            $this->setParent($parent);
        }
        parent::__construct();
    }

    public function pathOK($keyID = NULL) {
        if ($this->_parent === NULL) {
            return $this->pathOK($keyID);
        }
        return $this->_parent->pathOK($keyID);
    }

    /**
     * Save all columns in this loaded columnfamily
     * @return void
     */
    public function save($consistencyLevel = NULL) {

        if (!$this->isModified()) return FALSE;

        $ok = $this->pathOK();

        if ($ok) {
            if ($this->getDelete()) {

                $columnPath = new cassandra_ColumnPath();
                $columnPath->column_family = $this->_parent->getName();
                $columnPath->super_column = $this->getName();
                $ok = PandraCore::deleteColumnPath($this->_parent->getKeySpace(),
                        $this->_parent->getKeyID(),
                        $columnPath,
                        NULL,
                        PandraCore::getConsistency($consistencyLevel));

            } else {

                $this->bindTimeModifiedColumns();
                $ok = PandraCore::saveSuperColumn(  $this->_parent->getKeySpace(),
                        $this->_parent->getKeyID(),
                        array($this->_parent->getName()),
                        array($this->getName() => $this->getModifiedColumns()),
                        PandraCore::getConsistency($consistencyLevel));
            }

            if ($ok) {
                $this->reset();
            } else {
                $this->registerError(PandraCore::$lastError);
            }
        }

        return $ok;
    }

    /**
     * Loads a SuperColumn for key
     *
     * Load will generate RuntimeException if parent column family has not been set (
     *
     * @param string $keyID row key
     * @param bool $colAutoCreate create columns in the object instance which have not been defined
     * @param int $consistencyLevel cassandra consistency level
     * @return bool loaded OK
     */
    public function load($keyID = NULL, $colAutoCreate = NULL, $consistencyLevel = NULL) {

        if ($keyID === NULL) $keyID = $this->getKeyID();

        $ok = $this->pathOK($keyID);

        $this->setLoaded(FALSE);

        if ($ok) {
            $result = PandraCore::getCFSlice(
                    ($this->getKeySpace() === NULL ? $this->_parent->getKeySpace() : $this->getKeySpace()),
                    $keyID,
                    ($this->getColumnFamilyName() === NULL ? $this->_parent->getName() : $this->getColumnFamilyName()),
                    $this->getName(),
                    PandraCore::getConsistency($consistencyLevel));

            if (!empty($result)) {
                $this->init();
                $this->setLoaded($this->populate($result, $this->getAutoCreate($colAutoCreate)));
                if ($this->isLoaded()) $this->keyID = $keyID;
            } else {
                $this->registerError(PandraCore::$lastError);
            }
        }
        return ($ok && $this->isLoaded());
    }

    /**
     * Sets parent ColumnFamily or
     * @param PandraColumnContainer $parent
     */
    public function setParent(PandraSuperColumnFamily $parent) {
        $this->_parent = $parent;
    }

    /**
     * Gets the current working parent column family
     * @return <type>
     */
    public function getParent() {
        return $this->_parent;
    }

    /**
     * accessor, container name
     * @return string container name
     */
    public function getColumnFamilyName() {
        return $this->_columnFamilyName;
    }

    /**
     * mutator, container name
     * @param string $name new name
     */
    public function setColumnFamilyName($columnFamilyName) {
        $this->_columnFamilyName = $columnFamilyName;
    }

    /**
     * Creates an error entry in this column and propogate to parent
     * @param string $errorStr error string
     */
    public function registerError($errorStr) {
        if (!empty($errorStr)) {
            array_push($this->errors, $errorStr);
            if ($this->_parent !== NULL) $this->_parent->registerError($errorStr);
        }
    }
}
?>