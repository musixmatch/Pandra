<?php
require_once 'PHPUnit/Framework.php';
require_once(dirname(__FILE__).'/../../config.php');
require_once dirname(__FILE__).'/../../lib/SuperColumnFamily.class.php';

// SuperColumn wrapper
class TestSuperColumn extends PandraSuperColumn {
    public function init() {
        $this->addColumn('title', 'string');
        $this->addColumn('content');
        $this->addColumn('author', 'string');
    }
}

// ColumnFamily (SuperColumn Wrapper)
class TestCFSuper extends PandraSuperColumnFamily {

    public function init() {
        $this->setKeySpace('Keyspace1');
        $this->setName('Super1');

        $this->addSuper(new TestSuperColumn('blog-slug-1'));
        $this->addSuper(new TestSuperColumn('blog-slug-2'));
    }
}

/**
 * Test class for PandraSuperColumnFamily.
 * Generated by PHPUnit on 2010-01-09 at 11:52:22.
 */
class PandraSuperColumnFamilyTest extends PHPUnit_Framework_TestCase {
    /**
     * @var    PandraSuperColumnFamily
     * @access protected
     */
    protected $obj;

    private $_keyID = 'PandraCFTest';

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp() {
        $this->obj = new TestCFSuper();
        $this->obj->setKeyID($this->_keyID);
        PandraCore::connect('default', 'localhost');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown() {
        PandraCore::disconnectAll();
    }

    public function testAddGetColumn() {
        $newSuperName = 'newGenericSuper';
        $this->assertTrue($this->obj->addColumn($newSuperName) instanceof PandraSuperColumn);
        $this->assertTrue($this->obj->getColumn($newSuperName)->getName() == $newSuperName && $this->obj->getColumn($newSuperName) instanceof PandraSuperColumn);
    }

    public function testAddSuper() {
        $newSuperName = 'newTestSuperColumn';
        $this->assertTrue($this->obj->addSuper(new TestSuperColumn($newSuperName)) instanceof PandraSuperColumn);
        $this->assertTrue($this->obj->getColumn($newSuperName)->getName() == $newSuperName);
    }

    public function testGetSuper() {
        $this->assertTrue($this->obj->getSuper('blog-slug-1') instanceof PandraSuperColumn);
    }

    public function testIsModified() {
        $this->assertTrue($this->obj->reset());

        $this->obj['blog-slug-1']['title'] = 'NEW TITLE';
        $this->assertTrue($this->obj->isModified());
    }

    public function testIsDeleted() {
        $this->obj->delete();
        $this->assertTrue($this->obj->getDelete());
    }

    public function testSaveLoadDelete() {

        // Save it
        $this->obj['blog-slug-1']['title'] = 'My First Blog';
        $this->obj['blog-slug-1']['content'] = 'Can I be in the blog-o-club too?';

        $this->obj['blog-slug-2']['title'] = 'My Second Blog, and maybe the last';
        $this->obj['blog-slug-2']['content'] = 'I promise to write something soon!';

        $this->assertTrue($this->obj->save(), $this->obj->lastError());

        // Grab some konown values to test with
        $colTitleValue = $this->obj['blog-slug-1']['title'];
        $colTitleValue2 = $this->obj['blog-slug-2']['title'];

        // Re-Load, check saved data
        $this->obj = NULL;
        $this->obj = new TestCFSuper();

        $this->assertTrue($this->obj->load($this->_keyID), $this->obj->lastError());

        // Test at least 2 supercolumns to make sure population is ok
        $this->assertTrue($colTitleValue == $this->obj['blog-slug-1']['title']);
        $this->assertTrue($colTitleValue2 == $this->obj['blog-slug-2']['title']);

        // Delete columnfamily
        $this->obj->delete();

        $this->assertTrue($this->obj->save(), $this->obj->lastError());

        // Confirm we can't load the key any more
        $this->obj = NULL;
        $this->obj = new TestCFSuper();

        $this->assertFalse($this->obj->load($this->_keyID), $this->obj->lastError());
    }

    public function testNotations() {

        $superName = 'blog-slug-3';
        $colName = 'title';
        $value = 'Another blog by me';

        // --------- Array Access
        //
        // Test Super add
        $this->obj[$superName] = new TestSuperColumn($superName);
        $this->assertTrue($this->obj[$superName] instanceof PandraSuperColumn);

        // Check column name and not column name are correctly set
        $this->obj->reset();
        $this->assertFalse($this->obj->isModified());
        $this->obj[$superName][$colName] = $value;
        $this->assertTrue($this->obj->isModified());

        $this->assertTrue($this->obj[$superName][$colName] == $value);
        $this->assertFalse($this->obj[$superName]['NOT_'.$colName] == $value);

        // Unset
        unset($this->obj[$superName][$colName]);
        $this->assertTrue($this->obj[$superName][$colName] == NULL);

        unset($this->obj[$superName]);
        $this->assertTrue($this->obj[$superName] == NULL);

        // --------- Magic Methods
        // Test Super Add
        $superPath = PandraSuperColumnFamily::_columnNamePrefix.$superName;
        $columnPath = PandraColumnFamily::_columnNamePrefix.$colName;

        $this->obj->$superPath = new TestSuperColumn($superName);
        $this->assertTrue($this->obj->$superPath instanceof PandraSuperColumn);

        // Check column name and not column name are correctly set
        $this->obj->reset();
        $this->assertFalse($this->obj->isModified());
        $this->obj->$superPath->$columnPath = $value;
        $this->assertTrue($this->obj->isModified());

        $this->assertTrue($this->obj->$superPath->$columnPath == $value);

        $nColumnPath = 'NOT_'.$columnPath;
        $this->assertFalse($this->obj->$superPath->$nColumnPath == $value);

        // Unset
        //unset($this->obj[$superName][$colName]);
        $this->obj->$superPath->destroyColumn($colName);
        $this->assertTrue($this->obj->$superPath->$columnPath == NULL);

        $this->obj->destroyColumn($superName);
        $this->assertTrue($this->obj->$superPath == NULL);

        // --------- Accessors/Mutators
        // Test Super Add
        $superPath = PandraSuperColumnFamily::_columnNamePrefix.$superName;
        $columnPath = PandraColumnFamily::_columnNamePrefix.$colName;

        $this->obj->addSuper(new TestSuperColumn($superName));
        $this->assertTrue($this->obj->getSuper($superName) instanceof PandraSuperColumn);

        // Check column name and not column name are correctly set
        $this->obj->reset();
        $this->assertFalse($this->obj->isModified());
        $this->obj->getSuper($superName)->getColumn($colName)->setValue($value);
        $this->assertTrue($this->obj->isModified());

        $this->assertTrue($this->obj->getSuper($superName)->getColumn($colName)->value == $value);

        $this->assertFalse($this->obj->getSuper($superName)->getColumn('NOT_'.$colName)->value == $value);

        // Unset
        unset($this->obj[$superName][$colName]);
        $this->obj->getSuper($superName)->destroyColumn($colName);
        $this->assertTrue($this->obj->getSuper($superName)->getColumn($colName) == NULL);

        unset($this->obj[$superName]);
        $this->obj->destroyColumn($superName);
        $this->assertTrue($this->obj->getSuper($superName) == NULL);
    }
}
?>